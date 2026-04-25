@csrf

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Main Content Column --}}
    <div class="lg:col-span-2 space-y-6">
        {{-- Basic Information --}}
        <div class="rounded-2xl border border-white/10 bg-zinc-900/60 p-6">
            <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                <svg class="h-4 w-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Basic Information
            </h3>

            <div class="space-y-4">
                {{-- Name --}}
                <div>
                    <label for="name" class="block text-xs font-medium text-zinc-400 mb-1">
                        Product Name <span class="text-red-400">*</span>
                    </label>
                    <input type="text"
                           id="name"
                           name="name"
                           value="{{ old('name', $product->name ?? '') }}"
                           placeholder="e.g., Neverfull MM Tote Bag"
                           class="w-full rounded-lg bg-zinc-800 border border-white/10 px-3 py-2.5 text-sm text-white placeholder-zinc-500 outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400/50 transition-colors"
                           required>
                    @error('name')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Slug --}}
                <div>
                    <label for="slug" class="block text-xs font-medium text-zinc-400 mb-1">
                        URL Slug
                        <span class="text-zinc-500 font-normal">(optional)</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-zinc-500">/catalog/.../</span>
                        <input type="text"
                               id="slug"
                               name="slug"
                               value="{{ old('slug', $product->slug ?? '') }}"
                               placeholder="auto-generated-from-name"
                               class="flex-1 rounded-lg bg-zinc-800 border border-white/10 px-3 py-2.5 text-sm text-white placeholder-zinc-500 outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400/50 transition-colors">
                    </div>
                    <p class="mt-1 text-xs text-zinc-500">
                        Leave empty to auto-generate from the product name.
                    </p>
                    @error('slug')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Classification --}}
        <div class="rounded-2xl border border-white/10 bg-zinc-900/60 p-6">
            <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                <svg class="h-4 w-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
                Classification
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Brand --}}
                <div>
                    <label for="brand" class="block text-xs font-medium text-zinc-400 mb-1">Brand</label>
                    <select id="brand"
                            name="brand"
                            class="w-full rounded-lg bg-zinc-800 border border-white/10 px-3 py-2.5 text-sm text-white outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400/50 transition-colors">
                        <option value="">— Select Brand —</option>
                        @php
                            $brands = ['Louis Vuitton', 'Chanel', 'Dior', 'Hermès', 'Gucci', 'Celine', 'Prada', 'YSL'];
                            $currentBrand = old('brand', $product->brand ?? '');
                        @endphp
                        @foreach($brands as $brand)
                            <option value="{{ $brand }}" @selected($currentBrand === $brand)>{{ $brand }}</option>
                        @endforeach
                    </select>
                    @error('brand')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Gender --}}
                <div>
                    <label for="gender" class="block text-xs font-medium text-zinc-400 mb-1">Gender</label>
                    <select id="gender"
                            name="gender"
                            class="w-full rounded-lg bg-zinc-800 border border-white/10 px-3 py-2.5 text-sm text-white outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400/50 transition-colors">
                        <option value="">— Select Gender —</option>
                        @php
                            $genders = ['women' => 'Women', 'men' => 'Men'];
                            $currentGender = old('gender', $product->gender ?? '');
                        @endphp
                        @foreach($genders as $value => $label)
                            <option value="{{ $value }}" @selected($currentGender === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('gender')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Section --}}
                <div>
                    <label for="section" class="block text-xs font-medium text-zinc-400 mb-1">Section</label>
                    <select id="section"
                            name="section"
                            class="w-full rounded-lg bg-zinc-800 border border-white/10 px-3 py-2.5 text-sm text-white outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400/50 transition-colors">
                        <option value="">— Select Section —</option>
                        @php
                            $sections = ['bags' => 'Bags', 'shoes' => 'Shoes', 'clothes' => 'Clothes', 'accessories' => 'Accessories'];
                            $currentSection = old('section', $product->section ?? '');
                        @endphp
                        @foreach($sections as $value => $label)
                            <option value="{{ $value }}" @selected($currentSection === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('section')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Category --}}
                <div>
                    <label for="category_slug" class="block text-xs font-medium text-zinc-400 mb-1">Category</label>
                    <select id="category_slug"
                            name="category_slug"
                            class="w-full rounded-lg bg-zinc-800 border border-white/10 px-3 py-2.5 text-sm text-white outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400/50 transition-colors">
                        <option value="">— Select Category —</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->slug }}"
                                @selected(old('category_slug', $product->category_slug ?? '') === $category->slug)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('category_slug')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Storage Location --}}
        <div class="rounded-2xl border border-white/10 bg-zinc-900/60 p-6">
            <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                <svg class="h-4 w-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
                Storage Location
            </h3>

            <div>
                <label for="folder" class="block text-xs font-medium text-zinc-400 mb-1">
                    Image Folder
                    <span class="text-zinc-500 font-normal">(for imported products)</span>
                </label>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-zinc-500">imports/</span>
                    <input type="text"
                           id="folder"
                           name="folder"
                           value="{{ old('folder', $product->folder ?? '') }}"
                           placeholder="lv-bags-women/product-name"
                           class="flex-1 rounded-lg bg-zinc-800 border border-white/10 px-3 py-2.5 text-sm text-white placeholder-zinc-500 outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400/50 transition-colors">
                </div>
                <p class="mt-1 text-xs text-zinc-500">
                    Path to the product's image folder within storage/app/public/imports/
                </p>
                @error('folder')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    {{-- Sidebar Column --}}
    @php
        // Route preview thumbnails through ThumbnailService so the form
        // sidebar loads a small WebP instead of the full original. 'card'
        // (400x300 WebP) is plenty for the ~250-350px preview tile.
        $thumbnailService = app(\App\Services\ThumbnailService::class);
        $imagePreviewUrl = !empty($product->image)
            ? ($thumbnailService->getUrl($product->image, 'card') ?? asset('storage/'.$product->image))
            : '';
        $thumbPreviewUrl = !empty($product->thumbnail)
            ? ($thumbnailService->getUrl($product->thumbnail, 'card') ?? asset('storage/'.$product->thumbnail))
            : '';
    @endphp
    <div class="space-y-6">
        {{-- Image Upload --}}
        <div class="rounded-2xl border border-white/10 bg-zinc-900/60 p-6"
             x-data="{
                imagePreview: '{{ $imagePreviewUrl }}',
                fileName: '',
                handleFileSelect(event) {
                    const file = event.target.files[0];
                    if (file) {
                        this.fileName = file.name;
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            this.imagePreview = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                }
             }">
            <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                <svg class="h-4 w-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Product Image
            </h3>

            {{-- Image Preview --}}
            <div class="mb-4">
                <div class="aspect-square rounded-xl bg-zinc-800 border-2 border-dashed border-white/10 overflow-hidden flex items-center justify-center"
                     :class="{ 'border-solid border-amber-400/50': imagePreview }">
                    <template x-if="imagePreview">
                        <img :src="imagePreview" alt="Preview" class="w-full h-full object-cover">
                    </template>
                    <template x-if="!imagePreview">
                        <div class="text-center p-4">
                            <svg class="mx-auto h-12 w-12 text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="mt-2 text-xs text-zinc-500">No image selected</p>
                        </div>
                    </template>
                </div>
            </div>

            {{-- File Input --}}
            <label class="block">
                <span class="sr-only">Choose product image</span>
                <input type="file"
                       name="image"
                       accept="image/*"
                       @change="handleFileSelect($event)"
                       class="block w-full text-sm text-zinc-400
                              file:mr-3 file:rounded-full file:border-0
                              file:bg-amber-400 file:px-4 file:py-2 file:text-sm file:font-semibold
                              file:text-black hover:file:bg-amber-300 file:cursor-pointer
                              file:transition-colors">
            </label>

            {{-- File Name Display --}}
            <p x-show="fileName" x-text="fileName" class="mt-2 text-xs text-zinc-400 truncate"></p>

            {{-- Current Image Info --}}
            @if(!empty($product->image))
                <div class="mt-3 pt-3 border-t border-white/10">
                    <p class="text-xs text-zinc-500">
                        Current: <span class="text-zinc-400">{{ basename($product->image) }}</span>
                    </p>
                </div>
            @endif

            @error('image')
            <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Card Thumbnail Upload --}}
        <div class="rounded-2xl border border-white/10 bg-zinc-900/60 p-6"
             x-data="{
                thumbPreview: '{{ $thumbPreviewUrl }}',
                thumbFileName: '',
                handleThumbSelect(event) {
                    const file = event.target.files[0];
                    if (file) {
                        this.thumbFileName = file.name;
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            this.thumbPreview = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                }
             }">
            <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                <svg class="h-4 w-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                </svg>
                Card Thumbnail
            </h3>

            <p class="text-xs text-zinc-500 mb-3">
                Upload a custom card image. Auto-converted to 400×300 WebP.
            </p>

            {{-- Thumbnail Preview --}}
            <div class="mb-4">
                <div class="aspect-[4/3] rounded-xl bg-zinc-800 border-2 border-dashed border-white/10 overflow-hidden flex items-center justify-center"
                     :class="{ 'border-solid border-amber-400/50': thumbPreview }">
                    <template x-if="thumbPreview">
                        <img :src="thumbPreview" alt="Thumbnail preview" class="w-full h-full object-cover">
                    </template>
                    <template x-if="!thumbPreview">
                        <div class="text-center p-4">
                            <svg class="mx-auto h-10 w-10 text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6z"/>
                            </svg>
                            <p class="mt-2 text-xs text-zinc-500">No thumbnail</p>
                        </div>
                    </template>
                </div>
            </div>

            {{-- File Input --}}
            <label class="block">
                <span class="sr-only">Choose card thumbnail</span>
                <input type="file"
                       name="thumbnail"
                       accept="image/*"
                       @change="handleThumbSelect($event)"
                       class="block w-full text-sm text-zinc-400
                              file:mr-3 file:rounded-full file:border-0
                              file:bg-amber-400 file:px-4 file:py-2 file:text-sm file:font-semibold
                              file:text-black hover:file:bg-amber-300 file:cursor-pointer
                              file:transition-colors">
            </label>

            {{-- File Name Display --}}
            <p x-show="thumbFileName" x-text="thumbFileName" class="mt-2 text-xs text-zinc-400 truncate"></p>

            {{-- Current Thumbnail Info --}}
            @if(!empty($product->thumbnail))
                <div class="mt-3 pt-3 border-t border-white/10">
                    <p class="text-xs text-zinc-500">
                        Current: <span class="text-zinc-400">{{ basename($product->thumbnail) }}</span>
                    </p>
                </div>
            @endif

            @error('thumbnail')
            <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Product Status Card (for edit mode) --}}
        @if(!empty($product->id))
            <div class="rounded-2xl border border-white/10 bg-zinc-900/60 p-6">
                <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                    <svg class="h-4 w-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Product Info
                </h3>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">ID</dt>
                        <dd class="text-zinc-300 font-mono">{{ $product->id }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">Created</dt>
                        <dd class="text-zinc-300">{{ $product->created_at->format('M j, Y') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">Updated</dt>
                        <dd class="text-zinc-300">{{ $product->updated_at->format('M j, Y') }}</dd>
                    </div>
                </dl>

                @if($product->category)
                    <div class="mt-4 pt-4 border-t border-white/10">
                        <a href="{{ route('product.show', ['category' => $product->category->slug, 'productSlug' => $product->slug]) }}"
                           target="_blank"
                           class="inline-flex items-center gap-2 text-xs text-amber-400 hover:text-amber-300 transition-colors">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                            View on site
                        </a>
                    </div>
                @endif
            </div>
        @endif

        {{-- Help Card --}}
        <div class="rounded-2xl border border-white/10 bg-gradient-to-br from-amber-500/10 to-amber-600/5 p-6">
            <h3 class="text-sm font-semibold text-white mb-2">Need Help?</h3>
            <p class="text-xs text-zinc-400 leading-relaxed">
                Products are organized by brand, gender, and section. The category determines where the product appears in the catalog navigation.
            </p>
            <ul class="mt-3 space-y-1 text-xs text-zinc-500">
                <li class="flex items-center gap-2">
                    <span class="h-1 w-1 rounded-full bg-amber-400"></span>
                    Name is required
                </li>
                <li class="flex items-center gap-2">
                    <span class="h-1 w-1 rounded-full bg-amber-400"></span>
                    Slug auto-generates if empty
                </li>
                <li class="flex items-center gap-2">
                    <span class="h-1 w-1 rounded-full bg-amber-400"></span>
                    Images should be JPG/PNG/WebP
                </li>
            </ul>
        </div>
    </div>
</div>
