{{-- ABOUTME: Landing page with full-viewport intro animation and collections carousel.
    ABOUTME: Intro sequence: text logo → background monogram → navbar drop-in → scroll indicator. --}}

@extends('layouts.public')

{{-- Intro section lives outside <main> for clean viewport-height calculation --}}
@section('before-main')
<section
    x-data="introSequence()"
    x-init="start()"
    class="relative flex items-center justify-center overflow-hidden bg-slate-950"
    style="height: calc(100dvh - var(--header-h));"
>
    {{-- Background monogram logo --}}
    <img
        src="{{ asset('img/hl-logo-panel.png') }}"
        alt=""
        aria-hidden="true"
        :class="bgLogoVisible ? 'is-visible' : ''"
        class="intro-bg-logo"
    >

    {{-- Scroll-down indicator (inverted chevron) --}}
    <div
        x-show="scrollCueVisible"
        x-transition:enter="transition-opacity duration-500"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-70"
        x-transition:leave="transition-opacity duration-300"
        x-transition:leave-start="opacity-70"
        x-transition:leave-end="opacity-0"
        class="absolute bottom-8 z-10 flex flex-col items-center gap-2"
        style="left: calc(50% - 1.7rem); transform: translateX(-50%);"
    >
        <span class="text-[10px] tracking-[0.25em] uppercase text-white/40 font-medium">Scroll</span>
        <svg
            class="w-6 h-6 text-yellow-400/70 scroll-indicator"
            fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
    </div>
</section>

{{-- Intro sequence script --}}
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('introSequence', () => ({
        bgLogoVisible: false,
        scrollCueVisible: false,
        scrollHandler: null,
        _timers: [],

        start() {
            // Ensure page starts at top so collections panel doesn't peek
            window.scrollTo(0, 0);

            const hasVisited = sessionStorage.getItem('hl_visited');

            if (hasVisited) {
                // Return visit: show everything immediately, reveal navbar
                this.bgLogoVisible = true;
                this.scrollCueVisible = true;
                window.dispatchEvent(new CustomEvent('intro-navbar-reveal'));
                this.bindScrollDismiss();
                return;
            }

            // First visit: phased animation
            sessionStorage.setItem('hl_visited', 'true');

            // Phase 1: Background logo fades in
            this._timers.push(setTimeout(() => {
                this.bgLogoVisible = true;
            }, 300));

            // Phase 2: Navbar drops in
            this._timers.push(setTimeout(() => {
                window.dispatchEvent(new CustomEvent('intro-navbar-reveal'));
            }, 1400));

            // Phase 3: Scroll indicator appears
            this._timers.push(setTimeout(() => {
                this.scrollCueVisible = true;
                this.bindScrollDismiss();
            }, 2000));
        },

        bindScrollDismiss() {
            // Hide scroll indicator once the user starts scrolling
            this.scrollHandler = () => {
                if (window.scrollY > 80) {
                    this.scrollCueVisible = false;
                    window.removeEventListener('scroll', this.scrollHandler);
                    this.scrollHandler = null;
                }
            };
            window.addEventListener('scroll', this.scrollHandler, { passive: true });
        },

        destroy() {
            this._timers.forEach(id => clearTimeout(id));
            this._timers = [];
            if (this.scrollHandler) {
                window.removeEventListener('scroll', this.scrollHandler);
                this.scrollHandler = null;
            }
        }
    }));
});
</script>
@endsection

@section('content')

{{-- ========== COLLECTIONS PANEL (revealed on scroll) ========== --}}
<section class="relative py-16">
    {{-- Decorative Background Glow --}}
    <div class="pointer-events-none absolute inset-0 opacity-40"
         style="background: radial-gradient(circle at 0% 0%, rgba(234,179,8,0.4), transparent 55%),
                          radial-gradient(circle at 100% 0%, rgba(250,204,21,0.35), transparent 55%);">
    </div>

    {{-- LUXURY HERO PANEL --}}
    <div
        class="relative w-full max-w-6xl mx-auto overflow-hidden rounded-3xl border border-white/10
               bg-gradient-to-r from-slate-900 via-slate-900/95 to-slate-900/80
               px-10 py-14 sm:px-14 sm:py-16
               shadow-[0_40px_120px_rgba(0,0,0,0.85)] backdrop-blur-xl">

        {{-- HL Logo --}}
        <div class="mb-6 text-center">
            <img
                src="{{ asset('img/hl-logo-panel.png') }}"
                alt="Heir Luxury"
                class="w-20 h-20 mx-auto object-contain"
            >
        </div>

        <div class="relative max-w-3xl mx-auto text-center space-y-6">
            <p class="text-xs font-medium tracking-[0.38em] text-yellow-300 uppercase">
                {{ __('messages.collection') }}
            </p>

            <h2 class="text-4xl sm:text-5xl md:text-6xl font-semibold leading-tight text-slate-50">
                {{ __('messages.hero_title') }}
                <span class="block text-yellow-300">{{ __('messages.hero_subtitle') }}</span>
            </h2>

            <p class="max-w-xl mx-auto text-sm sm:text-base text-slate-300/80">
                {{ __('messages.hero_description') }}
            </p>

            <div class="flex flex-wrap justify-center items-center gap-4 pt-2">
                <a href="{{ route('catalog.grouped', ['locale' => app()->getLocale()]) }}"
                   class="inline-flex items-center rounded-full border border-white/20 px-6 py-2.5
                          text-sm font-medium text-slate-100 hover:border-yellow-300 hover:text-yellow-300
                          transition-colors">
                    {{ __('messages.collections') }}
                </a>
            </div>
        </div>

        {{-- ENTABLATURE / FRIEZE DIVIDER --}}
        <div class="relative mt-10">
            <div class="mx-auto h-px max-w-4xl bg-gradient-to-r from-transparent via-yellow-400/80 to-transparent"></div>
            <div class="absolute inset-x-0 -top-1 flex justify-center gap-1">
                <span class="h-2 w-10 rounded-full bg-yellow-500/90"></span>
                <span class="h-2 w-4 rounded-full bg-yellow-300/90"></span>
                <span class="h-2 w-10 rounded-full bg-yellow-500/90"></span>
            </div>
        </div>

        {{-- NEW ADDITIONS CAROUSEL --}}
        @if(isset($newAdditions) && $newAdditions->count() >= 3)
        <div class="mt-10 w-full" x-data="productCarousel({{ $newAdditions->count() }})">
            <p class="text-xs font-medium tracking-[0.3em] text-slate-300 uppercase mb-4 text-center">
                {{ __('messages.new_additions') }}
            </p>

            <div class="relative">
                {{-- Left Arrow --}}
                <button
                    type="button"
                    @click="prev()"
                    class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-4 z-10
                           w-10 h-10 rounded-full bg-black/60 border border-white/20
                           text-white flex items-center justify-center
                           hover:bg-yellow-400 hover:text-black hover:border-yellow-400
                           transition duration-200"
                    aria-label="Previous products"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                {{-- Carousel Container --}}
                <div class="overflow-hidden mx-6">
                    <div
                        class="flex transition-transform duration-500 ease-out"
                        :style="'transform: translateX(-' + (currentIndex * (100 / 3)) + '%)'"
                    >
                        @foreach($newAdditions as $product)
                            @php
                                $productHref = route('product.show', [
                                    'locale' => app()->getLocale(),
                                    'category' => $product->category_slug,
                                    'productSlug' => $product->slug,
                                ]);
                                $thumbnailService = app(\App\Services\ThumbnailService::class);
                                $img = $product->image_path
                                    ? ($thumbnailService->getUrl($product->image_path, 'card') ?? Storage::url($product->image_path))
                                    : asset('assets/placeholders/product-dark.png');
                                $imgSrcset = $product->image_path
                                    ? $thumbnailService->getSrcset($product->image_path, 'card')
                                    : null;
                            @endphp
                            <div class="w-1/3 flex-shrink-0 px-2">
                                <a href="{{ $productHref }}"
                                   class="block aspect-[4/3] rounded-2xl overflow-hidden border border-white/10
                                          hover:border-amber-400/80 transition duration-300">
                                    <img
                                        src="{{ $img }}"
                                        @if ($imgSrcset)
                                            srcset="{{ $imgSrcset }}"
                                            sizes="33vw"
                                        @endif
                                        alt="{{ $product->name }}"
                                        width="400"
                                        height="300"
                                        class="w-full h-full object-cover hover:scale-105 transition-transform duration-500"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Right Arrow --}}
                <button
                    type="button"
                    @click="next()"
                    class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-4 z-10
                           w-10 h-10 rounded-full bg-black/60 border border-white/20
                           text-white flex items-center justify-center
                           hover:bg-yellow-400 hover:text-black hover:border-yellow-400
                           transition duration-200"
                    aria-label="Next products"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>

            {{-- Dots Indicator --}}
            <div class="flex justify-center gap-2 mt-4">
                @for($i = 0; $i < min($newAdditions->count() - 2, 7); $i++)
                    <button
                        type="button"
                        @click="currentIndex = {{ $i }}"
                        :class="currentIndex === {{ $i }}
                            ? 'bg-yellow-400 w-6'
                            : 'bg-white/30 w-2 hover:bg-white/50'"
                        class="h-2 rounded-full transition-all duration-300"
                        aria-label="Go to slide {{ $i + 1 }}"
                    ></button>
                @endfor
            </div>
        </div>
        @endif
    </div>
</section>
@endsection
