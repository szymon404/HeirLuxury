<?php

// ABOUTME: Unit tests for the BrandRegistry — central source of truth for brand prefix ↔ slug mapping.
// ABOUTME: Replaces hardcoded $brandMap arrays previously duplicated across 4+ files.

namespace Tests\Unit;

use App\Support\BrandRegistry;
use Tests\TestCase;

/**
 * Tests the brand prefix ↔ slug registry.
 *
 * The registry reads from config/brands.php and exposes lookups in both
 * directions: forward (prefix → slug) used by importers parsing folder
 * names, and reverse (slug → prefix) used by views/controllers translating
 * a category_slug back to its on-disk import folder.
 *
 * To run:
 *   php artisan test --filter=BrandRegistryTest
 */
class BrandRegistryTest extends TestCase
{
    public function test_prefix_to_slug_resolves_known_prefix(): void
    {
        $this->assertSame('louis-vuitton', BrandRegistry::prefixToSlug('lv'));
        $this->assertSame('chanel', BrandRegistry::prefixToSlug('chanel'));
        $this->assertSame('philipp-plein', BrandRegistry::prefixToSlug('philippplein'));
    }

    public function test_prefix_to_slug_returns_null_for_unknown_prefix(): void
    {
        $this->assertNull(BrandRegistry::prefixToSlug('hermes'));
        $this->assertNull(BrandRegistry::prefixToSlug(''));
    }

    public function test_slug_to_prefix_resolves_known_slug(): void
    {
        $this->assertSame('lv', BrandRegistry::slugToPrefix('louis-vuitton'));
        $this->assertSame('chanel', BrandRegistry::slugToPrefix('chanel'));
        $this->assertSame('philippplein', BrandRegistry::slugToPrefix('philipp-plein'));
    }

    public function test_slug_to_prefix_returns_null_for_unknown_slug(): void
    {
        $this->assertNull(BrandRegistry::slugToPrefix('hermes'));
        $this->assertNull(BrandRegistry::slugToPrefix(''));
    }

    public function test_all_returns_full_prefix_to_slug_map(): void
    {
        $all = BrandRegistry::all();

        $this->assertIsArray($all);
        $this->assertArrayHasKey('lv', $all);
        $this->assertSame('louis-vuitton', $all['lv']);
        // Identity mappings still appear so iteration covers every brand.
        $this->assertArrayHasKey('chanel', $all);
    }
}
