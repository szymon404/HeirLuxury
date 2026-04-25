<?php

// ABOUTME: Static facade over config('brands.map') exposing forward + reverse brand lookups.
// ABOUTME: Replaces $brandMap arrays previously copy-pasted across importers, views, and controllers.

namespace App\Support;

/**
 * Central lookup for the brand prefix ↔ slug mapping.
 *
 * Reads from `config/brands.php`. Two directions are supported:
 *
 * - prefixToSlug('lv') → 'louis-vuitton'
 *   Used when parsing import folder names ({prefix}-{section}-{gender}).
 *
 * - slugToPrefix('louis-vuitton') → 'lv'
 *   Used when translating a category_slug back to its on-disk import folder.
 *
 * Both methods return null for unknown keys; callers decide whether to fall
 * back (e.g. ImportLV passes the unknown prefix through verbatim so new
 * brands still create a usable Product row before being added to config).
 */
class BrandRegistry
{
    /**
     * Look up the canonical brand slug for a folder prefix.
     *
     * @param  string  $prefix  e.g. 'lv'
     * @return string|null Canonical slug (e.g. 'louis-vuitton') or null when unknown.
     */
    public static function prefixToSlug(string $prefix): ?string
    {
        return self::all()[$prefix] ?? null;
    }

    /**
     * Look up the folder prefix for a canonical brand slug.
     *
     * @param  string  $slug  e.g. 'louis-vuitton'
     * @return string|null Folder prefix (e.g. 'lv') or null when unknown.
     */
    public static function slugToPrefix(string $slug): ?string
    {
        $prefix = array_search($slug, self::all(), true);

        return $prefix === false ? null : $prefix;
    }

    /**
     * Return the entire prefix → slug map for callers that need to iterate.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return config('brands.map', []);
    }
}
