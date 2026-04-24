<?php

// ABOUTME: Artisan command to delete Product rows whose image folder is gone from disk.
// ABOUTME: Supports --dry-run, --brand=X scoping, and --chunk=N for memory-safe iteration.

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Prune Product rows whose underlying image is missing on disk.
 *
 * After re-scraping or manually pruning the imports/ tree, the database can
 * end up with orphaned product rows pointing at files that no longer exist.
 * This command walks the catalog (chunked, memory-bounded), checks each
 * product's image_path against the public disk, and deletes rows whose
 * image file is gone.
 *
 * Usage:
 *   php artisan products:prune-missing                # delete stale rows
 *   php artisan products:prune-missing --dry-run      # report only
 *   php artisan products:prune-missing --brand=louis-vuitton
 *   php artisan products:prune-missing --chunk=200    # smaller batches
 *
 * Rows with a null image_path are treated as stale (they cannot render
 * anyway). Brand matches the `brand` column verbatim — pass the full slug
 * (e.g. `louis-vuitton`), not the short prefix.
 */
class PruneStaleProducts extends Command
{
    protected $signature = 'products:prune-missing
                            {--dry-run : Report what would be deleted without touching the database}
                            {--brand= : Restrict pruning to a single brand (matches Product.brand verbatim)}
                            {--chunk=500 : Rows to load per batch; lower for tighter memory bounds}';

    protected $description = 'Delete Product rows whose image folder is missing from the public disk';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $brand = $this->option('brand');
        $chunk = max(1, (int) $this->option('chunk'));

        $disk = Storage::disk('public');

        $query = Product::query();
        if ($brand !== null) {
            $query->where('brand', $brand);
        }

        $deleted = 0;
        $kept = 0;

        // chunkById is delete-safe: it pages by `id > last_id`, so removing
        // rows mid-iteration does not skip records the way offset paging does.
        $query->chunkById($chunk, function ($products) use ($disk, $dryRun, &$deleted, &$kept) {
            foreach ($products as $product) {
                if ($this->isStale($product, $disk)) {
                    $this->line("  ✗ {$product->category_slug}/{$product->name}");
                    if (! $dryRun) {
                        $product->delete();
                    }
                    $deleted++;
                } else {
                    $kept++;
                }
            }
        });

        $verb = $dryRun ? 'Would delete' : 'Deleted';
        $this->info("$verb $deleted stale products. Kept $kept.");

        return Command::SUCCESS;
    }

    /**
     * A product is stale when its image_path is empty or its file is gone.
     */
    protected function isStale(Product $product, $disk): bool
    {
        if (empty($product->image_path)) {
            return true;
        }

        return ! $disk->exists($product->image_path);
    }
}
