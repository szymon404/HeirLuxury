{{-- ABOUTME: Gallery management section for product edit form. --}}
{{-- ABOUTME: Multi-image upload, drag-to-reorder, delete, and primary selection. --}}

@if(!empty($product->id))
<div class="mt-6 rounded-2xl border border-white/10 bg-zinc-900/60 p-6"
     x-data="{
        dragging: null,
        dragOver: null,
        uploading: false,

        handleDragStart(event, id) {
            this.dragging = id;
            event.dataTransfer.effectAllowed = 'move';
        },
        handleDragOver(event, id) {
            event.preventDefault();
            this.dragOver = id;
        },
        handleDrop(event, targetId) {
            event.preventDefault();
            if (this.dragging === targetId) return;

            const items = [...document.querySelectorAll('[data-image-id]')];
            const ids = items.map(el => parseInt(el.dataset.imageId));

            const fromIdx = ids.indexOf(this.dragging);
            const toIdx = ids.indexOf(targetId);

            ids.splice(fromIdx, 1);
            ids.splice(toIdx, 0, this.dragging);

            this.dragging = null;
            this.dragOver = null;

            fetch('{{ route('admin.products.images.reorder', $product) }}', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({ order: ids }),
            }).then(() => window.location.reload());
        },
        handleDragEnd() {
            this.dragging = null;
            this.dragOver = null;
        },
     }">
    <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
        <svg class="h-4 w-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        Image Gallery
    </h3>

    {{-- Existing Gallery Images --}}
    @if($product->images->count() > 0)
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 mb-4">
            @foreach($product->images as $image)
                <div class="relative group rounded-xl overflow-hidden border-2 transition-all duration-150"
                     :class="{
                        'border-amber-400/50 ring-1 ring-amber-400/20': dragOver === {{ $image->id }},
                        'border-white/10': dragOver !== {{ $image->id }},
                        'opacity-50': dragging === {{ $image->id }},
                     }"
                     data-image-id="{{ $image->id }}"
                     draggable="true"
                     @dragstart="handleDragStart($event, {{ $image->id }})"
                     @dragover="handleDragOver($event, {{ $image->id }})"
                     @drop="handleDrop($event, {{ $image->id }})"
                     @dragend="handleDragEnd()">

                    {{-- Image --}}
                    @php
                        // Route admin gallery tiles through ThumbnailService so the
                        // grid loads optimized WebPs instead of the full originals.
                        // 'gallery' (800x800) matches the aspect-square container and
                        // looks crisp on retina at the rendered ~200-250px tile size.
                        $galleryTileUrl = app(\App\Services\ThumbnailService::class)
                            ->getUrl($image->path, 'gallery')
                            ?? asset('storage/' . $image->path);
                    @endphp
                    <div class="aspect-square bg-zinc-800">
                        <img src="{{ $galleryTileUrl }}"
                             alt="{{ $image->alt_text ?? $product->name }}"
                             width="800"
                             height="800"
                             loading="lazy"
                             decoding="async"
                             class="w-full h-full object-cover">
                    </div>

                    {{-- Primary Badge --}}
                    @if($image->is_primary)
                        <div class="absolute top-1.5 left-1.5">
                            <span class="inline-flex items-center rounded-full bg-amber-400/90 px-2 py-0.5 text-[10px] font-bold text-black">
                                PRIMARY
                            </span>
                        </div>
                    @endif

                    {{-- Filename --}}
                    <div class="px-2 py-1.5 bg-zinc-900/80 text-[10px] text-zinc-400 truncate">
                        {{ basename($image->path) }}
                    </div>

                    {{-- Action Buttons (visible on hover) --}}
                    <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                        {{-- Set as Primary --}}
                        @unless($image->is_primary)
                            <form method="POST" action="{{ route('admin.products.images.primary', [$product, $image]) }}">
                                @csrf
                                @method('PUT')
                                <button type="submit"
                                        title="Set as primary"
                                        class="h-8 w-8 rounded-full bg-amber-400/20 text-amber-400 flex items-center justify-center hover:bg-amber-400/40 transition-colors">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                    </svg>
                                </button>
                            </form>
                        @endunless

                        {{-- Delete --}}
                        <form method="POST" action="{{ route('admin.products.images.destroy', [$product, $image]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    title="Delete image"
                                    class="h-8 w-8 rounded-full bg-red-500/20 text-red-400 flex items-center justify-center hover:bg-red-500/40 transition-colors"
                                    onclick="return confirm('Delete this image?')">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                    </div>

                    {{-- Drag Handle --}}
                    <div class="absolute top-1.5 right-1.5 opacity-0 group-hover:opacity-100 transition-opacity cursor-grab active:cursor-grabbing">
                        <div class="h-6 w-6 rounded-full bg-black/60 flex items-center justify-center">
                            <svg class="h-3.5 w-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/>
                            </svg>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="text-xs text-zinc-500 mb-4">
            Drag images to reorder. The first image is the primary.
            <span class="text-zinc-400">{{ $product->images->count() }} image(s)</span>
        </p>
    @endif

    {{-- Upload Form (separate from main product form) --}}
    <form method="POST"
          action="{{ route('admin.products.images.store', $product) }}"
          enctype="multipart/form-data"
          class="border-t border-white/10 pt-4"
          x-data="{ fileCount: 0 }">
        @csrf

        <label class="block">
            <span class="text-xs font-medium text-zinc-400 mb-1 block">Add Images to Gallery</span>
            <input type="file"
                   name="images[]"
                   accept="image/*"
                   multiple
                   @change="fileCount = $event.target.files.length"
                   class="block w-full text-sm text-zinc-400
                          file:mr-3 file:rounded-full file:border-0
                          file:bg-amber-400 file:px-4 file:py-2 file:text-sm file:font-semibold
                          file:text-black hover:file:bg-amber-300 file:cursor-pointer
                          file:transition-colors">
        </label>

        <div x-show="fileCount > 0" class="mt-3">
            <p class="text-xs text-zinc-400 mb-2">
                <span x-text="fileCount"></span> file(s) selected
            </p>
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-full bg-amber-400 text-black px-4 py-2 text-sm font-medium hover:bg-amber-300 transition-colors">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Upload Images
            </button>
        </div>

        @error('images')
            <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
        @enderror
        @error('images.*')
            <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
        @enderror
    </form>
</div>
@endif
