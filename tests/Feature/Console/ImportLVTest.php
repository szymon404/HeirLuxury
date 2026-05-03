<?php

// ABOUTME: Feature tests for `php artisan import:lv` — covers folder scanning + idempotent upserts.
// ABOUTME: Specifically guards the case-insensitive name collapse and the (category_slug, slug) unique index.

namespace Tests\Feature\Console;

use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests for the import:lv artisan command.
 *
 * The importer walks storage/app/public/imports for {brand}-{section}-{gender}
 * category folders, then for each product subfolder writes a Product row keyed
 * by (category_slug, slug). The slug is derived from the folder name via
 * Str::slug, which lowercases — that's the canonical case-insensitive form
 * we use to collapse case-different scraper duplicates into one row.
 *
 * To run:
 *   php artisan test --filter=ImportLVTest
 */
class ImportLVTest extends TestCase
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
        Storage::disk('public')->deleteDirectory('imports/gucci-bags-women');
        Storage::disk('public')->deleteDirectory('imports/lv-shoes-men');
        Storage::disk('public')->deleteDirectory('imports/lv-belts-unisex');
    }

    /**
     * Plant a product subfolder containing one image so the importer
     * sees it as a real product.
     */
    protected function plantProduct(string $category, string $product): void
    {
        $disk = Storage::disk('public');
        $disk->put("imports/{$category}/{$product}/0000.jpg", 'fake-img');
    }

    // ==================== Case-insensitive collapse ====================

    /**
     * Two case-different folder names that resolve to the same slug
     * MUST collapse into a single Product row, not two. Previously the
     * upsert keyed on (category_slug, name) which is case-sensitive, so
     * 'Gucci 0015' and 'GUCCI 0015' produced duplicate rows.
     */
    public function test_case_different_folders_collapse_to_one_product(): void
    {
        $this->plantProduct('gucci-bags-women', 'Gucci 0015');
        $this->plantProduct('gucci-bags-women', 'GUCCI 0015');

        $this->artisan('import:lv', ['--folder' => 'gucci-bags-women', '--skip-thumbnails' => true])
            ->assertExitCode(0);

        $this->assertSame(
            1,
            Product::where('category_slug', 'gucci-women-bags')
                ->where('slug', 'gucci-0015')
                ->count(),
            'Case-different folders must collapse to one Product row'
        );
    }

    /**
     * Two genuinely-different products in the same category produce
     * two distinct Product rows. Sanity check that the case collapse
     * does not over-merge.
     */
    public function test_distinct_products_remain_distinct(): void
    {
        $this->plantProduct('gucci-bags-women', 'Gucci 0015');
        $this->plantProduct('gucci-bags-women', 'Gucci 0016');

        $this->artisan('import:lv', ['--folder' => 'gucci-bags-women', '--skip-thumbnails' => true])
            ->assertExitCode(0);

        $this->assertSame(
            2,
            Product::where('category_slug', 'gucci-women-bags')->count(),
            'Different products must stay as separate rows'
        );
    }

    /**
     * Unisex folders write two rows with different category_slugs but
     * the SAME slug. The composite uniqueness on (category_slug, slug)
     * still holds because the category differs.
     */
    public function test_unisex_folder_creates_two_rows_with_different_categories(): void
    {
        $this->plantProduct('lv-belts-unisex', 'LV 0001');

        $this->artisan('import:lv', ['--folder' => 'lv-belts-unisex', '--skip-thumbnails' => true])
            ->assertExitCode(0);

        $this->assertSame(2, Product::where('slug', 'lv-0001')->count());
        $this->assertSame(1, Product::where('category_slug', 'louis-vuitton-men-belts')->count());
        $this->assertSame(1, Product::where('category_slug', 'louis-vuitton-women-belts')->count());
    }

    // ==================== DB-level unique index ====================

    /**
     * The (category_slug, slug) unique index MUST exist as a hard DB
     * constraint, not just an importer convention. Future buggy code
     * that bypasses the importer should still hit a constraint violation.
     */
    public function test_database_enforces_unique_category_slug_slug(): void
    {
        Product::create([
            'name' => 'First',
            'slug' => 'first',
            'category_slug' => 'lv-men-shoes',
            'brand' => 'louis-vuitton',
            'gender' => 'men',
            'section' => 'shoes',
            'image_path' => 'imports/lv-shoes-men/First/0000.jpg',
        ]);

        $this->expectException(QueryException::class);

        Product::create([
            'name' => 'Second (different name, same slug)',
            'slug' => 'first',
            'category_slug' => 'lv-men-shoes',
            'brand' => 'louis-vuitton',
            'gender' => 'men',
            'section' => 'shoes',
            'image_path' => 'imports/lv-shoes-men/Second/0000.jpg',
        ]);
    }

    /**
     * Same slug in DIFFERENT categories is allowed — that's the whole
     * point of the COMPOSITE index. e.g. 'celine-0001' can legitimately
     * exist in both celine-women-bags and celine-women-clothes.
     */
    public function test_same_slug_in_different_categories_is_allowed(): void
    {
        Product::create([
            'name' => 'Celine 0001',
            'slug' => 'celine-0001',
            'category_slug' => 'celine-women-bags',
            'brand' => 'celine',
            'gender' => 'women',
            'section' => 'bags',
            'image_path' => 'imports/celine-bags-women/Celine 0001/0000.jpg',
        ]);

        Product::create([
            'name' => 'Celine 0001',
            'slug' => 'celine-0001',
            'category_slug' => 'celine-women-clothes',
            'brand' => 'celine',
            'gender' => 'women',
            'section' => 'clothes',
            'image_path' => 'imports/celine-clothes-women/Celine 0001/0000.jpg',
        ]);

        $this->assertSame(2, Product::where('slug', 'celine-0001')->count());
    }
}
