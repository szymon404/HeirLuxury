<?php

// ABOUTME: Artisan command to import luxury product catalogs from folder structure into DB.
// ABOUTME: Dynamically parses {brand}-{section}-{gender} folders and generates thumbnails.

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ThumbnailService;
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
                            {--skip-thumbnails : Skip thumbnail generation}';

    protected $description = 'Import luxury product folders into the database';

    /**
     * Brand name mapping from folder prefix to full brand name.
     */
    protected array $brandMap = [
        'lv' => 'louis-vuitton',
        'chanel' => 'chanel',
        'dior' => 'dior',
        'gucci' => 'gucci',
        'amiri' => 'amiri',
        'celine' => 'celine',
        'givenchy' => 'givenchy',
        'mcqueen' => 'mcqueen',
        'moncler' => 'moncler',
        'nike' => 'nike',
        'offwhite' => 'offwhite',
        'philippplein' => 'philipp-plein',
        'versace' => 'versace',
        'yeezy' => 'yeezy',
    ];

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

        foreach ($folders as $folder) {
            // Parse folder name: {brand}-{section}-{gender}
            // e.g., "lv-bags-women", "chanel-shoes-men", "hermes-belts-women"
            $parsed = $this->parseFolderName($folder);

            if (! $parsed) {
                $this->warn("Skipping unrecognized folder: $folder");

                continue;
            }

            $categorySlug = $parsed['category_slug'];
            $brand = $parsed['brand'];
            $gender = $parsed['gender'];
            $section = $parsed['section'];

            $path = "$base/$folder";

            $this->info("📂 Importing: $folder → $categorySlug");

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
                 * Create or update the product record.
                 *
                 * Uses category_slug + name as the unique key to prevent duplicates
                 * while allowing the same product name in different categories.
                 */
                Product::updateOrCreate(
                    [
                        'category_slug' => $categorySlug,
                        'name' => $dir,
                    ],
                    [
                        'slug' => Str::slug($dir),
                        'folder' => $dir,
                        'brand' => $brand,
                        'gender' => $gender,
                        'section' => $section,
                        'image' => $firstImage,
                        'image_path' => $imagePath,
                    ]
                );

                // Generate optimized thumbnails for all images unless skipped
                if (! $this->option('skip-thumbnails')) {
                    foreach ($images as $img) {
                        $imgPath = "imports/$folder/$dir/$img";
                        $thumbnailService->generateAll($imgPath);
                    }
                }

                $folderCount++;
                $totalImported++;
            }

            $this->info("  ✔ Imported $folderCount products");
            $this->line('');
        }

        $this->info("🎉 Import complete! Total products: $totalImported");

        return Command::SUCCESS;
    }

    /**
     * Parse folder name into brand, section, gender, and category_slug.
     *
     * Supports formats:
     * - {brand}-{section}-{gender} (e.g., lv-bags-women, chanel-shoes-men)
     */
    protected function parseFolderName(string $folder): ?array
    {
        // Pattern: brand-section-gender
        // e.g., lv-bags-women, chanel-shoes-men, hermes-belts-women
        if (! preg_match('/^([a-z]+)-([a-z]+)-(women|men)$/i', $folder, $matches)) {
            return null;
        }

        $brandPrefix = strtolower($matches[1]);
        $section = strtolower($matches[2]);
        $gender = strtolower($matches[3]);

        // Map brand prefix to full brand name
        $brand = $this->brandMap[$brandPrefix] ?? $brandPrefix;

        // Build category_slug: {brand}-{gender}-{section}
        // e.g., louis-vuitton-women-bags, chanel-men-shoes
        $categorySlug = "{$brand}-{$gender}-{$section}";

        return [
            'brand' => $brand,
            'section' => $section,
            'gender' => $gender,
            'category_slug' => $categorySlug,
            'folder' => $folder,
        ];
    }
}
