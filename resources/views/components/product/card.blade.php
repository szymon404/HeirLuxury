{{-- resources/views/components/product/card.blade.php --}}
@props(['product' => null, 'item' => null, 'p' => null])

@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Facades\Route;
    use App\Services\ThumbnailService;
    use App\Support\BrandRegistry;

    $prod = $product ?? $item ?? $p;
    if (! $prod) {
        return;
    }

    $val = fn($key, $default = null) => data_get($prod, $key, $default);

    $slug         = $val('slug') ?: Str::slug($val('name', 'product'));
    $categorySlug = $val('category_slug');
    $name         = (string) $val('name', 'Untitled');
    $catName      = $val('category.name')
                    ?: ($categorySlug ? Str::headline(str_replace('-', ' ', $categorySlug)) : null);
    $coming       = $val('status') === 'coming_soon';

    // === Correct href for product page ===
    if ($categorySlug && $slug && Route::has('product.show')) {
        $href = route('product.show', [
            'locale'      => app()->getLocale(),
            'category'    => $categorySlug,
            'productSlug' => $slug,
        ]);
    } else {
        $href = '#';
    }

    // === Thumbnail image resolution ===
    // Translate category_slug ({brand}-{gender}-{section}) to the on-disk import folder ({prefix}-{section}-{gender}).
    // Brand prefixes live in config/brands.php — see App\Support\BrandRegistry.
    $baseFolder = null;
    if ($categorySlug) {
        foreach (BrandRegistry::all() as $prefix => $brand) {
            if (str_starts_with($categorySlug, "{$brand}-")) {
                $rest = substr($categorySlug, strlen("{$brand}-"));
                if (preg_match('/^(women|men)-(.+)$/', $rest, $matches)) {
                    $gender = $matches[1];
                    $section = $matches[2];
                    $baseFolder = "{$prefix}-{$section}-{$gender}";
                    break;
                }
            }
        }
    }

    $folder    = $val('folder');
    $imageName = $val('image');

    // Default: no srcset (custom thumbnail or placeholder fallthrough).
    $srcset = null;

    // 0) Prefer custom thumbnail (already WebP, already sized for card)
    $customThumbnail = $val('thumbnail');
    if ($customThumbnail) {
        $img = Storage::disk('public')->url($customThumbnail);
        $originalImg = $img;
    } else {
        // 1) Prefer cover.jpg (scraped thumbnail) if it exists
        $imagePath = null;
        if ($baseFolder && $folder) {
            $coverPath = "imports/{$baseFolder}/{$folder}/cover.jpg";
            if (Storage::disk('public')->exists($coverPath)) {
                $imagePath = $coverPath;
            }
        }

        // 2) Fall back to stored image_path
        if (! $imagePath) {
            $imagePath = $val('image_path');
        }

        // 3) If still empty, build from baseFolder + folder + image
        if (! $imagePath && $baseFolder && $folder) {
            $filename  = $imageName ?: '0000.jpg';
            $imagePath = "imports/{$baseFolder}/{$folder}/{$filename}";
        }

        // Use optimized thumbnail service for card images
        $thumbnailService = app(ThumbnailService::class);

        if ($imagePath) {
            $img = $thumbnailService->getUrl($imagePath, 'card') ?? Storage::url($imagePath);
            $originalImg = Storage::url($imagePath);
            // Pair 1x + 2x card variants for retina screens; null when either is unavailable.
            $srcset = $thumbnailService->getSrcset($imagePath, 'card');
        } else {
            $img = asset('assets/placeholders/product-dark.png');
            $originalImg = $img;
        }
    }

    $alt = $val('alt', $name);
    $productId = $val('id');
@endphp

<div class="group">
    {{-- Media --}}
    <a href="{{ $href }}" class="block bg-[#050814] border border-white/5 overflow-hidden
              shadow-lg hover:border-amber-400/80 hover:shadow-amber-400/30 transition duration-300">
        <div class="aspect-[4/3] bg-black/40 relative overflow-hidden">
            <img
                src="{{ $img }}"
                @if ($srcset)
                    srcset="{{ $srcset }}"
                    sizes="(min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw"
                @endif
                alt="{{ $alt }}"
                width="400"
                height="300"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                loading="lazy"
                decoding="async"
            />

            @if ($coming)
                <span class="absolute top-3 left-3 z-10 inline-flex items-center rounded-full
                             bg-amber-300 text-black text-[11px] font-semibold px-3 py-1">
                    Coming Soon
                </span>
            @endif

            {{-- Wishlist heart button --}}
            @if ($productId)
                <button
                    type="button"
                    @click.prevent.stop="$store.wishlist.toggle({{ $productId }})"
                    class="absolute top-3 right-3 z-10 w-8 h-8 flex items-center justify-center
                           rounded-full bg-black/50 hover:bg-black/70 transition-all duration-200
                           hover:scale-110"
                    :class="$store.wishlist.has({{ $productId }}) ? 'text-amber-400' : 'text-white/70'"
                    :aria-label="$store.wishlist.has({{ $productId }}) ? '{{ __('messages.remove_from_wishlist') }}' : '{{ __('messages.add_to_wishlist') }}'"
                >
                    {{-- Outline heart --}}
                    <svg x-show="!$store.wishlist.has({{ $productId }})" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                    </svg>
                    {{-- Filled heart --}}
                    <svg x-show="$store.wishlist.has({{ $productId }})" x-cloak class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z" />
                    </svg>
                </button>
            @endif
        </div>
    </a>

    {{-- Product label --}}
    <a href="{{ $href }}" class="block px-1 pt-3 pb-1 text-center">
        <p class="text-sm font-semibold text-white/90 tracking-wide hover:text-amber-400 transition-colors duration-200">
            {{ $name }}
        </p>
    </a>
</div>
