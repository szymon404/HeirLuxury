<?php

// ABOUTME: Artisan command to import luxury product catalogs from folder structure into DB.
// ABOUTME: Dynamically parses {brand}-{section}-{gender} folders and generates thumbnails.

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ThumbnailService;
use App\Support\BrandRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Import luxury product catalogs from folder structure into the database.
 *
 * This command scans the storage/app/public/imports directory for product folders
 * and creates/updates Product records in the database. It's designed for bulk
 * importing luxury product catalogs that have been organized into folders.
 *
 * Expected Folder Structure:
 * ```
 * storage/app/public/imports/
 * ├── {brand}-{section}-{gender}/
 * │   ├── Product Name/
 * │   │   ├── 0000.jpg
 * │   │   └── ...
 * ```
 *
 * Supported folder patterns:
 * - lv-bags-women, lv-shoes-men, lv-clothes-women, etc.
 * - chanel-bags-women, chanel-shoes-men, etc.
 * - dior-bags-women, dior-shoes-men, etc.
 * - Any {brand}-{section}-{gender} folder is auto-parsed.
 *
 * Usage:
 *   php artisan import:lv              # Import new products, skip existing
 *   php artisan import:lv --fresh      # Clear all products first, then import
 *   php artisan import:lv --skip-thumbnails  # Import without generating thumbnails
 *
 * @see \App\Services\ThumbnailService For thumbnail generation during import
 * @see \App\Console\Commands\GenerateThumbnails For batch thumbnail generation
 */
class ImportLV extends Command
{
    protected $signature = 'import:lv
                            {--fresh : Delete existing products first}
                            {--skip-thumbnails : Skip thumbnail generation}
                            {--start-from= : Resume from this category folder (e.g. dior-shoes-men)}
                            {--only= : Only import categories matching this brand prefix (e.g. lv,celine)}
                            {--folder= : Import exactly one category folder (e.g. lv-bags-women)}';

    protected $description = 'Import luxury product folders into the database';

    /**
     * Execute the import process.
     *
     * @param  ThumbnailService  $thumbnailService  Injected service for thumbnail generation
     * @return int Command::SUCCESS or Command::FAILURE
     */
    public function handle(ThumbnailService $thumbnailService)
    {
        $base = storage_path('app/public/imports');

        if (! is_dir($base)) {
            $this->error("Folder not found: $base");

            return Command::FAILURE;
        }

        // Clear all products if --fresh flag is provided
        if ($this->option('fresh')) {
            Product::truncate();
            $this->warn('Cleared all existing products.');
        }

        // Get all category folders (excluding . and ..)
        $folders = array_filter(scandir($base), function ($f) use ($base) {
            return $f !== '.' && $f !== '..' && is_dir("$base/$f");
        });

        $totalImported = 0;
        $startFrom = $this->option('start-from');
        $started = $startFrom === null;
        $only = $this->option('only')
            ? array_map('trim', explode(',', strtolower($this->option('only'))))
            : null;
        $onlyFolder = $this->option('folder');

        foreach ($folders as $folder) {
            // Scope to exactly one folder when --folder= is supplied.
            if ($onlyFolder !== null && $folder !== $onlyFolder) {
                continue;
            }

            // Filter by --only brands
            if ($only) {
                $match = false;
                foreach ($only as $prefix) {
                    if (str_starts_with(strtolower($folder), $prefix.'-')) {
                        $match = true;
                        break;
                    }
                }
                if (! $match) {
                    continue;
                }
            }

            if (! $started) {
                if ($folder === $startFrom) {
                    $started = true;
                } else {
                    $this->line("⏭ Skipping: $folder (before --start-from)");

                    continue;
                }
            }
            // Parse folder name: {brand}-{section}-{gender}
            // e.g., "lv-bags-women", "chanel-shoes-men", "lv-belts-unisex"
            // Unisex folders expand to two entries (men + women slugs) so the
            // same product appears in both catalogs.
            $parsedList = $this->parseFolderName($folder);

            if (empty($parsedList)) {
                $this->warn("Skipping unrecognized folder: $folder");

                continue;
            }

            $categoryDisplay = implode(', ', array_column($parsedList, 'category_slug'));
            $path = "$base/$folder";

            $this->info("📂 Importing: $folder → $categoryDisplay");

            $folderCount = 0;

            // Process each product subfolder within the category
            foreach (scandir($path) as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }

                $productDir = "$path/$dir";
                if (! is_dir($productDir)) {
                    continue;
                }

                // Collect all image files (jpg, jpeg, png, webp)
                $images = array_values(array_filter(scandir($productDir), fn ($f) => preg_match('/\.(jpg|jpeg|png|webp)$/i', $f)
                ));

                // Skip folders with no images
                if (empty($images)) {
                    $this->warn("  ⭕ Skipping $dir — No images");

                    continue;
                }

                // Sort to ensure consistent first image (typically 0000.jpg)
                sort($images);
                $firstImage = $images[0];

                // Build the relative path for the primary image
                $imagePath = "imports/$folder/$dir/$firstImage";

                /*
                 * Create or update one Product row per parsed category.
                 *
                 * Uses category_slug + name as the unique key to prevent duplicates
                 * while allowing the same product name in different categories.
                 * For unisex folders this writes two rows (one per gender slug),
                 * each tagged with the matching binary gender.
                 */
                foreach ($parsedList as $parsed) {
                    Product::updateOrCreate(
                        [
                            'category_slug' => $parsed['category_slug'],
                            'name' => $dir,
                        ],
                        [
                            'slug' => Str::slug($dir),
                            'folder' => $dir,
                            'brand' => $parsed['brand'],
                            'gender' => $parsed['gender'],
                            'section' => $parsed['section'],
                            'image' => $firstImage,
                            'image_path' => $imagePath,
                        ]
                    );
                }

                // Generate optimized thumbnails for all images unless skipped.
                // Thumbnails live at the image path, not the category, so we
                // generate once even when the product is duplicated across slugs.
                if (! $this->option('skip-thumbnails')) {
                    foreach ($images as $img) {
                        $imgPath = "imports/$folder/$dir/$img";
                        $thumbnailService->generateAll($imgPath);
                    }
                }

                $folderCount++;
                $totalImported++;

                // Free memory between products
                gc_collect_cycles();
            }

            $this->info("  ✔ Imported $folderCount products");
            $this->line('');
        }

        $this->info("🎉 Import complete! Total products: $totalImported");

        return Command::SUCCESS;
    }

    /**
     * Parse folder name into one or more {brand, section, gender, category_slug}
     * entries.
     *
     * Supports formats:
     * - {brand}-{section}-{gender} where gender ∈ {men, women, unisex}
     *   (e.g., lv-bags-women, chanel-shoes-men, lv-belts-unisex)
     *
     * Returns:
     * - Empty array if the folder name does not match the expected pattern.
     * - A single-entry list for men/women folders.
     * - A two-entry list for unisex folders — one with gender='men' and one
     *   with gender='women' — so that unisex products are duplicated into
     *   both catalogs (the taxonomy has no first-class `unisex` gender).
     *
     * @return array<int, array{brand:string, section:string, gender:string, category_slug:string, folder:string}>
     */
    public function parseFolderName(string $folder): array
    {
        // Pattern: brand-section-gender (lowercase, ASCII-only)
        if (! preg_match('/^([a-z]+)-([a-z]+)-(women|men|unisex)$/', $folder, $matches)) {
            return [];
        }

        $brandPrefix = $matches[1];
        $section = $matches[2];
        $gender = $matches[3];

        // Map brand prefix to full brand name (config/brands.php).
        // Unknown prefixes pass through verbatim so a new brand still creates
        // a usable Product row before its config entry is added.
        $brand = BrandRegistry::prefixToSlug($brandPrefix) ?? $brandPrefix;

        // Unisex expands into both men and women entries; binary genders
        // produce a single entry.
        $targetGenders = $gender === 'unisex' ? ['men', 'women'] : [$gender];

        $entries = [];
        foreach ($targetGenders as $targetGender) {
            // Build category_slug: {brand}-{gender}-{section}
            // e.g., louis-vuitton-women-bags, chanel-men-shoes
            $entries[] = [
                'brand' => $brand,
                'section' => $section,
                'gender' => $targetGender,
                'category_slug' => "{$brand}-{$targetGender}-{$section}",
                'folder' => $folder,
            ];
        }

        return $entries;
    }
}
