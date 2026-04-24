<?php

// ABOUTME: Feature tests for `php artisan products:prune-missing` — removes DB rows whose folder is gone.
// ABOUTME: Covers default deletion, --dry-run, --brand filter, and chunked iteration.

namespace Tests\Feature\Console;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests for the products:prune-missing artisan command.
 *
 * The command walks every Product row, checks whether its image_path exists
 * on the public disk, and deletes rows whose underlying folder is gone.
 * --dry-run reports without deleting; --brand filters to one brand; --chunk
 * controls batch size for memory bounded iteration over large catalogs.
 *
 * To run:
 *   php artisan test --filter=PruneStaleProductsTest
 */
class PruneStaleProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanTestFolders();
    }

    protected function tearDown(): void
    {
        $this->cleanTestFolders();
        parent::tearDown();
    }

    /**
     * Wipe scratch folders both before and after each test so disk state
     * does not leak between cases.
     */
    protected function cleanTestFolders(): void
    {
        Storage::disk('public')->deleteDirectory('imports/lv-bags-women');
        Storage::disk('public')->deleteDirectory('imports/celine-bags-women');
    }

    /**
     * Helper: write a fake image file at `imports/{base}/{folder}/0000.jpg`
     * and return the relative image_path (matching ImportLV's storage layout).
     */
    protected function placeImage(string $base, string $folder): string
    {
        $relative = "imports/{$base}/{$folder}/0000.jpg";
        Storage::disk('public')->put($relative, 'fake-image-data');

        return $relative;
    }

    public function test_deletes_product_whose_image_is_missing(): void
    {
        Product::factory()->create([
            'name' => 'Stale Product',
            'category_slug' => 'louis-vuitton-women-bags',
            'image_path' => 'imports/lv-bags-women/Stale Product/0000.jpg',
        ]);

        $this->artisan('products:prune-missing')
            ->assertSuccessful();

        $this->assertDatabaseMissing('products', ['name' => 'Stale Product']);
    }

    public function test_keeps_product_whose_image_exists(): void
    {
        $imagePath = $this->placeImage('lv-bags-women', 'Live Product');

        Product::factory()->create([
            'name' => 'Live Product',
            'category_slug' => 'louis-vuitton-women-bags',
            'image_path' => $imagePath,
        ]);

        $this->artisan('products:prune-missing')
            ->assertSuccessful();

        $this->assertDatabaseHas('products', ['name' => 'Live Product']);
    }

    public function test_dry_run_reports_but_does_not_delete(): void
    {
        Product::factory()->create([
            'name' => 'Stale Product',
            'category_slug' => 'louis-vuitton-women-bags',
            'image_path' => 'imports/lv-bags-women/Stale Product/0000.jpg',
        ]);

        $this->artisan('products:prune-missing', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('products', ['name' => 'Stale Product']);
    }

    public function test_brand_filter_only_prunes_matching_brand(): void
    {
        Product::factory()->create([
            'name' => 'LV Stale',
            'brand' => 'louis-vuitton',
            'category_slug' => 'louis-vuitton-women-bags',
            'image_path' => 'imports/lv-bags-women/LV Stale/0000.jpg',
        ]);

        Product::factory()->create([
            'name' => 'Celine Stale',
            'brand' => 'celine',
            'category_slug' => 'celine-women-bags',
            'image_path' => 'imports/celine-bags-women/Celine Stale/0000.jpg',
        ]);

        $this->artisan('products:prune-missing', ['--brand' => 'louis-vuitton'])
            ->assertSuccessful();

        $this->assertDatabaseMissing('products', ['name' => 'LV Stale']);
        $this->assertDatabaseHas('products', ['name' => 'Celine Stale']);
    }

    public function test_chunked_iteration_handles_many_rows(): void
    {
        // Three stale, two live — chunk=2 forces multiple passes.
        for ($i = 1; $i <= 3; $i++) {
            Product::factory()->create([
                'name' => "Stale $i",
                'category_slug' => 'louis-vuitton-women-bags',
                'image_path' => "imports/lv-bags-women/Stale $i/0000.jpg",
            ]);
        }
        for ($i = 1; $i <= 2; $i++) {
            $imagePath = $this->placeImage('lv-bags-women', "Live $i");
            Product::factory()->create([
                'name' => "Live $i",
                'category_slug' => 'louis-vuitton-women-bags',
                'image_path' => $imagePath,
            ]);
        }

        $this->artisan('products:prune-missing', ['--chunk' => 2])
            ->assertSuccessful();

        $this->assertSame(2, Product::count());
        $this->assertDatabaseHas('products', ['name' => 'Live 1']);
        $this->assertDatabaseHas('products', ['name' => 'Live 2']);
    }

    public function test_reports_deleted_count_in_output(): void
    {
        Product::factory()->create([
            'name' => 'Stale Product',
            'category_slug' => 'louis-vuitton-women-bags',
            'image_path' => 'imports/lv-bags-women/Stale Product/0000.jpg',
        ]);

        $this->artisan('products:prune-missing')
            ->expectsOutputToContain('1')
            ->assertSuccessful();
    }
}
