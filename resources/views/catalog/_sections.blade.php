{{-- ABOUTME: Reusable partial that renders category columns for a given set of sections.
    ABOUTME: Used by both the desktop mega menu and the mobile slide-up sheet. --}}

@php
    $locale = app()->getLocale();

    // Helper to translate section labels (bags, shoes, clothes) but not brand names
    $translateSection = function($label) use ($locale) {
        $key = 'messages.' . strtolower($label);
        $translated = __($key, [], $locale);
        return $translated !== $key ? $translated : $label;
    };

    // Helper to translate item names (e.g., "Louis Vuitton Bags" → "Louis Vuitton Torby")
    // Translates category words (Bags, Shoes, Clothing, etc.) and gender (Women, Men) but keeps brand names
    $translateItemName = function($name) use ($locale) {
        $translations = [
            'Bags' => __('messages.bags', [], $locale),
            'Shoes' => __('messages.shoes', [], $locale),
            'Clothing' => __('messages.clothing', [], $locale),
            'Belts' => __('messages.belts', [], $locale),
            'Glasses' => __('messages.glasses', [], $locale),
            'Jewelry' => __('messages.jewelry', [], $locale),
            'Watches' => __('messages.watches', [], $locale),
            'Women' => __('messages.women', [], $locale),
            'Men' => __('messages.men', [], $locale),
        ];

        $result = $name;
        foreach ($translations as $english => $translated) {
            if ($translated !== 'messages.' . strtolower($english)) {
                $result = str_replace($english, $translated, $result);
            }
        }
        return $result;
    };
@endphp

@foreach ($sections as $sectionLabel => $items)
    <section class="mega-col">
        <h3 class="mega-col-title">
            {{ strtoupper($translateSection($sectionLabel)) }}
        </h3>

        <ul class="mega-list">
            @foreach ($items as $item)
                @php
                    // Normalise legacy config so everything goes through `catalog.category`
                    if (!empty($item['route'])) {
                        $routeName = $item['route'];
                        $params    = $item['params'] ?? [];

                        // If config still says `categories.show`, convert it to the new canonical route
                        // and map its old `slug` param to the new `{category:slug}` binding.
                        if ($routeName === 'categories.show') {
                            $routeName = 'catalog.category';

                            // Legacy config: ['slug' => 'louis-vuitton-women-bags']
                            if (isset($params['slug'])) {
                                $params = ['category' => $params['slug']];
                            }
                        }

                        // Add locale parameter
                        $params['locale'] = $locale;

                        $url = route($routeName, $params);
                    } else {
                        // Fallback: plain href or '#'
                        $url = $item['href'] ?? '#';
                    }
                @endphp

                <li>
                    <a href="{{ $url }}">
                        {{ $translateItemName($item['label'] ?? $item['name'] ?? 'Unnamed') }}
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endforeach
