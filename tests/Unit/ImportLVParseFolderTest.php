<?php

// ABOUTME: Unit tests for ImportLV::parseFolderName() — covers men/women/unisex gender parsing.
// ABOUTME: Unisex folders must expand into two parsed entries (one per binary gender slug).

namespace Tests\Unit;

use App\Console\Commands\ImportLV;
use Tests\TestCase;

/**
 * Unit tests for ImportLV folder-name parsing.
 *
 * Verifies that {brand}-{section}-{gender} folder names resolve to one or more
 * parsed entries, each carrying brand, gender, section, and category_slug.
 * Unisex folders must yield two entries (men + women slugs) so products are
 * duplicated into both catalogs per the project's taxonomy.
 *
 * To run:
 *   php artisan test --filter=ImportLVParseFolderTest
 */
class ImportLVParseFolderTest extends TestCase
{
    protected ImportLV $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->command = new ImportLV;
    }

    public function test_women_folder_parses_to_single_entry(): void
    {
        $result = $this->command->parseFolderName('lv-bags-women');

        $this->assertCount(1, $result);
        $this->assertSame('louis-vuitton', $result[0]['brand']);
        $this->assertSame('women', $result[0]['gender']);
        $this->assertSame('bags', $result[0]['section']);
        $this->assertSame('louis-vuitton-women-bags', $result[0]['category_slug']);
    }

    public function test_men_folder_parses_to_single_entry(): void
    {
        $result = $this->command->parseFolderName('chanel-shoes-men');

        $this->assertCount(1, $result);
        $this->assertSame('chanel', $result[0]['brand']);
        $this->assertSame('men', $result[0]['gender']);
        $this->assertSame('shoes', $result[0]['section']);
        $this->assertSame('chanel-men-shoes', $result[0]['category_slug']);
    }

    public function test_unisex_folder_expands_to_both_men_and_women(): void
    {
        $result = $this->command->parseFolderName('lv-belts-unisex');

        $this->assertCount(2, $result);

        $genders = array_column($result, 'gender');
        sort($genders);
        $this->assertSame(['men', 'women'], $genders);

        $slugs = array_column($result, 'category_slug');
        sort($slugs);
        $this->assertSame(
            ['louis-vuitton-men-belts', 'louis-vuitton-women-belts'],
            $slugs
        );

        foreach ($result as $entry) {
            $this->assertSame('louis-vuitton', $entry['brand']);
            $this->assertSame('belts', $entry['section']);
        }
    }

    public function test_unknown_brand_prefix_passes_through_as_brand(): void
    {
        $result = $this->command->parseFolderName('hermes-bags-women');

        $this->assertCount(1, $result);
        $this->assertSame('hermes', $result[0]['brand']);
        $this->assertSame('hermes-women-bags', $result[0]['category_slug']);
    }

    public function test_invalid_folder_returns_empty_array(): void
    {
        $this->assertSame([], $this->command->parseFolderName('not a valid folder'));
        $this->assertSame([], $this->command->parseFolderName('lv-bags'));
        $this->assertSame([], $this->command->parseFolderName('LV-Bags-Women'));
        $this->assertSame([], $this->command->parseFolderName('lv-bags-children'));
    }
}
