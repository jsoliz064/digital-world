<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">

            <x-slot name="title">
                Editar Producto: {{ $producto['nombre'] ?? '' }}
            </x-slot>
            <x-slot name="content">
                <div class="space-y-6">
                    <div class="grid grid-cols-1 gap-6">

                        <div class="animate-fade-in">
                            <x-label value="Descripción Automática" />
                            <div class="mt-2 p-3 bg-white dark:bg-gray-700 rounded text-gray-800 dark:text-gray-200">
                                @if ($descripcion)
                                    {{ $descripcion }}
                                @else
                                    <span class="text-gray-400 italic">Complete los campos para generar la
                                        descripción</span>
                                @endif
                            </div>
                            <input type="hidden" wire:model="descripcion">
                        </div>

                        <div class="animate-fade-in">
                            <x-label value="Almacenamiento *" />
                            <select wire:model.live="producto.almacenamiento"
                                class="mt-2 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                               focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm transition-all duration-200 h-10">
                                @if ($selectedModel && $selectedModel->almacenamientos)
                                    @foreach ($selectedModel->almacenamientos as $storage)
                                        <option value="{{ $storage->almacenamiento }}"
                                            @if ($producto['almacenamiento'] == $storage->almacenamiento) selected @endif>
                                            {{ $storage->almacenamiento }} - ${{ number_format($storage->precio, 2) }}
                                        </option>
                                    @endforeach
                                @else
                                    <option value="">No hay opciones de almacenamiento disponibles</option>
                                @endif
                            </select>
                            <x-input-error for="producto.almacenamiento" class="mt-1" />
                        </div>

                        {{-- Version --}}
                        <div class="animate-fade-in">
                            <x-label value="Versión" />
                            <select wire:model.live="producto.version"
                                class="mt-2 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                               focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm transition-all duration-200 h-10">
                                <option value="">Seleccione una versión</option>
                                @foreach (App\Enums\ProductoVersion::cases() as $version)
                                    <option value="{{ $version->value }}"
                                        @if (($producto['version'] ?? '') == $version->value) selected @endif>
                                        {{ $version->value }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error for="producto.version" class="mt-1" />
                        </div>


                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 animate-fade-in">
                            <div>
                                <x-label value="Costo Unitario ($) *" />
                                <x-input wire:model.lazy="producto.costo_unidad" type="number" step="0.01"
                                    class="mt-2 block w-full h-10 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                                       focus:border-brand-500 focus:ring-brand-500"
                                    onfocus="this.select()" placeholder="0.00" />
                                <x-input-error for="producto.costo_unidad" class="mt-1" />
                            </div>

                            <div>
                                <x-label value="Costo Envío ($) *" />
                                <x-input wire:model.lazy="producto.costo_envio" type="number" step="0.01"
                                    class="mt-2 block w-full h-10 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                                       focus:border-brand-500 focus:ring-brand-500"
                                    onfocus="this.select()" placeholder="0.00" />
                                <x-input-error for="producto.costo_envio" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 animate-fade-in">

                            <div>
                                <x-label value="Costo Reparacion ($)" />
                                <x-input wire:model="producto.costo_reparacion" type="number"
                                    class="mt-2 block w-full h-10 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                                       focus:border-brand-500 focus:ring-brand-500"
                                    disabled="true" />
                                <x-input-error for="producto.costo_reparacion" class="mt-1" />
                            </div>

                            <div>
                                <x-label value="Costo Total ($)" />
                                <x-input wire:model="producto.costo_total" type="number"
                                    class="mt-2 block w-full h-10 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                                       focus:border-brand-500 focus:ring-brand-500"
                                    disabled="true" />
                                <x-input-error for="producto.costo_total" class="mt-1" />
                            </div>

                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 animate-fade-in">
                            <div>
                                <x-label value="Precio Vendedor ($) *" />
                                <x-input wire:model="producto.precio_vendedor" type="number" step="0.01"
                                    class="mt-2 block w-full h-10 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                                       focus:border-brand-500 focus:ring-brand-500"
                                    placeholder="0.00" />
                                <x-input-error for="producto.precio_vendedor" class="mt-1" />
                            </div>

                            <div>
                                <x-label value="Precio Cliente ($) *" />
                                <x-input wire:model="producto.precio_cliente" type="number" step="0.01"
                                    class="mt-2 block w-full h-10 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                                       focus:border-brand-500 focus:ring-brand-500"
                                    placeholder="0.00" />
                                <x-input-error for="producto.precio_cliente" class="mt-1" />
                            </div>

                        </div>

                        <div class="animate-fade-in">
                            <x-label value="Color *" />
                            <div class="grid grid-cols-3 gap-3 mt-2">
                                @foreach ($colores as $color)
                                    <label
                                        class="inline-flex items-center border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-2xl">
                                        <input type="radio" wire:model.live="producto.color"
                                            value="{{ $color->value }}" class="hidden peer">
                                        <div class="w-full px-4 py-2 rounded-full border-2 peer-checked:border-brand-500 peer-checked:ring-2 
                                                   peer-checked:ring-brand-200 dark:peer-checked:ring-brand-800 transition-all duration-200
                                                   cursor-pointer hover:shadow-md flex items-center justify-center"
                                            style="background-color: var(--color-{{ strtolower($color->value) }});">
                                            <span class="text-xs font-medium">{{ $color->value }}</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error for="producto.color" class="mt-1" />
                        </div>

                        <div class="m-2">
                            <x-label>Detalles Seleccionados:</x-label>
                            <textarea wire:model.defer="producto.detalles" rows="3"
                                class="w-full mt-1 p-2 border border-gray-300 rounded-lg shadow-sm resize-none bg-gray-50 text-gray-700"></textarea>
                        </div>

                        <div class="animate-fade-in">
                            <x-label value="IMEI *" />
                            <x-input wire:model.live="producto.imei" type="text"
                                class="mt-2 block w-full h-10 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                                       focus:border-brand-500 focus:ring-brand-500"
                                placeholder="Ingrese al menos 4 digitos" />
                            <x-input-error for="producto.imei" class="mt-1" />
                        </div>

                        <div class="animate-fade-in">
                            <x-label value="Batería (%) *" />
                            <div class="flex items-center mt-2 space-x-4">
                                <x-input wire:model.live="producto.bateria_porcentaje" type="number" min="0"
                                    max="100" onfocus="this.select()"
                                    oninput="this.value = Math.max(0, Math.min(100, parseInt(this.value) || 0))"
                                    class="block h-10 w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                                       focus:border-brand-500 focus:ring-brand-500"
                                    placeholder="0-100" />
                                <span class="text-gray-700 dark:text-gray-300">%</span>
                            </div>
                            <x-input-error for="producto.bateria_porcentaje" class="mt-1" />
                        </div>

                        <div class="animate-fade-in">
                            <x-label for="estado_grado" value="Condición del Producto *" />
                            <select wire:model.live="producto.estado_grado" id="estado_grado"
                                class="mt-2 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
               focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm transition-all duration-200 h-10">
                                <option value="">Seleccione una condición</option>
                                <option value="A+" @if ($producto['estado_grado'] == 'A+') selected @endif>
                                    A+
                                </option>
                                <option value="A" @if ($producto['estado_grado'] == 'A') selected @endif>
                                    A
                                </option>
                                <option value="AB" @if ($producto['estado_grado'] == 'AB') selected @endif>
                                    AB
                                </option>
                                <option value="B" @if ($producto['estado_grado'] == 'B') selected @endif>
                                    B
                                </option>
                                <option value="C" @if ($producto['estado_grado'] == 'C') selected @endif>
                                    C
                                </option>
                                <option value="D" @if ($producto['estado_grado'] == 'D') selected @endif>
                                    D
                                </option>
                            </select>
                            <x-input-error for="producto.estado_grado" class="mt-1" />
                        </div>

                        <div class="animate-fade-in">
                            <x-label value="Estado *" />
                            <x-input wire:model="producto.status" class="w-full" disabled="true"></x-input>
                        </div>

                        <div class="animate-fade-in">
                            <x-label for="sucursal_id" value="Sucursal *" />
                            <select wire:model.live="producto.sucursal_id" id="sucursal_id"
                                class="mt-2 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                                       focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm transition-all duration-200 h-10">
                                @foreach ($sucursales as $sucursal)
                                    <option value="{{ $sucursal->id }}"
                                        @if ($producto['sucursal_id'] == $sucursal->id) selected @endif>
                                        {{ $sucursal->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error for="producto.sucursal_id" class="mt-1" />
                        </div>

                        <div class="animate-fade-in mt-4">
                            <div class="flex items-between">
                                <input type="checkbox" wire:model="producto.disponible_catalogo"
                                    class="form-checkbox h-5 w-5 text-brand-600 transition duration-150 ease-in-out rounded dark:bg-gray-700 dark:border-gray-600">
                                <span class="mr-10 text-gray-700 dark:text-gray-200">Mostrar en Catálogo</span>
                                <x-input-error for="producto.disponible_catalogo" class="mt-1" />

                                <input type="checkbox" wire:model="producto.sin_reparacion"
                                    class="form-checkbox h-5 w-5 text-brand-600 transition duration-150 ease-in-out rounded dark:bg-gray-700 dark:border-gray-600">
                                <span class="text-gray-700 dark:text-gray-200">DOA</span>
                                <x-input-error for="producto.sin_reparacion" class="mt-1" />
                            </div>
                        </div>

                        <div class="animate-fade-in">
                            <x-label value="Fotos del Producto" />
                            <div class="mt-2">
                                <button type="button" onclick="CameraHandler.initCamera('cameraModalEdit')"
                                    class="w-full flex items-center justify-center px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-md 
                                       transition-all duration-300 hover:scale-105 shadow-md">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Tomar Fotos
                                </button>

                                @if (count($photos) > 0)
                                    <div class="animate-fade-in mt-4" x-data="photoCarousel()"
                                        x-init="init({{ json_encode($photos) }})">
                                        <x-label x-text="`Fotos del Producto (${photos.length})`" />

                                        <div class="relative mt-2">
                                            <div
                                                class="relative overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800 p-2">
                                                <div class="relative h-64">
                                                    <template x-for="(photo, index) in photos" :key="`photo-${index}`">
                                                        <div class="transition-opacity duration-300 ease-in-out absolute inset-0 flex items-center justify-center p-2"
                                                            :style="`opacity: ${currentPhotoIndex === index ? '1' : '0'};`">
                                                            <img :src="photo"
                                                                class="max-h-full max-w-full object-contain rounded-lg shadow-md border border-gray-200 dark:border-gray-600">
                                                            <!-- Remove Button -->
                                                            <button wire:click="removePhoto"
                                                                class="absolute top-2 right-2 bg-red-500 hover:bg-red-600 text-white rounded-full p-2 shadow-lg transition-all duration-200 hover:scale-110">
                                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                                    class="h-4 w-4" fill="none"
                                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round"
                                                                        stroke-linejoin="round" stroke-width="2"
                                                                        d="M6 18L18 6M6 6l12 12" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </template>

                                                    <template x-if="photos.length > 1">
                                                        <div>
                                                            <button @click="prevPhoto()"
                                                                class="absolute left-2 top-1/2 -translate-y-1/2 bg-black/50 hover:bg-black/70 text-white rounded-full p-2 shadow-lg transition-all duration-200 hover:scale-110">
                                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                                    class="h-5 w-5" fill="none"
                                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round"
                                                                        stroke-linejoin="round" stroke-width="2"
                                                                        d="M15 19l-7-7 7-7" />
                                                                </svg>
                                                            </button>
                                                            <button @click="nextPhoto()"
                                                                class="absolute right-2 top-1/2 -translate-y-1/2 bg-black/50 hover:bg-black/70 text-white rounded-full p-2 shadow-lg transition-all duration-200 hover:scale-110">
                                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                                    class="h-5 w-5" fill="none"
                                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round"
                                                                        stroke-linejoin="round" stroke-width="2"
                                                                        d="M9 5l7 7-7 7" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </template>

                                                    <div
                                                        class="absolute bottom-2 left-0 right-0 flex justify-center gap-1">
                                                        <template x-for="(photo, indexIndicator) in photos"
                                                            :key="`indicator-${indexIndicator}`">
                                                            <button @click="goToPhoto(indexIndicator)"
                                                                class="w-2 h-2 rounded-full transition-all duration-200"
                                                                :class="{
                                                                    'bg-brand-500 scale-125': currentPhotoIndex ===
                                                                        indexIndicator,
                                                                    'bg-gray-400': currentPhotoIndex !==
                                                                        indexIndicator
                                                                }">
                                                            </button>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mt-3 flex overflow-x-auto pb-2 gap-2"
                                                style="scrollbar-width: thin;">
                                                <template x-for="(photo, indexThumbnail) in photos"
                                                    :key="`thumbnail-${indexThumbnail}`">
                                                    <div class="relative flex-shrink-0 w-16 h-16 rounded-md overflow-hidden border-2 transition-all duration-200"
                                                        :class="{
                                                            'border-brand-500': currentPhotoIndex === indexThumbnail,
                                                            'border-transparent hover:border-gray-300 dark:hover:border-gray-500': currentPhotoIndex !==
                                                                indexThumbnail
                                                        }">
                                                        <img :src="photo" @click="goToPhoto(indexThumbnail)"
                                                            class="w-full h-full object-cover cursor-pointer hover:opacity-90 transition-opacity duration-200">

                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                            </div>
                        </div>

                    </div>
                </div>
            </x-slot>

            <x-slot name="footer">
                <div class="flex flex-col sm:flex-row justify-between items-center w-full gap-4">
                    <x-secondary-button wire:click="closeModal" wire:loading.attr="disabled"
                        class="w-full sm:w-auto hover:scale-105 transition-transform duration-200">
                        Cerrar
                    </x-secondary-button>

                    <div class="flex flex-col sm:flex-row w-full sm:w-auto gap-3">
                        <x-primary-button wire:click="updateProduct" wire:loading.attr="disabled"
                            class="w-full sm:w-auto hover:scale-105 transition-transform duration-200">
                            <span wire:loading.remove>Guardar Cambios</span>
                            <span wire:loading class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                Guardando...
                            </span>
                        </x-primary-button>

                    </div>
                </div>
            </x-slot>

        </x-dialog-modal>

        <div id="cameraModalEdit" class="hidden fixed inset-0 z-[999999] bg-black w-screen h-screen">
            <div class="absolute top-0 left-0 w-full h-full flex flex-col">
                <button onclick="CameraHandler.closeCamera('cameraModalEdit')"
                    class="absolute top-4 right-4 bg-black bg-opacity-50 text-white rounded-full p-2 z-10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <div class="flex-grow relative" onclick="handleTapEdit(event, 'cameraModalEdit')">
                    <video id="cameraModalEdit-video" autoplay playsinline muted
                        class="w-full h-full object-cover"></video>
                    <canvas id="cameraModalEdit-canvas" class="hidden"></canvas>
                </div>
            </div>
        </div>

    @endif

    @push('js')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('photoCarousel', () => ({
                    photos: [],
                    currentPhotoIndex: 0,

                    init(initialPhotos) {
                        this.photos = initialPhotos;

                        // Sync with Livewire when photos change
                        this.$watch('photos', (value) => {
                            @this.set('photos', value);
                        });

                        this.$watch('currentPhotoIndex', (value) => {
                            @this.set('currentPhotoIndex', value);
                        });
                    },

                    nextPhoto() {
                        this.currentPhotoIndex = (this.currentPhotoIndex + 1) % this.photos.length;
                    },

                    prevPhoto() {
                        this.currentPhotoIndex = (this.currentPhotoIndex - 1 + this.photos.length) % this
                            .photos.length;
                    },

                    goToPhoto(index) {
                        if (index >= 0 && index < this.photos.length) {
                            this.currentPhotoIndex = index;
                        }
                    },





                }));
            });
        </script>
        <script>
            function handleTapEdit(event, modalId) {
                CameraHandler.handleDoubleTap(event, modalId, (photo) => {
                    @this.call('photoCapturedEdit', photo);
                });
            }
        </script>
    @endpush


    @push('css')
        <style>
            #videoElement {
                background: transparent !important;
                object-fit: cover;
            }
        </style>
    @endpush


</div>
