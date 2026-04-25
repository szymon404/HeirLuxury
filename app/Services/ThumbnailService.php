<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Service for generating and managing optimized WebP image thumbnails.
 *
 * This service handles thumbnail generation for the product catalog, creating
 * optimized WebP images in multiple sizes for different use cases:
 * - Card thumbnails for catalog grids
 * - Gallery images for product detail pages
 * - Small thumbnails for image navigation strips
 *
 * Storage Structure:
 * ------------------
 * Original: storage/app/public/imports/lv-bags-women/Product/0000.jpg
 * Thumbnails are stored in:
 *   storage/app/public/thumbnails/card/lv-bags-women/Product/0000.webp
 *   storage/app/public/thumbnails/gallery/lv-bags-women/Product/0000.webp
 *   storage/app/public/thumbnails/thumb/lv-bags-women/Product/0000.webp
 *
 * Features:
 * - On-demand generation: Creates thumbnails when first requested
 * - WebP format: Modern format with excellent compression
 * - Cover crop: Maintains aspect ratio by cropping to fill dimensions
 * - Batch generation: Can pre-generate all thumbnails via artisan command
 * - Cache lock: Prevents stampede when multiple requests hit uncached thumbnail
 * - URL caching: Caches resolved URLs to reduce filesystem checks
 *
 * Dependencies:
 * - intervention/image-laravel: Image manipulation library
 * - GD or Imagick PHP extension: Required by Intervention
 *
 * @see \App\Console\Commands\GenerateThumbnails For batch thumbnail generation
 * @see \App\Http\Controllers\ProductController For thumbnail usage in views
 */
class ThumbnailService
{
    /**
     * Thumbnail size configurations.
     *
     * Each size defines:
     * - width: Target width in pixels
     * - height: Target height in pixels
     * - quality: WebP quality (0-100, higher = better quality, larger file)
     *
     * Usage contexts:
     * - card: Product cards in catalog grid listings
     * - gallery: Main product image on detail pages
     * - thumb: Navigation thumbnails in product gallery strip
     */
    public const SIZES = [
        'card' => ['width' => 400, 'height' => 300, 'quality' => 80],
        'card_2x' => ['width' => 800, 'height' => 600, 'quality' => 80],
        'gallery' => ['width' => 800, 'height' => 800, 'quality' => 85],
        'thumb' => ['width' => 96,  'height' => 96,  'quality' => 75],
    ];

    /** @var int Cache TTL for URL lookups in seconds (1 hour) */
    protected const URL_CACHE_TTL = 3600;

    /** @var int Lock timeout for generation in seconds */
    protected const LOCK_TIMEOUT = 30;

    /** @var string Storage disk name */
    protected string $disk = 'public';

    /** @var string Base directory for thumbnail storage */
    protected string $thumbnailDir = 'thumbnails';

    /**
     * Get the public URL for a thumbnail, generating on-demand if needed.
     *
     * This is the primary method for getting thumbnail URLs in views.
     * It automatically generates the thumbnail if it doesn't exist.
     * Uses caching to avoid repeated filesystem checks.
     *
     * @param  string  $originalPath  Relative path to original image (e.g., "imports/lv-bags-women/Product/0000.jpg")
     * @param  string  $size  Size key: 'card', 'gallery', or 'thumb'
     * @return string|null Public URL to thumbnail, or null if invalid size or missing original
     */
    public function getUrl(string $originalPath, string $size = 'card'): ?string
    {
        if (! isset(self::SIZES[$size])) {
            return null;
        }

        $cacheKey = "thumbnail_url:{$size}:".md5($originalPath);

        return Cache::remember($cacheKey, self::URL_CACHE_TTL, function () use ($originalPath, $size) {
            $thumbnailPath = $this->getThumbnailPath($originalPath, $size);
            $storage = Storage::disk($this->disk);

            // Return existing thumbnail URL
            if ($storage->exists($thumbnailPath)) {
                return $storage->url($thumbnailPath);
            }

            // Generate on-demand if original exists (with lock to prevent stampede)
            if ($storage->exists($originalPath)) {
                $this->generateWithLock($originalPath, $size);

                // Verify generation succeeded
                if ($storage->exists($thumbnailPath)) {
                    return $storage->url($thumbnailPath);
                }
            }

            return null;
        });
    }

    /**
     * Generate a thumbnail with a lock to prevent stampede.
     *
     * When multiple requests hit an uncached thumbnail simultaneously,
     * only one will generate it while others wait.
     *
     * @param  string  $originalPath  Relative path to original image
     * @param  string  $size  Size key: 'card', 'gallery', or 'thumb'
     * @return bool True on success, false on failure or timeout
     */
    protected function generateWithLock(string $originalPath, string $size): bool
    {
        $lockKey = "thumbnail_lock:{$size}:".md5($originalPath);

        return Cache::lock($lockKey, self::LOCK_TIMEOUT)->block(self::LOCK_TIMEOUT, function () use ($originalPath, $size) {
            // Double-check: another process may have generated it while we waited
            if ($this->exists($originalPath, $size)) {
                return true;
            }

            return $this->generate($originalPath, $size);
        });
    }

    /**
     * Generate a single thumbnail for an image.
     *
     * Creates a WebP thumbnail with cover crop (fills dimensions, crops overflow).
     * Thumbnails are stored in: thumbnails/{size}/{relative-path}.webp
     *
     * @param  string  $originalPath  Relative path to original image
     * @param  string  $size  Size key: 'card', 'gallery', or 'thumb'
     * @return bool True on success, false on failure
     */
    public function generate(string $originalPath, string $size = 'card'): bool
    {
        if (! isset(self::SIZES[$size])) {
            return false;
        }

        $storage = Storage::disk($this->disk);

        if (! $storage->exists($originalPath)) {
            return false;
        }

        $config = self::SIZES[$size];
        $thumbnailPath = $this->getThumbnailPath($originalPath, $size);

        try {
            // Get full filesystem path for Intervention Image
            $fullPath = $storage->path($originalPath);

            // Load and process the image
            $image = Image::read($fullPath);

            // Cover crop: resize to fill dimensions, cropping excess
            $image->cover($config['width'], $config['height']);

            // Encode as WebP with configured quality
            $encoded = $image->encode(new WebpEncoder(quality: $config['quality']));

            // Create directory (makeDirectory is idempotent, no need to check exists)
            $storage->makeDirectory(dirname($thumbnailPath));

            // Save the thumbnail
            $storage->put($thumbnailPath, (string) $encoded);

            return true;
        } catch (\Throwable $e) {
            // Log the error but don't crash - views will fall back to original
            report($e);

            return false;
        }
    }

    /**
     * Generate all thumbnail sizes for a single image (optimized).
     *
     * Loads and decodes the image once, then resizes for each size.
     * More efficient than calling generate() three times.
     *
     * @param  string  $originalPath  Relative path to original image
     * @return array<string, bool> Results keyed by size name
     */
    public function generateAll(string $originalPath): array
    {
        $storage = Storage::disk($this->disk);
        $results = [];

        if (! $storage->exists($originalPath)) {
            foreach (array_keys(self::SIZES) as $size) {
                $results[$size] = false;
            }

            return $results;
        }

        try {
            // Load and decode the image ONCE
            $fullPath = $storage->path($originalPath);
            $sourceImage = Image::read($fullPath);

            foreach (self::SIZES as $size => $config) {
                try {
                    $thumbnailPath = $this->getThumbnailPath($originalPath, $size);

                    // Clone the source image to avoid modifying it
                    $image = clone $sourceImage;

                    // Cover crop for this size
                    $image->cover($config['width'], $config['height']);

                    // Encode as WebP
                    $encoded = $image->encode(new WebpEncoder(quality: $config['quality']));

                    // Create directory and save
                    $storage->makeDirectory(dirname($thumbnailPath));
                    $storage->put($thumbnailPath, (string) $encoded);

                    $results[$size] = true;
                } catch (\Throwable $e) {
                    report($e);
                    $results[$size] = false;
                }
            }
        } catch (\Throwable $e) {
            // Failed to load source image
            report($e);
            foreach (array_keys(self::SIZES) as $size) {
                $results[$size] = false;
            }
        }

        return $results;
    }

    /**
     * Convert an original image path to its thumbnail path.
     *
     * Path transformation:
     * - Strips 'imports/' prefix from original path
     * - Changes extension to .webp (or appends if no recognized extension)
     * - Prepends thumbnails/{size}/
     *
     * Example:
     *   Input:  imports/lv-bags-women/LV 0001/0000.jpg
     *   Output: thumbnails/card/lv-bags-women/LV 0001/0000.webp
     *
     * @param  string  $originalPath  Relative path to original image
     * @param  string  $size  Size key for thumbnail
     * @return string Relative path to thumbnail
     */
    public function getThumbnailPath(string $originalPath, string $size): string
    {
        // Strip 'imports/' prefix if present
        $relativePath = preg_replace('#^imports/#', '', $originalPath);

        // Convert extension to .webp, or append if no recognized extension
        if (preg_match('/\.(jpe?g|png|gif|webp|bmp|tiff?)$/i', $relativePath)) {
            $webpPath = preg_replace('/\.(jpe?g|png|gif|webp|bmp|tiff?)$/i', '.webp', $relativePath);
        } else {
            // No recognized extension - append .webp
            $webpPath = $relativePath.'.webp';
        }

        return "{$this->thumbnailDir}/{$size}/{$webpPath}";
    }

    /**
     * Check if a thumbnail exists for the given image and size.
     *
     * @param  string  $originalPath  Relative path to original image
     * @param  string  $size  Size key: 'card', 'gallery', or 'thumb'
     * @return bool True if thumbnail exists
     */
    public function exists(string $originalPath, string $size = 'card'): bool
    {
        $thumbnailPath = $this->getThumbnailPath($originalPath, $size);

        return Storage::disk($this->disk)->exists($thumbnailPath);
    }

    /**
     * Build an array of image URLs for a product gallery.
     *
     * Returns structured data for gallery components with:
     * - src: Gallery-sized image for main display
     * - thumb: Small thumbnail for navigation strip
     * - original: Full-size image for lightbox/zoom
     *
     * @param  string  $basePath  Base directory path (e.g., "imports/lv-bags-women/Product")
     * @param  array  $files  Array of filenames in the directory
     * @return array<int, array{src: string, thumb: string, original: string, alt: string}>
     */
    public function getGalleryImages(string $basePath, array $files): array
    {
        $storage = Storage::disk($this->disk);
        $images = [];

        foreach ($files as $file) {
            $originalPath = $basePath.'/'.basename($file);

            $images[] = [
                'src' => $this->getUrl($originalPath, 'gallery') ?? $storage->url($originalPath),
                'thumb' => $this->getUrl($originalPath, 'thumb') ?? $storage->url($originalPath),
                'original' => $storage->url($originalPath),
                'alt' => pathinfo($file, PATHINFO_FILENAME),
            ];
        }

        return $images;
    }

    /**
     * Get card thumbnail URL with automatic fallback to original.
     *
     * Convenience method for product card components.
     *
     * @param  string  $originalPath  Relative path to original image
     * @return string URL to card thumbnail or original
     */
    public function getCardUrl(string $originalPath): string
    {
        $storage = Storage::disk($this->disk);

        return $this->getUrl($originalPath, 'card')
            ?? $storage->url($originalPath);
    }

    /**
     * Build a `srcset` value pairing the 1x and 2x variants of a size.
     *
     * Convention: a size named `{base}` is paired with `{base}_2x` if that
     * companion exists in SIZES. Sizes without a `_2x` companion (e.g.
     * `thumb`, `gallery`) return null — the caller should fall back to a
     * plain `src` attribute.
     *
     * Returns null when:
     * - The base size is unknown.
     * - There is no registered `_2x` companion.
     * - Either thumbnail cannot be resolved (no original on disk to
     *   generate from, no existing thumbnail).
     *
     * Example output:
     *   "/storage/thumbnails/card/lv-bags-women/Bag/0000.webp 1x,
     *    /storage/thumbnails/card_2x/lv-bags-women/Bag/0000.webp 2x"
     *
     * @param  string  $originalPath  Relative path to original image
     * @param  string  $size  Base size key (e.g. 'card')
     * @return string|null `"url1x 1x, url2x 2x"` or null if pairing impossible
     */
    public function getSrcset(string $originalPath, string $size = 'card'): ?string
    {
        if (! isset(self::SIZES[$size])) {
            return null;
        }

        $retinaSize = $size.'_2x';
        if (! isset(self::SIZES[$retinaSize])) {
            return null;
        }

        $url1x = $this->getUrl($originalPath, $size);
        $url2x = $this->getUrl($originalPath, $retinaSize);

        if ($url1x === null || $url2x === null) {
            return null;
        }

        return "{$url1x} 1x, {$url2x} 2x";
    }

    /**
     * Clear cached thumbnail URL lookups for an image.
     *
     * Call this when an original image is updated or deleted,
     * or when thumbnails are regenerated.
     *
     * @param  string  $originalPath  Relative path to original image
     */
    public function clearCache(string $originalPath): void
    {
        foreach (array_keys(self::SIZES) as $size) {
            Cache::forget("thumbnail_url:{$size}:".md5($originalPath));
        }
    }

    /**
     * Delete all thumbnails for an image and clear its cache.
     *
     * Call this when an original image is deleted.
     *
     * @param  string  $originalPath  Relative path to original image
     */
    public function deleteThumbnails(string $originalPath): void
    {
        $storage = Storage::disk($this->disk);

        foreach (array_keys(self::SIZES) as $size) {
            $thumbnailPath = $this->getThumbnailPath($originalPath, $size);
            if ($storage->exists($thumbnailPath)) {
                $storage->delete($thumbnailPath);
            }
        }

        $this->clearCache($originalPath);
    }
}
