@extends('admin.layouts.app')

@section('title', 'Products')

@section('content')
    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold text-white">Products</h2>
            <p class="text-sm text-zinc-400">Manage your product catalog</p>
        </div>
        <a href="{{ route('admin.products.create') }}"
           class="inline-flex items-center gap-2 rounded-full bg-amber-400 text-black px-4 py-2 text-sm font-medium hover:bg-amber-300 transition-colors">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Product
        </a>
    </div>

    {{-- Status Messages --}}
    @if(session('status'))
        <div class="mb-4 rounded-lg border border-emerald-400/40 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200 flex items-center gap-2">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('status') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 rounded-lg border border-red-400/40 bg-red-400/10 px-4 py-3 text-sm text-red-200 flex items-center gap-2">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Search & Filters --}}
    <div class="mb-6 rounded-2xl border border-white/10 bg-zinc-900/60 p-4">
        <form method="GET" action="{{ route('admin.products.index') }}" class="flex flex-col sm:flex-row gap-3">
            {{-- Search --}}
            <div class="flex-1">
                <label for="search" class="sr-only">Search products</label>
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text"
                           id="search"
                           name="search"
                           value="{{ request('search') }}"
                           placeholder="Search by name or slug..."
                           class="w-full rounded-lg bg-zinc-800 border border-white/10 pl-10 pr-4 py-2 text-sm text-white placeholder-zinc-500 outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400/50 transition-colors">
                </div>
            </div>

            {{-- Brand Filter --}}
            <div class="sm:w-40">
                <label for="brand" class="sr-only">Filter by brand</label>
                <select id="brand"
                        name="brand"
                        class="w-full rounded-lg bg-zinc-800 border border-white/10 px-3 py-2 text-sm text-white outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400/50 transition-colors">
                    <option value="">All Brands</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand }}" @selected(request('brand') === $brand)>{{ $brand }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Gender Filter --}}
            <div class="sm:w-32">
                <label for="gender" class="sr-only">Filter by gender</label>
                <select id="gender"
                        name="gender"
                        class="w-full rounded-lg bg-zinc-800 border border-white/10 px-3 py-2 text-sm text-white outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400/50 transition-colors">
                    <option value="">All Genders</option>
                    @foreach($genders as $gender)
                        <option value="{{ $gender }}" @selected(request('gender') === $gender)>{{ ucfirst($gender) }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Submit --}}
            <button type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-zinc-700 text-white px-4 py-2 text-sm font-medium hover:bg-zinc-600 transition-colors border border-white/10">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                Filter
            </button>

            @if(request()->hasAny(['search', 'brand', 'gender']))
                <a href="{{ route('admin.products.index') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-lg bg-zinc-800 text-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-700 transition-colors border border-white/10">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Clear
                </a>
            @endif
        </form>
    </div>

    {{-- Products Table --}}
    <div x-data="{ selectedProducts: [], selectAll: false }" class="rounded-2xl border border-white/10 bg-zinc-900/60 overflow-hidden">
        {{-- Bulk Actions Bar --}}
        <div x-show="selectedProducts.length > 0"
             x-cloak
             class="bg-amber-400/10 border-b border-amber-400/20 px-4 py-3 flex items-center justify-between">
            <span class="text-sm text-amber-200">
                <span x-text="selectedProducts.length"></span> product(s) selected
            </span>
            <form method="POST" action="{{ route('admin.products.bulk-destroy') }}" class="inline">
                @csrf
                @method('DELETE')
                <template x-for="id in selectedProducts" :key="id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>
                <button type="submit"
                        onclick="return confirm('Delete selected products? This cannot be undone.')"
                        class="inline-flex items-center gap-2 rounded-full bg-red-500/20 text-red-400 px-3 py-1.5 text-xs font-medium hover:bg-red-500/30 transition-colors">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Delete Selected
                </button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-zinc-800/50">
                @php
                    // Build sort URL helper: toggles direction if already sorting by this column
                    $sortUrl = function (string $column) use ($sort, $direction) {
                        $newDirection = ($sort === $column && $direction === 'asc') ? 'desc' : 'asc';
                        return request()->fullUrlWithQuery(['sort' => $column, 'direction' => $newDirection]);
                    };
                    $sortIcon = function (string $column) use ($sort, $direction) {
                        if ($sort !== $column) return '';
                        return $direction === 'asc' ? '▲' : '▼';
                    };
                @endphp
                <tr class="text-left text-xs uppercase text-zinc-400">
                    <th class="px-4 py-3 w-10">
                        <input type="checkbox"
                               x-model="selectAll"
                               @change="selectedProducts = selectAll ? {{ $products->pluck('id') }} : []"
                               class="rounded border-white/20 bg-zinc-700 text-amber-400 focus:ring-amber-400/50">
                    </th>
                    <th class="px-4 py-3 w-16">Image</th>
                    <th class="px-4 py-3">
                        <a href="{{ $sortUrl('name') }}" class="inline-flex items-center gap-1 hover:text-white transition-colors">
                            Name <span class="text-amber-400">{{ $sortIcon('name') }}</span>
                        </a>
                    </th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">
                        <a href="{{ $sortUrl('brand') }}" class="inline-flex items-center gap-1 hover:text-white transition-colors">
                            Brand <span class="text-amber-400">{{ $sortIcon('brand') }}</span>
                        </a>
                    </th>
                    <th class="px-4 py-3">
                        <a href="{{ $sortUrl('gender') }}" class="inline-flex items-center gap-1 hover:text-white transition-colors">
                            Gender <span class="text-amber-400">{{ $sortIcon('gender') }}</span>
                        </a>
                    </th>
                    <th class="px-4 py-3">
                        <a href="{{ $sortUrl('created_at') }}" class="inline-flex items-center gap-1 hover:text-white transition-colors">
                            Added <span class="text-amber-400">{{ $sortIcon('created_at') }}</span>
                        </a>
                    </th>
                    <th class="px-4 py-3 w-32 text-right">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                @forelse($products as $product)
                    <tr class="hover:bg-white/5 transition-colors">
                        <td class="px-4 py-3">
                            <input type="checkbox"
                                   value="{{ $product->id }}"
                                   x-model="selectedProducts"
                                   class="rounded border-white/20 bg-zinc-700 text-amber-400 focus:ring-amber-400/50">
                        </td>
                        <td class="px-4 py-3">
                            @if($product->image)
                                @php
                                    // Route admin list thumbnails through ThumbnailService so the
                                    // table loads small WebPs (~5 KB) instead of the original
                                    // multi-MB JPEGs. The 'thumb' size (96x96) matches the
                                    // h-10 w-10 (40x40) display, including retina (~80px).
                                    $imagePath = $product->image_path ?? $product->image;
                                    $adminThumbUrl = app(\App\Services\ThumbnailService::class)
                                        ->getUrl($imagePath, 'thumb')
                                        ?? asset('storage/' . $imagePath);
                                @endphp
                                <img src="{{ $adminThumbUrl }}"
                                     alt="{{ $product->name }}"
                                     width="96"
                                     height="96"
                                     loading="lazy"
                                     decoding="async"
                                     class="h-10 w-10 rounded-lg object-cover">
                            @else
                                <div class="h-10 w-10 rounded-lg bg-zinc-800 flex items-center justify-center">
                                    <svg class="h-5 w-5 text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-white">{{ $product->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $product->slug }}</div>
                        </td>
                        <td class="px-4 py-3 text-zinc-400">
                            {{ optional($product->category)->name ?? $product->category_slug ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-zinc-400">{{ $product->brand ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if($product->gender)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $product->gender === 'women' ? 'bg-pink-400/20 text-pink-300' : 'bg-blue-400/20 text-blue-300' }}">
                                    {{ ucfirst($product->gender) }}
                                </span>
                            @else
                                <span class="text-zinc-500">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-zinc-500 whitespace-nowrap">
                            {{ $product->created_at->format('M j, Y') }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.products.edit', $product) }}"
                                   class="inline-flex items-center gap-1 text-xs text-amber-400 hover:text-amber-300 transition-colors">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Edit
                                </a>
                                <form action="{{ route('admin.products.destroy', $product) }}"
                                      method="POST"
                                      class="inline"
                                      onsubmit="return confirm('Delete this product?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="inline-flex items-center gap-1 text-xs text-red-400 hover:text-red-300 transition-colors">
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12">
                            <div class="text-center">
                                <svg class="mx-auto h-12 w-12 text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                @if(request()->hasAny(['search', 'brand', 'gender']))
                                    <h3 class="mt-4 text-sm font-medium text-white">No products match your search</h3>
                                    <p class="mt-1 text-sm text-zinc-400">Try adjusting your filters or search terms.</p>
                                    <a href="{{ route('admin.products.index') }}"
                                       class="mt-4 inline-flex items-center gap-2 text-sm text-amber-400 hover:text-amber-300">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Clear filters
                                    </a>
                                @else
                                    <h3 class="mt-4 text-sm font-medium text-white">No products found</h3>
                                    <p class="mt-1 text-sm text-zinc-400">Get started by creating your first product.</p>
                                    <a href="{{ route('admin.products.create') }}"
                                       class="mt-4 inline-flex items-center gap-2 rounded-full bg-amber-400 text-black px-4 py-2 text-sm font-medium hover:bg-amber-300 transition-colors">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Create Product
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    @if($products->hasPages())
        <div class="mt-6">
            {{ $products->links() }}
        </div>
    @endif
@endsection
