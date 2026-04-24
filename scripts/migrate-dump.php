<?php

// ABOUTME: Dry-run + execute migration of scraped albums from Scraper/dump into storage/imports.
// ABOUTME: Renames {Brand Brand gender [section]} folders to {brand-section-gender}, flattens nested duplicates.

/**
 * Migrate scraped album folders from the Yupoo scraper's dump directory
 * into the Laravel imports directory expected by `php artisan import:lv`.
 *
 * Transformations:
 * - Dedupe the doubled brand prefix produced by the scraper
 *   (e.g. "Celine Celine women bags" → "celine-bags-women").
 * - Flatten the redundant inner folder layer (products live one level deeper
 *   inside a folder of the same name as the outer).
 * - Normalize brand tokens to the prefixes expected by ImportLV::$brandMap
 *   (Off-White → offwhite, Philipp-Plein → philippplein, LV stays lv, etc.).
 * - Default the section to "shoes" when the dump folder omits it (McQueen).
 * - Lowercase everything; reorder to {brand}-{section}-{gender}.
 *
 * Modes:
 *   php scripts/migrate-dump.php           # dry-run: print the mapping table
 *   php scripts/migrate-dump.php --execute # copy files into imports/
 *
 * Always non-destructive: copies (never moves) so the source dump is
 * preserved until the operator is satisfied.
 */
const SOURCE = 'C:/Users/simon/Documents/Scripts/Scraper/dump';
const TARGET = 'C:/Users/simon/Dev/Laravel/HeirLuxury/public/storage/imports';

// Brand token (as it appears in the dump) → prefix (as ImportLV expects).
const BRAND_PREFIX = [
    'celine' => 'celine',
    'chanel' => 'chanel',
    'dior' => 'dior',
    'givenchy' => 'givenchy',
    'gucci' => 'gucci',
    'lv' => 'lv',
    'mcqueen' => 'mcqueen',
    'moncler' => 'moncler',
    'nike' => 'nike',
    'off-white' => 'offwhite',
    'philipp-plein' => 'philippplein',
    'versace' => 'versace',
    'yeezy' => 'yeezy',
];

// Default section to apply when the dump folder is sectionless
// (e.g. "McQueen McQueen men" → shoes, since that's what the taxonomy has).
const DEFAULT_SECTIONS = [
    'mcqueen' => 'shoes',
];

/**
 * Convert one dump folder name into its canonical imports/ folder name,
 * or null if the shape is unrecognized.
 */
function mapFolderName(string $dumpName): ?string
{
    $tokens = preg_split('/\s+/', trim($dumpName));
    if (count($tokens) < 3) {
        return null;
    }

    // Dedupe doubled brand (case-insensitive; handles "NIke" typo).
    if (strcasecmp($tokens[0], $tokens[1]) === 0) {
        array_shift($tokens);
    }

    // Expected remainder: brand, gender, [section]
    $brandToken = strtolower($tokens[0]);
    $brandPrefix = BRAND_PREFIX[$brandToken] ?? null;
    if ($brandPrefix === null) {
        return null;
    }

    $gender = strtolower($tokens[1] ?? '');
    if (! in_array($gender, ['men', 'women', 'unisex'], true)) {
        return null;
    }

    $section = strtolower($tokens[2] ?? '') ?: (DEFAULT_SECTIONS[$brandPrefix] ?? null);
    if ($section === null) {
        return null;
    }

    return "{$brandPrefix}-{$section}-{$gender}";
}

/**
 * Recursively copy a directory tree. Skips files that already exist at
 * the destination (so the script is safely re-runnable).
 *
 * @return array{copied:int, skipped:int}
 */
function copyTree(string $src, string $dst): array
{
    $copied = 0;
    $skipped = 0;

    if (! is_dir($dst)) {
        mkdir($dst, 0755, true);
    }

    $handle = opendir($src);
    while (($entry = readdir($handle)) !== false) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $srcPath = "$src/$entry";
        $dstPath = "$dst/$entry";

        if (is_dir($srcPath)) {
            $sub = copyTree($srcPath, $dstPath);
            $copied += $sub['copied'];
            $skipped += $sub['skipped'];
        } else {
            if (file_exists($dstPath)) {
                $skipped++;
            } else {
                copy($srcPath, $dstPath);
                $copied++;
            }
        }
    }
    closedir($handle);

    return ['copied' => $copied, 'skipped' => $skipped];
}

// --- main ---

$execute = in_array('--execute', $argv, true);

if (! is_dir(SOURCE)) {
    fwrite(STDERR, 'Source not found: '.SOURCE."\n");
    exit(1);
}
if (! is_dir(TARGET)) {
    fwrite(STDERR, 'Target not found: '.TARGET."\n");
    exit(1);
}

$folders = array_values(array_filter(
    scandir(SOURCE),
    fn ($f) => $f !== '.' && $f !== '..' && is_dir(SOURCE.'/'.$f)
));

$rows = [];
$unrecognized = [];

foreach ($folders as $folder) {
    // Walk into the nested duplicate if present.
    $inner = SOURCE."/$folder/$folder";
    $source = is_dir($inner) ? $inner : SOURCE."/$folder";

    $target = mapFolderName($folder);
    if ($target === null) {
        $unrecognized[] = $folder;

        continue;
    }

    $productCount = count(array_filter(
        scandir($source),
        fn ($f) => $f !== '.' && $f !== '..' && is_dir("$source/$f")
    ));

    $rows[] = [
        'source' => $source,
        'source_label' => $folder,
        'target' => $target,
        'products' => $productCount,
    ];
}

echo str_repeat('=', 100)."\n";
echo $execute ? "EXECUTING MIGRATION\n" : "DRY RUN — no files will be copied. Re-run with --execute to perform the copy.\n";
echo str_repeat('=', 100)."\n\n";

printf("%-45s  →  %-30s  %s\n", 'DUMP FOLDER', 'TARGET FOLDER', 'PRODUCTS');
echo str_repeat('-', 100)."\n";
foreach ($rows as $row) {
    printf("%-45s  →  %-30s  %d\n", $row['source_label'], $row['target'], $row['products']);
}

if ($unrecognized) {
    echo "\nUNRECOGNIZED (will be skipped):\n";
    foreach ($unrecognized as $u) {
        echo "  - $u\n";
    }
}

echo "\n".count($rows).' folders mapped, '.count($unrecognized)." skipped.\n";

if (! $execute) {
    exit(0);
}

echo "\nCopying...\n";
$totalCopied = 0;
$totalSkipped = 0;
foreach ($rows as $row) {
    $dst = TARGET.'/'.$row['target'];
    echo "  {$row['source_label']} → {$row['target']} ... ";
    $result = copyTree($row['source'], $dst);
    $totalCopied += $result['copied'];
    $totalSkipped += $result['skipped'];
    echo "{$result['copied']} copied, {$result['skipped']} skipped\n";
}

echo "\nDone. $totalCopied files copied, $totalSkipped files already existed.\n";
