<?php

// ABOUTME: Admin controller for bulk product imports via the web UI.
// ABOUTME: Wraps the import:lv artisan command with a browser-friendly form interface.

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ImportController extends Controller
{
    /**
     * Show the import form with current database/folder stats.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $productCount = Product::count();
        $folderCount = $this->countImportFolders();

        return view('admin.import.index', compact('productCount', 'folderCount'));
    }

    /**
     * Execute the import command and redirect with results.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function run(Request $request)
    {
        $options = [];

        if ($request->has('fresh')) {
            $options['--fresh'] = true;
        }

        if ($request->has('skip_thumbnails')) {
            $options['--skip-thumbnails'] = true;
        }

        if ($request->filled('folder')) {
            $options['--folder'] = $request->input('folder');
        }

        Artisan::call('import:lv', $options);

        $output = Artisan::output();

        $productCount = Product::count();

        return redirect()->route('admin.import.index')
            ->with('status', "Import complete. {$productCount} products in database.")
            ->with('import_output', $output);
    }

    /**
     * Count the number of category folders in the imports directory.
     */
    protected function countImportFolders(): int
    {
        $base = storage_path('app/public/imports');

        if (! is_dir($base)) {
            return 0;
        }

        $folders = array_filter(scandir($base), function ($f) use ($base) {
            return $f !== '.' && $f !== '..' && is_dir("$base/$f");
        });

        return count($folders);
    }
}
