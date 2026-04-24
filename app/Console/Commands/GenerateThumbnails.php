<?php

// ABOUTME: Artisan command to batch generate optimized WebP thumbnails for product images.
// ABOUTME: Supports --size, --folder, and --force flags for targeted or full regeneration.

namespace App\Console\Commands;

use App\Services\ThumbnailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Batch generate optimized WebP thumbnails for product images.
 *
 * This command processes all images in the storage/app/public/imports directory
 * and generates optimized thumbnails in three sizes (card, gallery, thumb).
 * Thumbnails are stored alongside originals with size suffix (e.g., image_card.webp).
 *
 * Use Cases:
 * - Initial setup: Generate thumbnails for all existing product images
 * - Maintenance: Regenerate thumbnails after changing size configurations
 * - Targeted: Process specific folders or sizes only
 *
 * Usage:
 *   php artisan thumbnails:generate                    # All folders, all sizes
 *   php artisan thumbnails:generate --folder=lv-bags-women  # Specific folder
 *   php artisan thumbnails:generate --size=card       # Only card size
 *   php artisan thumbnails:generate --force           # Regenerate existing
 *
 * Performance:
 * - Skips existing thumbnails unless --force is used
 * - Shows progress bar for each folder
 * - Reports generated/skipped/failed counts at completion
 *
 * @see \App\Services\ThumbnailService For thumbnail generation logic
 * @see \App\Console\Commands\ImportLV For thumbnail generation during import
 */
class GenerateThumbnails extends Command
{
    protected $signature = 'thumbnails:generate
                            {--size=all : Size to generate (card, gallery, thumb, or all)}
                            {--folder= : Specific import folder to process}
                            {--force : Regenerate existing thumbnails}';

    protected $description = 'Generate optimized thumbnails for product images';

    /**
     * Execute the thumbnail generation process.
     *
     * Process flow:
     * 1. Determine which folders to process (all or specific)
     * 2. For each folder, iterate through product directories
     * 3. For each image, generate requested thumbnail sizes
     * 4. Track and report success/skip/failure counts
     *
     * @param  ThumbnailService  $thumbnailService  Injected thumbnail service
     * @return int Command::SUCCESS or Command::FAILURE
     */
    public function handle(ThumbnailService $thumbnailService): int
    {
        $size = $this->option('size');
        $folder = $this->option('folder');
        $force = $this->option('force');

        $storage = Storage::disk('public');
        $basePath = 'imports';

        // Determine which folders to process (specific or all)
        if ($folder) {
            $folders = [$folder];
        } else {
            $folders = collect($storage->directories($basePath))
                ->map(fn ($path) => basename($path))
                ->filter()
                ->values()
                ->all();
        }

        if (empty($folders)) {
            $this->error('No import folders found.');

            return Command::FAILURE;
        }

        $this->info('Processing folders: '.implode(', ', $folders));
        $this->newLine();

        // Resolve which sizes to generate
        $sizes = $size === 'all' ? array_keys(ThumbnailService::SIZES) : [$size];
        $totalProcessed = 0;
        $totalSkipped = 0;
        $totalFailed = 0;

        foreach ($folders as $folderName) {
            $folderPath = "{$basePath}/{$folderName}";

            if (! $storage->exists($folderPath)) {
                $this->warn("Folder not found: {$folderPath}");

                continue;
            }

            $this->info("Processing: {$folderName}");

            // Get all product directories within this category folder
            $productDirs = $storage->directories($folderPath);

            $progressBar = $this->output->createProgressBar(count($productDirs));
            $progressBar->start();

            foreach ($productDirs as $productDir) {
                // Collect all image files (jpg, jpeg, png, webp)
                $files = collect($storage->allFiles($productDir))
                    ->filter(fn ($path) => preg_match('/\.(jpe?g|png|webp)$/i', $path))
                    ->values();

                foreach ($files as $imagePath) {
                    foreach ($sizes as $sizeKey) {
                        // Skip existing thumbnails unless --force is used
                        if (! $force && $thumbnailService->exists($imagePath, $sizeKey)) {
                            $totalSkipped++;

                            continue;
                        }

                        // Generate the thumbnail
                        $result = $thumbnailService->generate($imagePath, $sizeKey);

                        if ($result) {
                            $totalProcessed++;
                        } else {
                            $totalFailed++;
                        }
                    }
                }

                $progressBar->advance();

                // Free memory between product directories
                gc_collect_cycles();
            }

            $progressBar->finish();
            $this->newLine(2);
        }

        // Display summary table
        $this->info('Thumbnail generation complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Generated', $totalProcessed],
                ['Skipped (existing)', $totalSkipped],
                ['Failed', $totalFailed],
            ]
        );

        return Command::SUCCESS;
    }
}
