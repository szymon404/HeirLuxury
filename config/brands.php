<?php

// ABOUTME: Single source of truth for brand prefix ↔ canonical slug mapping.
// ABOUTME: Read via App\Support\BrandRegistry; do not duplicate this map elsewhere.

/**
 * Brand prefix → canonical brand slug.
 *
 * The "prefix" is the short token used in import folder names
 * ({prefix}-{section}-{gender}, e.g. `lv-bags-women`). The "slug" is the
 * full kebab-case brand identifier used in category_slug values
 * (`{slug}-{gender}-{section}`, e.g. `louis-vuitton-women-bags`) and in the
 * Product.brand column.
 *
 * When adding a new brand:
 * 1. Add an entry below.
 * 2. Run the test suite — BrandRegistryTest will pick up the new entry
 *    automatically; the importers and views read through BrandRegistry so
 *    they need no further changes.
 */
return [
    'map' => [
        // prefix       => slug
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
    ],
];
