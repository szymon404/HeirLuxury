{{-- ABOUTME: Desktop mega menu panel with gender toggle and category columns.
    ABOUTME: Delegates category rendering to the _sections partial for reuse in mobile. --}}

@php
    // Build sections from database categories (falls back to config if DB is empty)
    $navData = \App\Models\Category::getNavigationData();
    $configData = config('categories', []);

    $womenSections = $womenSections
        ?? collect(!empty($navData['women']) ? $navData['women'] : ($configData['women'] ?? []));

    $menSections = $menSections
        ?? collect(!empty($navData['men']) ? $navData['men'] : ($configData['men'] ?? []));
@endphp


<div x-data="{ gender: 'women' }" class="mega-shell">
    {{-- GENDER TOGGLE (centered) --}}
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

    {{-- WOMEN PANEL --}}
    <div
        x-show="gender === 'women'"
        x-transition.opacity
        class="mega-grid"
    >
        @include('catalog._sections', ['sections' => $womenSections])
    </div>

    {{-- MEN PANEL (centered) --}}
    <div
        x-show="gender === 'men'"
        x-transition.opacity
        class="mega-grid justify-center"
        style="grid-template-columns: repeat({{ $menSections->count() }}, minmax(0, max-content));"
    >
        @include('catalog._sections', ['sections' => $menSections])
    </div>
</div>
