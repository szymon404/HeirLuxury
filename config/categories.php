<?php

/**
 * ABOUTME: Legacy catalog taxonomy kept as fallback reference.
 * ABOUTME: The database (categories table) is the primary source of truth.
 *
 * DEPRECATED: Navigation reads from the categories table via
 * Category::getNavigationData(). This file is used as a fallback when the
 * database has no categories seeded. Run `php artisan db:seed --class=CategorySeeder`
 * to populate the database from this config.
 */

return [

    // ========================= WOMEN =========================
    'women' => [

        // ---- Bags ----
        'Bags' => [
            ['name' => 'Chanel Bags',          'route' => 'catalog.category', 'params' => ['category' => 'chanel-women-bags']],
            ['name' => 'Dior Bags',            'route' => 'catalog.category', 'params' => ['category' => 'dior-women-bags']],
            ['name' => 'Givenchy Bags',        'route' => 'catalog.category', 'params' => ['category' => 'givenchy-women-bags']],
            ['name' => 'Gucci Bags',           'route' => 'catalog.category', 'params' => ['category' => 'gucci-women-bags']],
            ['name' => 'Louis Vuitton Bags',   'route' => 'catalog.category', 'params' => ['category' => 'louis-vuitton-women-bags']],
            ['name' => 'Versace Bags',         'route' => 'catalog.category', 'params' => ['category' => 'versace-women-bags']],
        ],

        // ---- Shoes ----
        'Shoes' => [
            ['name' => 'Amiri Women Shoes',            'route' => 'catalog.category', 'params' => ['category' => 'amiri-women-shoes']],
            ['name' => 'Chanel Women Shoes',           'route' => 'catalog.category', 'params' => ['category' => 'chanel-women-shoes']],
            ['name' => 'Dior Women Shoes',             'route' => 'catalog.category', 'params' => ['category' => 'dior-women-shoes']],
            ['name' => 'Givenchy Women Shoes',         'route' => 'catalog.category', 'params' => ['category' => 'givenchy-women-shoes']],
            ['name' => 'Gucci Women Shoes',            'route' => 'catalog.category', 'params' => ['category' => 'gucci-women-shoes']],
            ['name' => 'Louis Vuitton Women Shoes',    'route' => 'catalog.category', 'params' => ['category' => 'louis-vuitton-women-shoes']],
            ['name' => 'McQueen Women Shoes',          'route' => 'catalog.category', 'params' => ['category' => 'mcqueen-women-shoes']],
            ['name' => 'Off-White Women Shoes',        'route' => 'catalog.category', 'params' => ['category' => 'offwhite-women-shoes']],
            ['name' => 'Versace Women Shoes',          'route' => 'catalog.category', 'params' => ['category' => 'versace-women-shoes']],
        ],

        // ---- Clothing ----
        'Clothing' => [
            ['name' => 'Amiri Women Clothing',            'route' => 'catalog.category', 'params' => ['category' => 'amiri-women-clothes']],
            ['name' => 'Chanel Women Clothing',           'route' => 'catalog.category', 'params' => ['category' => 'chanel-women-clothes']],
            ['name' => 'Dior Women Clothing',             'route' => 'catalog.category', 'params' => ['category' => 'dior-women-clothes']],
            ['name' => 'Givenchy Women Clothing',         'route' => 'catalog.category', 'params' => ['category' => 'givenchy-women-clothes']],
            ['name' => 'Gucci Women Clothing',            'route' => 'catalog.category', 'params' => ['category' => 'gucci-women-clothes']],
            ['name' => 'Louis Vuitton Women Clothing',    'route' => 'catalog.category', 'params' => ['category' => 'louis-vuitton-women-clothes']],
            ['name' => 'Moncler Women Clothing',          'route' => 'catalog.category', 'params' => ['category' => 'moncler-women-clothes']],
            ['name' => 'Off-White Women Clothing',        'route' => 'catalog.category', 'params' => ['category' => 'offwhite-women-clothes']],
            ['name' => 'Versace Women Clothing',          'route' => 'catalog.category', 'params' => ['category' => 'versace-women-clothes']],
        ],

        // ---- Belts ----
        'Belts' => [
            ['name' => 'Chanel Belts',          'route' => 'catalog.category', 'params' => ['category' => 'chanel-women-belts']],
        ],

        // ---- Jewelry ----
        'Jewelry' => [
            ['name' => 'Chanel Jewelry',        'route' => 'catalog.category', 'params' => ['category' => 'chanel-women-jewelry']],
            ['name' => 'Dior Jewelry',          'route' => 'catalog.category', 'params' => ['category' => 'dior-women-jewelry']],
            ['name' => 'Givenchy Jewelry',      'route' => 'catalog.category', 'params' => ['category' => 'givenchy-women-jewelry']],
            ['name' => 'Gucci Jewelry',         'route' => 'catalog.category', 'params' => ['category' => 'gucci-women-jewelry']],
            ['name' => 'Louis Vuitton Jewelry', 'route' => 'catalog.category', 'params' => ['category' => 'louis-vuitton-women-jewelry']],
        ],

        // ---- Glasses ----
        'Glasses' => [
            ['name' => 'Chanel Glasses',        'route' => 'catalog.category', 'params' => ['category' => 'chanel-women-glasses']],
            ['name' => 'Dior Glasses',          'route' => 'catalog.category', 'params' => ['category' => 'dior-women-glasses']],
            ['name' => 'Gucci Glasses',         'route' => 'catalog.category', 'params' => ['category' => 'gucci-women-glasses']],
            ['name' => 'Versace Glasses',       'route' => 'catalog.category', 'params' => ['category' => 'versace-women-glasses']],
        ],
    ],

    // ========================= MEN =========================
    'men' => [

        // ---- Shoes ----
        'Shoes' => [
            ['name' => 'Amiri Men Shoes',              'route' => 'catalog.category', 'params' => ['category' => 'amiri-men-shoes']],
            ['name' => 'Chanel Men Shoes',             'route' => 'catalog.category', 'params' => ['category' => 'chanel-men-shoes']],
            ['name' => 'Dior Men Shoes',               'route' => 'catalog.category', 'params' => ['category' => 'dior-men-shoes']],
            ['name' => 'Givenchy Men Shoes',           'route' => 'catalog.category', 'params' => ['category' => 'givenchy-men-shoes']],
            ['name' => 'Gucci Men Shoes',              'route' => 'catalog.category', 'params' => ['category' => 'gucci-men-shoes']],
            ['name' => 'Louis Vuitton Men Shoes',      'route' => 'catalog.category', 'params' => ['category' => 'louis-vuitton-men-shoes']],
            ['name' => 'McQueen Men Shoes',            'route' => 'catalog.category', 'params' => ['category' => 'mcqueen-men-shoes']],
            ['name' => 'Moncler Men Shoes',            'route' => 'catalog.category', 'params' => ['category' => 'moncler-men-shoes']],
            ['name' => 'Nike Shoes',                   'route' => 'catalog.category', 'params' => ['category' => 'nike-men-shoes']],
            ['name' => 'Off-White Men Shoes',          'route' => 'catalog.category', 'params' => ['category' => 'offwhite-men-shoes']],
            ['name' => 'Philipp Plein Men Shoes',      'route' => 'catalog.category', 'params' => ['category' => 'philipp-plein-men-shoes']],
            ['name' => 'Versace Men Shoes',            'route' => 'catalog.category', 'params' => ['category' => 'versace-men-shoes']],
            ['name' => 'Yeezy Shoes',                  'route' => 'catalog.category', 'params' => ['category' => 'yeezy-men-shoes']],
        ],

        // ---- Clothing ----
        'Clothing' => [
            ['name' => 'Amiri Men Clothing',              'route' => 'catalog.category', 'params' => ['category' => 'amiri-men-clothes']],
            ['name' => 'Chanel Men Clothing',             'route' => 'catalog.category', 'params' => ['category' => 'chanel-men-clothes']],
            ['name' => 'Dior Men Clothing',               'route' => 'catalog.category', 'params' => ['category' => 'dior-men-clothes']],
            ['name' => 'Givenchy Men Clothing',           'route' => 'catalog.category', 'params' => ['category' => 'givenchy-men-clothes']],
            ['name' => 'Gucci Men Clothing',              'route' => 'catalog.category', 'params' => ['category' => 'gucci-men-clothes']],
            ['name' => 'Louis Vuitton Men Clothing',      'route' => 'catalog.category', 'params' => ['category' => 'louis-vuitton-men-clothes']],
            ['name' => 'Moncler Men Clothing',            'route' => 'catalog.category', 'params' => ['category' => 'moncler-men-clothes']],
            ['name' => 'Off-White Men Clothing',          'route' => 'catalog.category', 'params' => ['category' => 'offwhite-men-clothes']],
            ['name' => 'Versace Men Clothing',            'route' => 'catalog.category', 'params' => ['category' => 'versace-men-clothes']],
        ],

        // ---- Belts ----
        'Belts' => [
            ['name' => 'Dior Belts',            'route' => 'catalog.category', 'params' => ['category' => 'dior-men-belts']],
            ['name' => 'Givenchy Belts',        'route' => 'catalog.category', 'params' => ['category' => 'givenchy-men-belts']],
            ['name' => 'Gucci Belts',           'route' => 'catalog.category', 'params' => ['category' => 'gucci-men-belts']],
            ['name' => 'Louis Vuitton Belts',   'route' => 'catalog.category', 'params' => ['category' => 'louis-vuitton-men-belts']],
            ['name' => 'Versace Belts',         'route' => 'catalog.category', 'params' => ['category' => 'versace-men-belts']],
        ],

        // ---- Glasses ----
        'Glasses' => [
            ['name' => 'Givenchy Glasses',      'route' => 'catalog.category', 'params' => ['category' => 'givenchy-men-glasses']],
            ['name' => 'Louis Vuitton Glasses', 'route' => 'catalog.category', 'params' => ['category' => 'louis-vuitton-men-glasses']],
        ],
    ],
];
