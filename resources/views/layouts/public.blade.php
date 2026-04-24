{{-- resources/views/layouts/public.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'HeirLuxury')</title>

    {{-- Favicon --}}
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/icons/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/favicon-180x180.png">

    {{-- Tailwind + app.js via Vite (includes Alpine.js with stores) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-50 antialiased">

    @include('layouts.navbar', ['introNav' => $introNav ?? false])

    @yield('before-main')

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        @yield('content')
    </main>

    {{-- contact modal --}}
    @include('contact.modal')

    {{-- Mobile scroll-to-top button --}}
    <button
        x-data="{ visible: false }"
        x-on:scroll.window.passive="visible = window.scrollY > 400"
        x-show="visible"
        x-transition.opacity.duration.200ms
        x-cloak
        @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
        type="button"
        class="fixed bottom-6 right-6 z-50 lg:hidden w-12 h-12 flex items-center justify-center
               rounded-full bg-amber-400 text-black shadow-lg shadow-amber-400/30
               active:scale-95 transition-transform"
        aria-label="Scroll to top"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
        </svg>
    </button>

</body>
</html>
