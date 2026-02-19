{{-- ABOUTME: Main navigation bar with desktop mega menu, mobile slide-up sheet, and wishlist.
    ABOUTME: Handles language switching, catalog navigation, and contact modal triggers. --}}

@php
    // Pre-load navigation data for the mobile sheet (desktop mega.blade.php loads its own)
    $navData = \App\Models\Category::getNavigationData();
    $configData = config('categories', []);

    $womenSections = collect(!empty($navData['women']) ? $navData['women'] : ($configData['women'] ?? []));
    $menSections = collect(!empty($navData['men']) ? $navData['men'] : ($configData['men'] ?? []));

    $currentLocale = app()->getLocale();
    $currentPath = request()->path();
    // Remove current locale prefix to get the path
    $pathWithoutLocale = preg_replace('#^(en|pl)/?#', '', $currentPath);
@endphp

<nav
    x-data="{
        open: false,
        mobile: false,
        gender: 'women',
        introPage: {{ isset($introNav) && $introNav ? 'true' : 'false' }},
        introRevealed: {{ isset($introNav) && $introNav ? 'false' : 'true' }},
        introAnimated: false
    }"
    x-init="
        $watch('open',   value => document.body.classList.toggle('is-menu-open', value || mobile));
        $watch('mobile', value => document.body.classList.toggle('is-menu-open', value || open));
        if (introPage && sessionStorage.getItem('hl_visited')) {
            introRevealed = true;
        }
        if (!introRevealed) {
            window.addEventListener('intro-navbar-reveal', () => {
                introAnimated = true;
                introRevealed = true;
            }, { once: true });
        }
    "
    @keydown.escape.window="open = false; mobile = false"
    class="lux-nav"
    :class="{
        'is-intro-hidden': !introRevealed,
        'is-intro-reveal': introAnimated
    }"
>
    <div class="lux-nav-inner">
        {{-- LEFT: LOGO --}}
        <a href="{{ route('home') }}" class="lux-logo">
            <span class="lux-logo-heir">HEIR</span>
            <span class="lux-logo-luxury">LUXURY</span>
        </a>

        {{-- CENTER: CATALOG LABEL (DESKTOP) --}}
        <button
            type="button"
            @click="open = !open; if(open) window.scrollTo({ top: 0, behavior: 'smooth' })"
            :aria-expanded="open"
            class="catalog-toggle"
        >
            <span class="catalog-line"></span>
            <span class="catalog-label">{{ __('messages.catalog') }}</span>
            <span class="catalog-line"></span>
        </button>

        {{-- RIGHT: LANGUAGE SWITCHER + CONTACT (DESKTOP) --}}
        <div class="hidden lg:flex items-center gap-4">
            {{-- Language Switcher --}}
            <div class="flex items-center gap-1">
                <a href="/en/{{ $pathWithoutLocale }}"
                   class="lang-switch {{ $currentLocale === 'en' ? 'active' : '' }}"
                   aria-label="Switch to English">
                    <span class="lang-label">EN</span>
                    <span class="lang-label lang-label-hover">EN</span>
                </a>

                <span class="text-white/30 text-xs">/</span>

                <a href="/pl/{{ $pathWithoutLocale }}"
                   class="lang-switch {{ $currentLocale === 'pl' ? 'active' : '' }}"
                   aria-label="Switch to Polish">
                    <span class="lang-label">PL</span>
                    <span class="lang-label lang-label-hover">PL</span>
                </a>
            </div>

            {{-- Wishlist Heart + Dropdown Anchor --}}
            <div class="relative flex items-center">
                <button
                    type="button"
                    @click="$store.wishlist.togglePanel()"
                    class="relative flex items-center text-white/70 hover:text-amber-400 transition"
                    aria-label="{{ __('messages.wishlist') }}"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                    </svg>
                    <span
                        x-show="$store.wishlist.count > 0"
                        x-text="$store.wishlist.count"
                        x-cloak
                        class="absolute -top-1.5 -right-2 bg-amber-400 text-black text-[10px] font-bold
                               rounded-full min-w-[16px] h-4 flex items-center justify-center px-0.5"
                    ></span>
                </button>

                {{-- Desktop wishlist dropdown (positioned under heart) --}}
                <div class="hidden lg:block">
                    @include('wishlist.dropdown')
                </div>
            </div>

            <button
                type="button"
                onclick="window.dispatchEvent(new Event('open-contact-modal'))"
                class="btn-gold px-4 py-1.5 text-xs"
            >
                {{ __('messages.contact') }}
            </button>
        </div>

        {{-- MOBILE: LANG SWITCHER + WISHLIST + CONTACT + HAMBURGER --}}
        <div class="flex items-center gap-2 lg:hidden">
            {{-- Mobile Language Switcher (compact) --}}
            <div class="flex items-center gap-1">
                <a href="/en/{{ $pathWithoutLocale }}"
                   class="text-[11px] font-medium {{ $currentLocale === 'en' ? 'text-yellow-400' : 'text-white/50' }}"
                   aria-label="Switch to English">
                    EN
                </a>
                <span class="text-white/30 text-[10px]">/</span>
                <a href="/pl/{{ $pathWithoutLocale }}"
                   class="text-[11px] font-medium {{ $currentLocale === 'pl' ? 'text-yellow-400' : 'text-white/50' }}"
                   aria-label="Switch to Polish">
                    PL
                </a>
            </div>

            {{-- Wishlist Heart + Dropdown Anchor (mobile) --}}
            <div class="relative flex items-center">
                <button
                    type="button"
                    @click="$store.wishlist.togglePanel()"
                    class="relative flex items-center text-white/70 hover:text-amber-400 transition"
                    aria-label="{{ __('messages.wishlist') }}"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                    </svg>
                    <span
                        x-show="$store.wishlist.count > 0"
                        x-text="$store.wishlist.count"
                        x-cloak
                        class="absolute -top-1.5 -right-2 bg-amber-400 text-black text-[10px] font-bold
                               rounded-full min-w-[16px] h-4 flex items-center justify-center px-0.5"
                    ></span>
                </button>

                {{-- Mobile wishlist dropdown (positioned under heart) --}}
                <div class="lg:hidden">
                    @include('wishlist.dropdown')
                </div>
            </div>

            <button
                type="button"
                onclick="window.dispatchEvent(new Event('open-contact-modal'))"
                class="btn-gold text-[11px] px-3 py-1.5"
            >
                {{ __('messages.contact') }}
            </button>

            <button
                type="button"
                @click="mobile = !mobile"
                class="inline-flex h-10 w-10 items-center justify-center rounded-md hover:bg-white/10"
                :aria-label="mobile ? 'Close menu' : 'Open menu'"
            >
                {{-- Hamburger icon (shown when closed) --}}
                <svg x-show="!mobile" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                {{-- X icon (shown when open) --}}
                <svg x-show="mobile" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M6 18L18 6" />
                </svg>
            </button>
        </div>
    </div>

    {{-- ========== DARK BACKDROP (closes mega + mobile menus) ========== --}}
    <div
        x-show="open || mobile"
        x-transition.opacity
        x-cloak
        @click="open = false; mobile = false"
        class="fixed inset-0 bg-black/60 z-40"
    ></div>

    {{-- ========== DESKTOP MEGA PANEL ========== --}}
    <section
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
        class="mega-panel hidden lg:block"
    >
        <div class="mega-scroll gold-scroll">
            @include('catalog.mega')
        </div>

        {{-- Gold line --}}
        <div class="mega-bottom-border"></div>
    </section>

    {{-- ========== MOBILE SLIDE-UP SHEET ========== --}}
    <section
        x-show="mobile"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-full"
        x-transition:enter-end="translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-y-0"
        x-transition:leave-end="translate-y-full"
        class="mobile-sheet lg:hidden"
        role="dialog"
        aria-label="Catalog navigation"
    >
        <header class="flex h-14 items-center justify-between border-b border-white/10 px-4">
            <span class="text-xs font-semibold tracking-[0.32em] uppercase text-slate-200">{{ __('messages.catalog') }}</span>

            <button
                type="button"
                @click="mobile = false"
                class="inline-flex h-10 w-10 items-center justify-center rounded-md hover:bg-white/10"
                aria-label="Close menu"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M6 18L18 6" />
                </svg>
            </button>
        </header>

        <div class="mobile-scroll gold-scroll p-6 space-y-6">
            {{-- Gender toggle tabs --}}
            <div class="mega-genders">
                <button
                    type="button"
                    @click="gender = 'women'"
                    :class="gender === 'women' ? 'is-active' : ''"
                >
                    {{ __('messages.women') }}
                </button>

                <button
                    type="button"
                    @click="gender = 'men'"
                    :class="gender === 'men' ? 'is-active' : ''"
                >
                    {{ __('messages.men') }}
                </button>
            </div>

            {{-- Women categories --}}
            <div x-show="gender === 'women'" x-transition.opacity class="space-y-4">
                <div class="mega-grid mobile-mega-grid">
                    @include('catalog._sections', ['sections' => $womenSections])
                </div>
            </div>

            {{-- Men categories --}}
            <div x-show="gender === 'men'" x-transition.opacity class="space-y-4">
                <div class="mega-grid mobile-mega-grid">
                    @include('catalog._sections', ['sections' => $menSections])
                </div>
            </div>
        </div>
    </section>
</nav>
