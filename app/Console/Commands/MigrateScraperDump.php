<?php

// ABOUTME: Artisan command to transform Yupoo scraper dump folder structure into import-ready symlinks.
// ABOUTME: Creates directory junctions from dump/{Brand}/{Brand Gender section}/ to storage imports format.

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Transform a Yupoo scraper dump into the folder structure expected by import:lv.
 *
 * The scraper outputs: dump/{Brand}/{Brand Gender section}/{Brand NNNN}/00000.jpg
 * The importer expects: imports/{brand}-{section}-{gender}/{Product Name}/00000.jpg
 *
 * This command creates Windows directory junctions (symlink equivalent) from each
 * category folder in the dump to the corresponding imports path, avoiding any file copying.
 *
 * Usage:
 *   php artisan scraper:migrate "C:\path\to\dump"              # Create junctions
 *   php artisan scraper:migrate "C:\path\to\dump" --dry-run    # Preview only
 *   php artisan scraper:migrate "C:\path\to\dump" --clean      # Remove existing junctions first
 */
class MigrateScraperDump extends Command
{
    protected $signature = 'scraper:migrate
                            {path : Path to the scraper dump folder}
                            {--dry-run : Preview mapping without creating junctions}
                            {--clean : Remove existing junctions before creating new ones}';

    protected $description = 'Create symlinks from scraper dump to import folder structure';

    /**
     * Map scraper brand folder names (case-insensitive) to import prefix.
     *
     * Keys are lowercased versions of the dump's top-level folder names.
     * Values are the prefix used in {prefix}-{section}-{gender} import folders.
     */
    protected array $brandFolderMap = [
        'lv' => 'lv',
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
        'philipp plein' => 'philippplein',
        'versace' => 'versace',
        'yeezy' => 'yeezy',
    ];

    public function handle(): int
    {
        $dumpPath = $this->argument('path');
        $importsPath = storage_path('app/public/imports');
        $dryRun = $this->option('dry-run');
        $clean = $this->option('clean');

        if (! is_dir($dumpPath)) {
            $this->error("Dump folder not found: $dumpPath");

            return Command::FAILURE;
        }

        // Ensure imports directory exists
        if (! $dryRun && ! is_dir($importsPath)) {
            mkdir($importsPath, 0755, true);
        }

        // Clean existing junctions if requested
        if ($clean && ! $dryRun) {
            $this->cleanExistingJunctions($importsPath);
        }

        $this->info($dryRun ? '--- DRY RUN MODE ---' : 'Creating directory junctions...');
        $this->line('');

        $totalCategories = 0;
        $totalProducts = 0;
        $skipped = [];

        // Scan top-level brand folders
        $brandFolders = $this->getSubdirectories($dumpPath);

        foreach ($brandFolders as $brandFolder) {
            $brandKey = strtolower($brandFolder);
            $brandPrefix = $this->brandFolderMap[$brandKey] ?? null;

            if (! $brandPrefix) {
                $skipped[] = $brandFolder;
                $this->warn("  Skipping unknown brand folder: $brandFolder");

                continue;
            }

            $brandPath = "$dumpPath/$brandFolder";
            $categoryFolders = $this->getSubdirectories($brandPath);

            foreach ($categoryFolders as $categoryFolder) {
                $parsed = $this->parseCategoryFolder($categoryFolder, $brandKey, $brandPrefix);

                if (! $parsed) {
                    $skipped[] = "$brandFolder/$categoryFolder";
                    $this->warn("  Skipping unrecognized category: $brandFolder/$categoryFolder");

                    continue;
                }

                $importFolderName = $parsed['import_folder'];
                $sourcePath = "$brandPath/$categoryFolder";
                $targetPath = "$importsPath/$importFolderName";

                // Count products in this category
                $productCount = count($this->getSubdirectories($sourcePath));

                if ($dryRun) {
                    $this->line("  <info>$brandFolder/$categoryFolder</info>");
                    $this->line("    → <comment>$importFolderName</comment> ($productCount products)");
                } else {
                    $this->createJunction($sourcePath, $targetPath);
                    $this->line("  <info>$importFolderName</info> ← $brandFolder/$categoryFolder ($productCount products)");
                }

                $totalCategories++;
                $totalProducts += $productCount;
            }
        }

        $this->line('');
        $this->info("Categories mapped: $totalCategories");
        $this->info("Total products: $totalProducts");

        if (! empty($skipped)) {
            $this->warn('Skipped folders: '.count($skipped));

            foreach ($skipped as $s) {
                $this->line("  - $s");
            }
        }

        if ($dryRun) {
            $this->line('');
            $this->comment('Run without --dry-run to create junctions.');
        }

        return Command::SUCCESS;
    }

    /**
     * Parse a category folder name like "LV Women bags" into import components.
     *
     * @param  string  $folderName  e.g., "LV Women bags", "Philipp Plein Men shoes"
     * @param  string  $brandKey  Lowercased brand folder name for prefix lookup
     * @param  string  $brandPrefix  Import prefix (e.g., "lv", "philippplein")
     * @return array|null ['import_folder' => 'lv-bags-women', 'gender' => 'women', 'section' => 'bags']
     */
    protected function parseCategoryFolder(string $folderName, string $brandKey, string $brandPrefix): ?array
    {
        // Split into words: last = section, second-to-last = gender
        $words = explode(' ', trim($folderName));

        if (count($words) < 3) {
            return null;
        }

        $section = strtolower(array_pop($words));
        $gender = strtolower(array_pop($words));

        if (! in_array($gender, ['women', 'men'])) {
            return null;
        }

        // Build the import folder name: {brand}-{section}-{gender}
        $importFolder = "$brandPrefix-$section-$gender";

        return [
            'import_folder' => $importFolder,
            'gender' => $gender,
            'section' => $section,
        ];
    }

    /**
     * Create a Windows directory junction (works without admin privileges).
     */
    protected function createJunction(string $source, string $target): void
    {
        // Convert forward slashes to backslashes for Windows mklink
        $source = str_replace('/', '\\', $source);
        $target = str_replace('/', '\\', $target);

        // Remove existing junction/symlink if present
        if (is_link($target) || is_dir($target)) {
            // Check if it's a junction/symlink (not a real directory)
            if (is_link($target) || $this->isJunction($target)) {
                rmdir($target);
            } else {
                $this->warn("  Target exists and is a real directory: $target — skipping");

                return;
            }
        }

        // Create directory junction using mklink /J
        $cmd = sprintf('mklink /J "%s" "%s"', $target, $source);
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->error("  Failed to create junction: $cmd");
        }
    }

    /**
     * Check if a path is a directory junction (Windows).
     */
    protected function isJunction(string $path): bool
    {
        $path = str_replace('/', '\\', $path);
        exec(sprintf('fsutil reparsepoint query "%s" 2>nul', $path), $output, $exitCode);

        return $exitCode === 0;
    }

    /**
     * Remove all directory junctions from the imports folder.
     */
    protected function cleanExistingJunctions(string $importsPath): void
    {
        if (! is_dir($importsPath)) {
            return;
        }

        $this->info('Cleaning existing junctions...');
        $count = 0;

        foreach ($this->getSubdirectories($importsPath) as $dir) {
            $fullPath = "$importsPath/$dir";

            if (is_link($fullPath) || $this->isJunction($fullPath)) {
                rmdir($fullPath);
                $count++;
            }
        }

        $this->info("  Removed $count existing junctions.");
    }

    /**
     * Get immediate subdirectory names (excluding . and ..).
     *
     * @return array<string> Directory names (not full paths)
     */
    protected function getSubdirectories(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        return array_values(array_filter(
            scandir($path),
            fn ($f) => $f !== '.' && $f !== '..' && is_dir("$path/$f")
        ));
    }
}
