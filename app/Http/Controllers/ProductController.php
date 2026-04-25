<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CatalogCache;
use App\Services\ThumbnailService;
use App\Support\BrandRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles individual product detail pages.
 *
 * This controller is responsible for displaying single product views with:
 * - Full image gallery (with optimized thumbnails)
 * - Related products from the same category
 * - Breadcrumb navigation
 *
 * Products are identified by their category_slug + product_slug combination,
 * which allows for unique URLs like: /catalog/louis-vuitton-women-bags/neverfull-mm
 *
 * Caching Strategy:
 * - Image file listings: 24 hours (reduces filesystem scans)
 * - Related product IDs: Uses versioned catalog cache for instant invalidation
 *
 * @see \App\Services\ThumbnailService For image optimization
 * @see \App\Http\Controllers\CategoryController For category browsing
 */
class ProductController extends Controller
{
    public function __construct(
        protected ThumbnailService $thumbnailService,
        protected CatalogCache $catalogCache
    ) {}

    /**
     * Display a single product's detail page.
     *
     * This method handles the product detail view, including:
     * 1. Loading the product by category + slug (ensures unique URLs)
     * 2. Building the image gallery from the product's folder
     * 3. Generating optimized thumbnails for gallery display
     * 4. Loading related products from the same category
     * 5. Building breadcrumb navigation
     *
     * Image Gallery Structure:
     * - Products have images stored in: storage/imports/{base-folder}/{product-folder}/
     * - Each image gets three versions: thumb (96px), gallery (800px), original
     * - ThumbnailService generates WebP thumbnails on-demand
     *
     * @param  string  $category  The category_slug (e.g., "louis-vuitton-women-bags")
     * @param  string  $productSlug  The product's unique slug within its category
     * @return \Illuminate\View\View
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If product not found
     */
    public function show(string $locale, string $category, string $productSlug)
    {
        // Find product by both category and slug to ensure unique URL resolution
        $product = Product::where('category_slug', $category)
            ->where('slug', $productSlug)
            ->firstOrFail();

        /*
         * Dynamically resolve the storage folder from category_slug.
         *
         * Category slug format: {brand}-{gender}-{section}
         * Storage folder format: {brand-prefix}-{section}-{gender}
         *
         * Examples:
         * - louis-vuitton-women-bags -> lv-bags-women
         * - chanel-men-shoes -> chanel-shoes-men
         * - hermes-women-belts -> hermes-belts-women
         */
        $baseFolder = $this->resolveStorageFolder($product->category_slug);

        $images = [];

        // Build gallery from product's image folder in storage
        if ($baseFolder && $product->folder) {
            $disk = Storage::disk('public');
            $dir = "imports/{$baseFolder}/{$product->folder}";

            if ($disk->exists($dir)) {
                // Cache file listing to avoid repeated filesystem scans
                // Sort by file size descending so largest (best quality) image is first
                $files = Cache::remember(
                    "product.images.{$product->id}",
                    now()->addHours(24),
                    function () use ($disk, $dir) {
                        return collect($disk->allFiles($dir))
                            ->filter(fn ($path) => preg_match('/\.(jpe?g|png|webp)$/i', $path))
                            ->sortByDesc(fn ($path) => $disk->size($path))
                            ->values()
                            ->all();
                    }
                );
                /*
                                 * Build image array with three sizes for each image:
                                 * - src: Gallery size (800x800) for main display
                                 * - thumb: Thumbnail (96x96) for gallery navigation strip
                                 * - original: Full resolution for zoom/download
                                 */
                $images = collect($files)->map(function (string $path) use ($disk, $product) {
                    return [
                        'src' => $this->thumbnailService->getUrl($path, 'gallery') ?? $disk->url($path),
                        'thumb' => $this->thumbnailService->getUrl($path, 'thumb') ?? $disk->url($path),
                        'original' => $disk->url($path),
                        'alt' => $product->name,
                    ];
                })->all();
            }
        }
        // Fallback: use the single image_path if no folder-based gallery exists
        if (empty($images) && $product->image_path) {
            $disk = Storage::disk('public');
            $images[] = [
                'src' => $this->thumbnailService->getUrl($product->image_path, 'gallery') ?? $disk->url($product->image_path),
                'thumb' => $this->thumbnailService->getUrl($product->image_path, 'thumb') ?? $disk->url($product->image_path),
                'original' => $disk->url($product->image_path),
                'alt' => $product->name,
            ];
        }
        // Load related products using versioned cache (IDs only for lightweight caching)
        $related = $this->getRelatedProducts($product);
        // Build breadcrumb trail: Home > Catalog > Category > Product
        $categoryLabel = Str::headline(str_replace('-', ' ', $product->category_slug));

        $breadcrumbs = [
            ['label' => 'Home',          'href' => route('home', ['locale' => $locale])],
            ['label' => 'Catalog',       'href' => route('catalog.grouped', ['locale' => $locale])],
            ['label' => $categoryLabel,  'href' => route('catalog.category', ['locale' => $locale, 'category' => $product->category_slug])],
            ['label' => $product->name,  'href' => null],
        ];

        return view('catalog.product', [
            'product' => $product,
            'images' => $images,
            'related' => $related,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Get related products with versioned caching.
     *
     * Caches only IDs to reduce memory, then hydrates.
     * Uses catalog version for instant invalidation when products change.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getRelatedProducts(Product $product)
    {
        $version = $this->catalogCache->getVersion();
        $cacheKey = "related:v{$version}:{$product->id}";

        $ids = Cache::remember($cacheKey, $this->catalogCache->getTtl(), function () use ($product) {
            return Product::where('category_slug', $product->category_slug)
                ->where('id', '!=', $product->id)
                ->latest('id')
                ->take(12)
                ->pluck('id')
                ->all();
        });

        if (empty($ids)) {
            return collect();
        }
        // Fetch and sort in PHP to be database-agnostic (SQLite lacks FIELD())
        $products = Product::whereIn('id', $ids)->get();
        $idOrder = array_flip($ids);

        return $products->sortBy(fn ($p) => $idOrder[$p->id] ?? PHP_INT_MAX)->values();
    }

    /**
     * Resolve storage folder path from category_slug.
     *
     * Converts category_slug format ({brand}-{gender}-{section})
     * to storage folder format ({brand-prefix}-{section}-{gender}).
     *
     * @param  string  $categorySlug  e.g., "louis-vuitton-women-bags"
     * @return string|null e.g., "lv-bags-women"
     */
    protected function resolveStorageFolder(string $categorySlug): ?string
    {
        // Parse: {brand}-{gender}-{section}
        // Examples: louis-vuitton-women-bags, chanel-men-shoes
        // Brand prefix map lives in config/brands.php — see App\Support\BrandRegistry.
        foreach (BrandRegistry::all() as $prefix => $brand) {
            if (str_starts_with($categorySlug, "{$brand}-")) {
                $rest = substr($categorySlug, strlen("{$brand}-"));
                // rest = "women-bags" or "men-shoes"
                if (preg_match('/^(women|men)-(.+)$/', $rest, $matches)) {
                    $gender = $matches[1];
                    $section = $matches[2];

                    return "{$prefix}-{$section}-{$gender}";
                }
            }
        }

        return null;
    }
}
