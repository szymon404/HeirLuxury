<?php

// ABOUTME: Feature tests for the admin bulk import UI.
// ABOUTME: Covers form display, import execution, and access control.

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::factory()->create();
        $this->admin->is_admin = true;
        $this->admin->save();

        // Clean test import directories to isolate state between tests
        $this->cleanTestImportFolders();
    }

    protected function tearDown(): void
    {
        $this->cleanTestImportFolders();
        parent::tearDown();
    }

    /**
     * Remove test import folders to prevent cross-test contamination.
     */
    protected function cleanTestImportFolders(): void
    {
        Storage::disk('public')->deleteDirectory('imports/lv-bags-women');
    }

    // ==================== Access Control ====================

    public function test_import_form_accessible_by_admin(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.import.index'));

        $response->assertOk();
    }

    public function test_import_form_not_accessible_by_guest(): void
    {
        $response = $this->get(route('admin.import.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_import_form_not_accessible_by_regular_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.import.index'));

        $response->assertForbidden();
    }

    // ==================== Import Form ====================

    public function test_import_form_shows_current_product_count(): void
    {
        Product::factory()->count(15)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.import.index'));

        $response->assertOk();
        $response->assertSee('15');
    }

    public function test_import_form_shows_import_options(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.import.index'));

        $response->assertOk();
        $response->assertSee('fresh');
        $response->assertSee('skip_thumbnails');
    }

    // ==================== Running Import ====================

    public function test_import_run_requires_admin(): void
    {
        $response = $this->post(route('admin.import.run'));

        $response->assertRedirect(route('login'));
    }

    public function test_import_run_redirects_with_results(): void
    {
        // Create a minimal import folder structure
        Storage::disk('public')->makeDirectory('imports/lv-bags-women/Test Product');
        Storage::disk('public')->put('imports/lv-bags-women/Test Product/0000.jpg', 'fake-image-data');

        $response = $this->actingAs($this->admin)
            ->post(route('admin.import.run'), [
                'skip_thumbnails' => '1',
                'folder' => 'lv-bags-women',
            ]);

        $response->assertRedirect(route('admin.import.index'));
        $response->assertSessionHas('status');
    }

    public function test_import_fresh_clears_existing_products(): void
    {
        Product::factory()->count(5)->create();

        // Create a minimal import folder structure
        Storage::disk('public')->makeDirectory('imports/lv-bags-women/Fresh Product');
        Storage::disk('public')->put('imports/lv-bags-women/Fresh Product/0000.jpg', 'fake-image-data');

        $this->actingAs($this->admin)
            ->post(route('admin.import.run'), [
                'fresh' => '1',
                'skip_thumbnails' => '1',
                'folder' => 'lv-bags-women',
            ]);

        // The 5 factory products should be gone, replaced by imported one
        $this->assertEquals(1, Product::count());
        $this->assertDatabaseHas('products', ['name' => 'Fresh Product']);
    }

    public function test_import_without_fresh_preserves_existing(): void
    {
        Product::factory()->create(['name' => 'Existing', 'category_slug' => 'keep-me']);

        // Create a minimal import folder
        Storage::disk('public')->makeDirectory('imports/lv-bags-women/Import Product');
        Storage::disk('public')->put('imports/lv-bags-women/Import Product/0000.jpg', 'fake-image-data');

        $this->actingAs($this->admin)
            ->post(route('admin.import.run'), [
                'skip_thumbnails' => '1',
                'folder' => 'lv-bags-women',
            ]);

        $this->assertDatabaseHas('products', ['name' => 'Existing']);
        $this->assertDatabaseHas('products', ['name' => 'Import Product']);
    }

    public function test_import_sidebar_link_exists(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Import');
        $response->assertSee(route('admin.import.index'));
    }
}
