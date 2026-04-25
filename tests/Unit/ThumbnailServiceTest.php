<?php

namespace Tests\Unit;

use App\Services\ThumbnailService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Unit tests for ThumbnailService.
 *
 * These tests verify thumbnail path generation, caching behavior,
 * and URL resolution without requiring actual image files.
 *
 * To run these tests:
 *   php artisan test --filter=ThumbnailServiceTest
 *
 * Or run all tests:
 *   php artisan test
 */
class ThumbnailServiceTest extends TestCase
{
    protected ThumbnailService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Use array driver for tests to avoid database dependency
        config(['cache.default' => 'array']);
        $this->service = new ThumbnailService;
    }

    /**
     * Test that thumbnail paths are correctly generated from original paths.
     */
    public function test_get_thumbnail_path_generates_correct_path(): void
    {
        // Standard jpg file
        $result = $this->service->getThumbnailPath('imports/lv-bags-women/Product/0000.jpg', 'card');
        $this->assertEquals('thumbnails/card/lv-bags-women/Product/0000.webp', $result);

        // Different size
        $result = $this->service->getThumbnailPath('imports/lv-bags-women/Product/0000.jpg', 'gallery');
        $this->assertEquals('thumbnails/gallery/lv-bags-women/Product/0000.webp', $result);

        // Thumb size
        $result = $this->service->getThumbnailPath('imports/lv-bags-women/Product/0000.jpg', 'thumb');
        $this->assertEquals('thumbnails/thumb/lv-bags-women/Product/0000.webp', $result);
    }

    /**
     * Test that various image extensions are converted to .webp.
     */
    public function test_get_thumbnail_path_converts_various_extensions(): void
    {
        // JPEG variations
        $this->assertStringEndsWith('.webp', $this->service->getThumbnailPath('test/image.jpeg', 'card'));
        $this->assertStringEndsWith('.webp', $this->service->getThumbnailPath('test/image.JPG', 'card'));

        // PNG
        $this->assertStringEndsWith('.webp', $this->service->getThumbnailPath('test/image.png', 'card'));

        // Already webp
        $this->assertStringEndsWith('.webp', $this->service->getThumbnailPath('test/image.webp', 'card'));

        // GIF, BMP, TIFF (new extensions)
        $this->assertStringEndsWith('.webp', $this->service->getThumbnailPath('test/image.gif', 'card'));
        $this->assertStringEndsWith('.webp', $this->service->getThumbnailPath('test/image.bmp', 'card'));
        $this->assertStringEndsWith('.webp', $this->service->getThumbnailPath('test/image.tiff', 'card'));
    }

    /**
     * Test that files without recognized extensions get .webp appended.
     */
    public function test_get_thumbnail_path_appends_webp_for_unknown_extensions(): void
    {
        $result = $this->service->getThumbnailPath('test/image_no_ext', 'card');
        $this->assertEquals('thumbnails/card/test/image_no_ext.webp', $result);

        $result = $this->service->getThumbnailPath('test/image.unknown', 'card');
        $this->assertEquals('thumbnails/card/test/image.unknown.webp', $result);
    }

    /**
     * Test that imports/ prefix is stripped from paths.
     */
    public function test_get_thumbnail_path_strips_imports_prefix(): void
    {
        $result = $this->service->getThumbnailPath('imports/folder/image.jpg', 'card');
        $this->assertStringNotContainsString('imports/', $result);
        $this->assertEquals('thumbnails/card/folder/image.webp', $result);
    }

    /**
     * Test that paths without imports/ prefix work correctly.
     */
    public function test_get_thumbnail_path_works_without_imports_prefix(): void
    {
        $result = $this->service->getThumbnailPath('other/folder/image.jpg', 'card');
        $this->assertEquals('thumbnails/card/other/folder/image.webp', $result);
    }

    /**
     * Test that getUrl returns null for invalid size.
     */
    public function test_get_url_returns_null_for_invalid_size(): void
    {
        $result = $this->service->getUrl('test/image.jpg', 'invalid_size');
        $this->assertNull($result);
    }

    /**
     * Test that SIZES constant contains expected keys.
     */
    public function test_sizes_constant_has_required_keys(): void
    {
        $this->assertArrayHasKey('card', ThumbnailService::SIZES);
        $this->assertArrayHasKey('gallery', ThumbnailService::SIZES);
        $this->assertArrayHasKey('thumb', ThumbnailService::SIZES);
    }

    /**
     * Test that each size configuration has required properties.
     */
    public function test_size_configurations_have_required_properties(): void
    {
        foreach (ThumbnailService::SIZES as $size => $config) {
            $this->assertArrayHasKey('width', $config, "Size '{$size}' missing 'width'");
            $this->assertArrayHasKey('height', $config, "Size '{$size}' missing 'height'");
            $this->assertArrayHasKey('quality', $config, "Size '{$size}' missing 'quality'");

            $this->assertIsInt($config['width'], "Size '{$size}' width should be int");
            $this->assertIsInt($config['height'], "Size '{$size}' height should be int");
            $this->assertIsInt($config['quality'], "Size '{$size}' quality should be int");

            $this->assertGreaterThan(0, $config['width']);
            $this->assertGreaterThan(0, $config['height']);
            $this->assertGreaterThanOrEqual(0, $config['quality']);
            $this->assertLessThanOrEqual(100, $config['quality']);
        }
    }

    /**
     * Test that clearCache clears the correct cache keys.
     */
    public function test_clear_cache_removes_url_cache_keys(): void
    {
        $path = 'test/image.jpg';

        // Pre-populate cache
        foreach (array_keys(ThumbnailService::SIZES) as $size) {
            $cacheKey = "thumbnail_url:{$size}:".md5($path);
            Cache::put($cacheKey, 'http://example.com/test.webp', 3600);
        }

        // Verify cache is set
        foreach (array_keys(ThumbnailService::SIZES) as $size) {
            $cacheKey = "thumbnail_url:{$size}:".md5($path);
            $this->assertTrue(Cache::has($cacheKey), "Cache should exist for size '{$size}'");
        }

        // Clear cache
        $this->service->clearCache($path);

        // Verify cache is cleared
        foreach (array_keys(ThumbnailService::SIZES) as $size) {
            $cacheKey = "thumbnail_url:{$size}:".md5($path);
            $this->assertFalse(Cache::has($cacheKey), "Cache should be cleared for size '{$size}'");
        }
    }

    /**
     * Test exists() returns false for non-existent thumbnails.
     */
    public function test_exists_returns_false_for_nonexistent_thumbnail(): void
    {
        Storage::fake('public');

        $result = $this->service->exists('nonexistent/image.jpg', 'card');
        $this->assertFalse($result);
    }

    /**
     * Test generate() returns false for non-existent original.
     */
    public function test_generate_returns_false_for_nonexistent_original(): void
    {
        Storage::fake('public');

        $result = $this->service->generate('nonexistent/image.jpg', 'card');
        $this->assertFalse($result);
    }

    /**
     * Test generate() returns false for invalid size.
     */
    public function test_generate_returns_false_for_invalid_size(): void
    {
        $result = $this->service->generate('test/image.jpg', 'invalid');
        $this->assertFalse($result);
    }

    /**
     * Test generateAll() returns array with all sizes as keys.
     */
    public function test_generate_all_returns_results_for_all_sizes(): void
    {
        Storage::fake('public');

        $results = $this->service->generateAll('nonexistent/image.jpg');

        $this->assertIsArray($results);
        $this->assertArrayHasKey('card', $results);
        $this->assertArrayHasKey('gallery', $results);
        $this->assertArrayHasKey('thumb', $results);

        // All should be false since original doesn't exist
        foreach ($results as $size => $success) {
            $this->assertFalse($success, "Size '{$size}' should fail for nonexistent image");
        }
    }

    // ==================== card_2x + srcset ====================

    /**
     * Retina/HiDPI variant must exist in SIZES so generateAll/getSrcset
     * can pair it with the 1x card thumbnail.
     */
    public function test_card_2x_size_is_registered(): void
    {
        $this->assertArrayHasKey('card_2x', ThumbnailService::SIZES);
        $this->assertSame(800, ThumbnailService::SIZES['card_2x']['width']);
        $this->assertSame(600, ThumbnailService::SIZES['card_2x']['height']);
    }

    /**
     * getSrcset() returns null for unknown sizes (mirrors getUrl behaviour).
     */
    public function test_get_srcset_returns_null_for_invalid_size(): void
    {
        Storage::fake('public');
        $this->assertNull($this->service->getSrcset('any/image.jpg', 'invalid'));
    }

    /**
     * No 2x companion size → no srcset is meaningful, return null.
     * The blade falls back to plain src in that case.
     */
    public function test_get_srcset_returns_null_when_no_2x_companion(): void
    {
        Storage::fake('public');
        // 'thumb' has no 'thumb_2x' counterpart by design — small images don't need retina
        $this->assertNull($this->service->getSrcset('any/image.jpg', 'thumb'));
    }

    /**
     * When neither thumbnail can be resolved (no original on disk either),
     * srcset is null so the blade omits the attribute entirely.
     */
    public function test_get_srcset_returns_null_when_thumbnails_cannot_be_resolved(): void
    {
        Storage::fake('public');
        $this->assertNull($this->service->getSrcset('imports/missing/Product/0000.jpg', 'card'));
    }

    /**
     * Happy path: both thumbnails exist on disk → srcset string pairs them
     * with the appropriate density descriptors.
     */
    public function test_get_srcset_pairs_1x_and_2x_when_both_thumbnails_exist(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');

        // Pre-place both thumbnails so getUrl() does not need to generate.
        $disk->put('thumbnails/card/folder/Product/0000.webp', 'fake-1x');
        $disk->put('thumbnails/card_2x/folder/Product/0000.webp', 'fake-2x');

        $srcset = $this->service->getSrcset('imports/folder/Product/0000.jpg', 'card');

        $this->assertNotNull($srcset);
        $this->assertStringContainsString('1x', $srcset);
        $this->assertStringContainsString('2x', $srcset);
        $this->assertStringContainsString('thumbnails/card/', $srcset);
        $this->assertStringContainsString('thumbnails/card_2x/', $srcset);
    }
}
