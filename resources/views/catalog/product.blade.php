@extends('layouts.public')

@section('title', $product->name)

@section('content')
    {{-- Breadcrumbs --}}
    @if (!empty($breadcrumbs ?? null))
        <x-breadcrumbs :items="$breadcrumbs" />
    @endif

    <div class="max-w-7xl mx-auto px-4 py-10 grid grid-cols-1 lg:grid-cols-2 gap-10">

        {{-- LEFT: PRODUCT IMAGE GALLERY --}}
        <div class="min-w-0">
            <x-product.gallery :images="$images" :product-id="$product->id" class="max-w-xl" />
        </div>

        {{-- RIGHT: PRODUCT DETAILS --}}
        <div class="space-y-6 text-white">
            <h1 class="text-3xl font-bold">{{ $product->name }}</h1>



        </div>
    </div>

    {{-- RELATED ITEMS --}}
    @if($related->count())
        <section class="max-w-7xl mx-auto px-4 pb-10 mt-16 text-white">
            <h2 class="text-xl font-semibold mb-4">More in this category</h2>

            <div class="grid gap-6 grid-cols-1 sm:grid-cols-2 md:grid-cols-3">
                @foreach($related as $item)
                    <div class="h-full">
                        <x-product.card :product="$item" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endsection
