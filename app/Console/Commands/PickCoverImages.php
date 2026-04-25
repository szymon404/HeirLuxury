<?php

// ABOUTME: Artisan command that auto-selects the best product overview image for catalog thumbnails.
// ABOUTME: Uses border brightness analysis to distinguish overview shots from close-up detail shots.

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ThumbnailService;
use App\Support\BrandRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Automatically pick the best "cover" image for each product.
 *
 * Yupoo album scrapes don't always put the product overview shot first.
 * Some products end up with close-up detail shots (hardware, labels, stitching)
 * as their primary thumbnail. This command analyzes all images in each product
 * folder and picks the one most likely to be a full product overview.
 *
 * Scoring approach: Overview shots have visible studio background around the
 * product (light/neutral border pixels), while close-ups fill the entire frame.
 * We sample border pixels and score by average brightness — higher = more
 * background visible = better overview shot.
 *
 * Usage:
 *   php artisan images:pick-cover --dry-run                          # Preview changes
 *   php artisan images:pick-cover --category=louis-vuitton-women-bags # One category
 *   php artisan images:pick-cover                                     # All products
 *   php artisan images:pick-cover --threshold=140                     # Adjust sensitivity
 */
class PickCoverImages extends Command
{
    protected $signature = 'images:pick-cover
                            {--category= : Process only this category slug}
                            {--dry-run : Show proposed changes without updating}
                            {--threshold=140 : Min border brightness to qualify as overview (0-255)}
                            {--force : Re-analyze products whose current image already scores well}';

    protected $description = 'Auto-select the best product overview image for catalog thumbnails';

    public function handle(ThumbnailService $thumbnailService): int
    {
        $categorySlug = $this->option('category');
        $dryRun = $this->option('dry-run');
        $threshold = (int) $this->option('threshold');
        $force = $this->option('force');

        $storage = Storage::disk('public');

        // Build product query
        $query = Product::whereNotNull('folder')->where('folder', '!=', '');
        if ($categorySlug) {
            $query->where('category_slug', $categorySlug);
        }

        $totalProducts = $query->count();

        if ($totalProducts === 0) {
            $this->error('No products found.');

            return Command::FAILURE;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Analyzing {$totalProducts} products...");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($totalProducts);
        $progressBar->start();

        $changed = 0;
        $skipped = 0;
        $errors = 0;
        $alreadyGood = 0;
        $changes = [];

        $query->chunk(100, function ($products) use (
            $storage, $thumbnailService, $dryRun, $threshold, $force,
            &$changed, &$skipped, &$errors, &$alreadyGood, &$changes, $progressBar
        ) {
            foreach ($products as $product) {
                $progressBar->advance();

                // Resolve the import folder path
                $folderPath = $this->resolveImportFolder($product, $storage);
                if (! $folderPath || ! $storage->exists($folderPath)) {
                    $errors++;

                    continue;
                }

                // List all image files in the folder
                $images = collect($storage->files($folderPath))
                    ->filter(fn ($path) => preg_match('/\.(jpe?g|png|webp)$/i', $path))
                    ->values()
                    ->all();

                if (count($images) < 2) {
                    $skipped++;

                    continue;
                }

                // Score each image by border brightness
                $scores = [];
                foreach ($images as $imagePath) {
                    $score = $this->scoreBorderBrightness($storage->path($imagePath));
                    if ($score !== null) {
                        $scores[$imagePath] = $score;
                    }
                }

                if (empty($scores)) {
                    $errors++;

                    continue;
                }

                // Check if current image already scores well
                $currentPath = $product->image_path;
                $currentScore = $scores[$currentPath] ?? $scores[$folderPath.'/'.$product->image] ?? 0;

                if (! $force && $currentScore >= $threshold) {
                    $alreadyGood++;

                    continue;
                }

                // Pick the image with the highest border brightness
                arsort($scores);
                $bestPath = array_key_first($scores);
                $bestScore = $scores[$bestPath];
                $bestFilename = basename($bestPath);

                // Skip if the best image is already the current one
                if ($bestFilename === $product->image) {
                    $alreadyGood++;

                    continue;
                }

                // Skip if the best score is still below threshold (all images are close-ups)
                if ($bestScore < $threshold) {
                    $skipped++;

                    continue;
                }

                $changed++;
                $changes[] = [
                    $product->name,
                    $product->category_slug,
                    $product->image." ({$this->formatScore($currentScore)})",
                    $bestFilename." ({$this->formatScore($bestScore)})",
                ];

                if (! $dryRun) {
                    $product->update([
                        'image' => $bestFilename,
                        'image_path' => $bestPath,
                    ]);

                    // Clear thumbnail cache so new image gets generated
                    $thumbnailService->clearCache($currentPath);
                }
            }

            // Free memory between chunks
            gc_collect_cycles();
        });

        $progressBar->finish();
        $this->newLine(2);

        // Show changes table
        if (! empty($changes)) {
            $this->info(($dryRun ? 'Proposed changes:' : 'Applied changes:'));
            $this->table(
                ['Product', 'Category', 'Old Image (score)', 'New Image (score)'],
                array_slice($changes, 0, 50) // Show first 50
            );

            if (count($changes) > 50) {
                $this->info('... and '.(count($changes) - 50).' more.');
            }
        }

        // Summary
        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] ' : '').'Analysis complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total products', $products->count()],
                ['Changed', $changed],
                ['Already good', $alreadyGood],
                ['Skipped (single image or below threshold)', $skipped],
                ['Errors (missing folder)', $errors],
            ]
        );

        if ($dryRun && $changed > 0) {
            $this->warn("Run without --dry-run to apply {$changed} changes.");
        }

        return Command::SUCCESS;
    }

    /**
     * Score an image by the average brightness of its border pixels.
     *
     * Overview/product shots have visible studio background around the edges
     * (light, neutral pixels). Close-ups fill the frame completely with product
     * material (darker, saturated pixels). Higher score = more likely overview.
     *
     * Loads at reduced resolution (100x75) for fast processing.
     */
    protected function scoreBorderBrightness(string $fullPath): ?float
    {
        try {
            $image = Image::read($fullPath);

            // Resize to small dimensions for fast pixel sampling
            $w = 100;
            $h = 75;
            $image->resize($w, $h);

            $borderDepth = 8; // Sample outer ~10% of image
            $totalBrightness = 0;
            $pixelCount = 0;

            // Sample top and bottom rows
            for ($x = 0; $x < $w; $x++) {
                for ($d = 0; $d < $borderDepth; $d++) {
                    $color = $image->pickColor($x, $d);
                    $totalBrightness += ($color->red()->toInt() + $color->green()->toInt() + $color->blue()->toInt()) / 3;
                    $pixelCount++;

                    $color = $image->pickColor($x, $h - 1 - $d);
                    $totalBrightness += ($color->red()->toInt() + $color->green()->toInt() + $color->blue()->toInt()) / 3;
                    $pixelCount++;
                }
            }

            // Sample left and right columns
            for ($y = $borderDepth; $y < $h - $borderDepth; $y++) {
                for ($d = 0; $d < $borderDepth; $d++) {
                    $color = $image->pickColor($d, $y);
                    $totalBrightness += ($color->red()->toInt() + $color->green()->toInt() + $color->blue()->toInt()) / 3;
                    $pixelCount++;

                    $color = $image->pickColor($w - 1 - $d, $y);
                    $totalBrightness += ($color->red()->toInt() + $color->green()->toInt() + $color->blue()->toInt()) / 3;
                    $pixelCount++;
                }
            }

            if ($pixelCount === 0) {
                return null;
            }

            return $totalBrightness / $pixelCount;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve the import folder path for a product.
     *
     * Reconstructs the storage path from the product's category_slug using
     * the same brand-prefix mapping as the product card component.
     */
    protected function resolveImportFolder(Product $product, $storage): ?string
    {
        $categorySlug = $product->category_slug;

        // Iterate the canonical prefix → slug map (config/brands.php).
        foreach (BrandRegistry::all() as $prefix => $brand) {
            if (str_starts_with($categorySlug, "{$brand}-")) {
                $rest = substr($categorySlug, strlen("{$brand}-"));
                if (preg_match('/^(women|men)-(.+)$/', $rest, $matches)) {
                    $gender = $matches[1];
                    $section = $matches[2];
                    $baseFolder = "{$prefix}-{$section}-{$gender}";

                    return "imports/{$baseFolder}/{$product->folder}";
                }
            }
        }

        return null;
    }

    protected function formatScore(float $score): string
    {
        return round($score).'/255';
    }
}
