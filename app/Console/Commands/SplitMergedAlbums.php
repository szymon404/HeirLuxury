<?php

// ABOUTME: Artisan command to detect and split Yupoo scraper albums merged into the same folder.
// ABOUTME: Uses file modification timestamp gaps to identify batch boundaries and moves files to new folders.

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ThumbnailService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Split merged Yupoo scraper albums back into separate product folders.
 *
 * The Yupoo scraper sometimes merges images from different products into the
 * same folder when albums share sequential names (e.g., "Amiri 0001" appears
 * in multiple scraper pages). This command detects these merges by analyzing
 * file modification timestamps and splits them into separate product folders.
 *
 * Detection: Files within a single scrape batch have timestamps within seconds
 * of each other. A gap of >5 minutes between consecutive files indicates a
 * different product's images were appended later.
 *
 * Usage:
 *   php artisan scraper:split-merged --dry-run                          # Preview all splits
 *   php artisan scraper:split-merged --category=amiri-clothes-men       # One category
 *   php artisan scraper:split-merged                                     # Split all
 *   php artisan scraper:split-merged --skip-thumbnails                  # Split without thumbnails
 */
class SplitMergedAlbums extends Command
{
    protected $signature = 'scraper:split-merged
                            {--category= : Process only this import folder (e.g., gucci-belts-men)}
                            {--dry-run : Preview splits without moving files or creating DB records}
                            {--gap=300 : Seconds between file timestamps to detect a new batch}
                            {--skip-thumbnails : Skip thumbnail generation for new folders}';

    protected $description = 'Split Yupoo scraper albums that merged multiple products into one folder';

    /**
     * Brand name mapping from folder prefix to full brand name.
     * Identical to ImportLV::$brandMap.
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

    public function handle(ThumbnailService $thumbnailService): int
    {
        $base = storage_path('app/public/imports');
        $dryRun = $this->option('dry-run');
        $gapThreshold = (int) $this->option('gap');
        $skipThumbnails = $this->option('skip-thumbnails');
        $categoryFilter = $this->option('category');

        if (! is_dir($base)) {
            $this->error("Imports folder not found: $base");

            return Command::FAILURE;
        }

        if ($categoryFilter) {
            if (! is_dir("$base/$categoryFilter")) {
                $this->error("Category folder not found: $categoryFilter");

                return Command::FAILURE;
            }
            $categoryFolders = [$categoryFilter];
        } else {
            $categoryFolders = array_values(array_filter(
                scandir($base),
                fn ($f) => $f !== '.' && $f !== '..' && is_dir("$base/$f")
            ));
        }

        if ($dryRun) {
            $this->warn('[DRY RUN] No files will be moved or DB records created.');
        }

        $this->info('Scanning '.count($categoryFolders)." categories (gap threshold: {$gapThreshold}s)...");
        $this->newLine();

        $totalScanned = 0;
        $totalMerged = 0;
        $totalSplits = 0;
        $totalErrors = 0;

        foreach ($categoryFolders as $categoryFolder) {
            $parsed = $this->parseFolderName($categoryFolder);
            if (! $parsed) {
                $this->warn("Skipping unrecognized folder: $categoryFolder");

                continue;
            }

            $result = $this->processCategory(
                $categoryFolder,
                "$base/$categoryFolder",
                $parsed,
                $thumbnailService,
                $dryRun,
                $gapThreshold,
                $skipThumbnails
            );

            $totalScanned += $result['scanned'];
            $totalMerged += $result['merged'];
            $totalSplits += $result['splits'];
            $totalErrors += $result['errors'];
        }

        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] ' : '').'Complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Folders scanned', $totalScanned],
                ['Merged folders detected', $totalMerged],
                ['New folders '.($dryRun ? 'to create' : 'created'), $totalSplits],
                ['Errors', $totalErrors],
            ]
        );

        if ($dryRun && $totalSplits > 0) {
            $this->warn("Run without --dry-run to apply $totalSplits splits.");
        }

        return Command::SUCCESS;
    }

    /**
     * Process all product folders within a single import category.
     */
    protected function processCategory(
        string $categoryFolder,
        string $categoryPath,
        array $parsed,
        ThumbnailService $thumbnailService,
        bool $dryRun,
        int $gapThreshold,
        bool $skipThumbnails
    ): array {
        $result = ['scanned' => 0, 'merged' => 0, 'splits' => 0, 'errors' => 0];

        $productDirs = array_values(array_filter(
            scandir($categoryPath),
            fn ($f) => $f !== '.' && $f !== '..' && is_dir("$categoryPath/$f")
        ));

        foreach ($productDirs as $productFolder) {
            $result['scanned']++;
            $productPath = "$categoryPath/$productFolder";

            $detection = $this->detectBatches($productPath, $gapThreshold);
            if (empty($detection)) {
                continue;
            }

            [$batches, $coverFiles] = $detection;
            $batchSizes = implode(' + ', array_map('count', $batches));

            // Only print category header on first merged folder found
            if ($result['merged'] === 0) {
                $this->line("<info>$categoryFolder</info>");
            }

            $this->line("  <comment>$productFolder</comment>: ".count($batches)." batches ($batchSizes files)");

            $result['merged']++;

            $newFolders = $this->executeSplit(
                $categoryFolder,
                $categoryPath,
                $productFolder,
                $batches,
                $thumbnailService,
                $dryRun
            );

            $result['splits'] += count($newFolders);

            foreach ($newFolders as $newFolder) {
                $this->line('    → '.($dryRun ? 'Would create' : 'Created').": $newFolder");

                $this->createProductRecord(
                    $parsed,
                    $newFolder,
                    $categoryFolder,
                    $thumbnailService,
                    $dryRun,
                    $skipThumbnails
                );
            }

            // Refresh the original product's image fields after removing files
            $this->refreshOriginalProduct(
                $parsed,
                $productFolder,
                $categoryFolder,
                $thumbnailService,
                $dryRun
            );

            // Free memory between products
            gc_collect_cycles();
        }

        return $result;
    }

    /**
     * Detect batch boundaries in a product folder using timestamp gaps.
     *
     * Returns empty array if folder has only one batch (nothing to split).
     * Otherwise returns [batches, coverFiles] where batches is an array of
     * arrays of filenames, and coverFiles are 4-digit files to keep with batch 0.
     */
    protected function detectBatches(string $productPath, int $gapThreshold): array
    {
        $allFiles = array_values(array_filter(
            scandir($productPath),
            fn ($f) => preg_match('/\.(jpe?g|png|webp)$/i', $f)
        ));

        // 4-digit cover files (0000.jpg, 0001.jpg) stay with batch 0
        $coverFiles = array_values(array_filter(
            $allFiles,
            fn ($f) => preg_match('/^\d{4}\.(jpe?g|png|webp)$/i', $f)
        ));

        // Only 5-digit sequential files participate in batch detection
        $seqFiles = array_values(array_filter(
            $allFiles,
            fn ($f) => preg_match('/^\d{5}\.(jpe?g|png|webp)$/i', $f)
        ));
        sort($seqFiles);

        if (count($seqFiles) < 2) {
            return [];
        }

        // Walk through files, split on timestamp gaps
        $batches = [[]];
        $maxTimestamp = 0;

        foreach ($seqFiles as $filename) {
            $mtime = filemtime("$productPath/$filename");

            // A gap larger than the threshold from the highest timestamp seen
            // so far indicates a new batch (handles concurrent download jitter)
            if ($maxTimestamp > 0 && $mtime > $maxTimestamp + $gapThreshold) {
                $batches[] = [];
            }

            $batches[count($batches) - 1][] = $filename;
            $maxTimestamp = max($maxTimestamp, $mtime);
        }

        if (count($batches) === 1) {
            return [];
        }

        return [$batches, $coverFiles];
    }

    /**
     * Move files from batch 1+ into new split folders with renumbered filenames.
     *
     * Batch 0 stays in the original folder untouched.
     *
     * @return array<string> Names of newly created folders
     */
    protected function executeSplit(
        string $categoryFolder,
        string $categoryPath,
        string $productFolder,
        array $batches,
        ThumbnailService $thumbnailService,
        bool $dryRun
    ): array {
        $originalPath = "$categoryPath/$productFolder";
        $newFolders = [];

        // Skip batch 0 (stays in place), process batch 1+
        for ($i = 1; $i < count($batches); $i++) {
            $batchFiles = $batches[$i];
            $splitNumber = $i + 1;
            $newFolderName = "$productFolder split $splitNumber";
            $newPath = "$categoryPath/$newFolderName";

            // Collision safety (in case command is run multiple times)
            if (is_dir($newPath)) {
                $suffix = 1;
                while (is_dir("{$newPath}_{$suffix}")) {
                    $suffix++;
                }
                $newFolderName = "{$newFolderName}_{$suffix}";
                $newPath = "$categoryPath/$newFolderName";
                $this->warn("    Collision detected, using: $newFolderName");
            }

            if (! $dryRun) {
                mkdir($newPath, 0755, true);

                foreach ($batchFiles as $newIndex => $filename) {
                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                    $newFilename = str_pad((string) $newIndex, 5, '0', STR_PAD_LEFT).".$ext";

                    rename("$originalPath/$filename", "$newPath/$newFilename");

                    // Invalidate stale thumbnail for the old path
                    $thumbnailService->deleteThumbnails("imports/$categoryFolder/$productFolder/$filename");
                }
            }

            $newFolders[] = $newFolderName;
        }

        return $newFolders;
    }

    /**
     * Create a Product DB record for a newly split-out folder.
     * Mirrors the logic in ImportLV's inner loop.
     */
    protected function createProductRecord(
        array $parsedCategory,
        string $newFolderName,
        string $categoryFolder,
        ThumbnailService $thumbnailService,
        bool $dryRun,
        bool $skipThumbnails
    ): void {
        if ($dryRun) {
            return;
        }

        $base = storage_path('app/public/imports');
        $productPath = "$base/$categoryFolder/$newFolderName";

        if (! is_dir($productPath)) {
            return;
        }

        $images = array_values(array_filter(
            scandir($productPath),
            fn ($f) => preg_match('/\.(jpe?g|png|webp)$/i', $f)
        ));
        sort($images);

        if (empty($images)) {
            return;
        }

        $firstImage = $images[0];
        $imagePath = "imports/$categoryFolder/$newFolderName/$firstImage";

        Product::updateOrCreate(
            [
                'category_slug' => $parsedCategory['category_slug'],
                'name' => $newFolderName,
            ],
            [
                'slug' => Str::slug($newFolderName),
                'folder' => $newFolderName,
                'brand' => $parsedCategory['brand'],
                'gender' => $parsedCategory['gender'],
                'section' => $parsedCategory['section'],
                'image' => $firstImage,
                'image_path' => $imagePath,
            ]
        );

        if (! $skipThumbnails) {
            foreach ($images as $img) {
                $thumbnailService->generateAll("imports/$categoryFolder/$newFolderName/$img");
            }
        }
    }

    /**
     * After splitting, refresh the original product's image fields.
     *
     * Files were removed from the original folder, so the stored image_path
     * might still be valid but we re-derive it to be safe.
     */
    protected function refreshOriginalProduct(
        array $parsedCategory,
        string $productFolder,
        string $categoryFolder,
        ThumbnailService $thumbnailService,
        bool $dryRun
    ): void {
        if ($dryRun) {
            return;
        }

        $product = Product::where('category_slug', $parsedCategory['category_slug'])
            ->where('folder', $productFolder)
            ->first();

        if (! $product) {
            return;
        }

        $base = storage_path('app/public/imports');
        $productPath = "$base/$categoryFolder/$productFolder";

        $images = array_values(array_filter(
            scandir($productPath),
            fn ($f) => preg_match('/\.(jpe?g|png|webp)$/i', $f)
        ));
        sort($images);

        if (empty($images)) {
            return;
        }

        $firstImage = $images[0];
        $imagePath = "imports/$categoryFolder/$productFolder/$firstImage";

        // Clear old thumbnail cache
        if ($product->image_path) {
            $thumbnailService->clearCache($product->image_path);
        }

        $product->update([
            'image' => $firstImage,
            'image_path' => $imagePath,
        ]);
    }

    /**
     * Parse import folder name into brand, section, gender, and category_slug.
     * Identical to ImportLV::parseFolderName().
     */
    protected function parseFolderName(string $folder): ?array
    {
        if (! preg_match('/^([a-z]+)-([a-z]+)-(women|men)$/i', $folder, $matches)) {
            return null;
        }

        $brandPrefix = strtolower($matches[1]);
        $section = strtolower($matches[2]);
        $gender = strtolower($matches[3]);

        $brand = $this->brandMap[$brandPrefix] ?? $brandPrefix;
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
