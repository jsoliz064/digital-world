<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Agregar nuevo
                {{ $tipo ? mb_strtolower(\App\Enums\RepuestoTipo::from($tipo)->label()) : 'artículo' }}
            </x-slot>

            <x-slot name="content">
                <hr>
                {{-- El select solo aparece cuando el tipo NO viene fijado por la
                     pantalla. En las dos rutas del inventario la ruta ES el tipo,
                     asi que elegirlo aqui solo serviria para que el articulo
                     recien creado no apareciera en la lista. --}}
                @if ($tipo === null)
                    <div class="m-2">
                        <x-label>Tipo:</x-label>
                        <x-select wire:model.live="repuesto.tipo"
                            :options="\App\Enums\RepuestoTipo::toSelectArray()" />
                        <x-input-error for="repuesto.tipo"></x-input-error>
                    </div>
                @endif

                <div class="m-2">
                    <x-label>Nombre:</x-label>
                    <x-input type="text" wire:model="repuesto.nombre" class="w-full"></x-input>
                    <x-input-error for="repuesto.nombre"></x-input-error>
                </div>

                @unless ($this->esAccesorio())
                    {{-- Un accesorio no es la pieza de un modelo concreto: estos cuatro
                         campos solo describen una pieza de reparacion. La clave evita que
                         el morph de Livewire reutilice estos nodos al cambiar de tipo, que
                         dejaria al selector de color con el estado de Alpine anterior. --}}
                    <div wire:key="campos-de-repuesto">
                        <div class="m-2">
                            <x-label>Fabricante (opcional):</x-label>
                            <x-input type="text" wire:model="repuesto.fabricante" class="w-full"></x-input>
                            <x-input-error for="repuesto.fabricante"></x-input-error>
                        </div>

                        <div class="m-2">
                            <x-label>Modelo:</x-label>
                            <x-select wire:model="repuesto.producto_modelo_id" :options="$modelos->pluck('nombre', 'id')"
                                placeholder="Seleccione un modelo" />
                            <x-input-error for="repuesto.producto_modelo_id"></x-input-error>
                        </div>

                        <div class="m-2">
                            <x-label>Categoria:</x-label>
                            <x-select wire:model="repuesto.repuesto_categoria_id" :options="$categorias->pluck('nombre', 'id')"
                                placeholder="Seleccione una categoria" />
                            <x-input-error for="repuesto.repuesto_categoria_id"></x-input-error>
                        </div>

                        <div class="m-2">
                            <x-label>Color (opcional):</x-label>
                            <x-color-picker name-model="repuesto.color" hex-model="repuesto.color_hex"
                                :nombre="$repuesto['color'] ?? null" :hex="$repuesto['color_hex'] ?? null" />
                            <x-input-error for="repuesto.color"></x-input-error>
                            <x-input-error for="repuesto.color_hex"></x-input-error>
                        </div>
                    </div>
                @endunless

                <div class="m-2 grid grid-cols-1 md:grid-cols-4 gap-6 animate-fade-in">
                    <div>
                        <x-label>Costo (USD):</x-label>
                        <x-input type="number" wire:model="repuesto.costo" class="w-full"
                            onfocus="this.select()"></x-input>
                        <x-input-error for="repuesto.costo"></x-input-error>
                    </div>

                    <div>
                        <x-label>Precio (USD):</x-label>
                        <x-input type="number" wire:model="repuesto.precio" class="w-full"
                            onfocus="this.select()"></x-input>
                        <x-input-error for="repuesto.precio"></x-input-error>
                    </div>

                    <div>
                        <x-label>Tipo de Cambio:</x-label>
                        <x-input type="number" wire:model="repuesto.tipo_cambio" class="w-full"
                            onfocus="this.select()"></x-input>
                        <x-input-error for="repuesto.tipo_cambio"></x-input-error>
                    </div>

                    <div>
                        {{-- Calculado y de solo lectura: el stock vive por
                             sucursal y el total es su suma. Antes este campo se
                             escribía a mano y no dejaba rastro en el historial
                             de movimientos, así que el saldo y la cantidad
                             podían divergir sin que nadie lo notara. --}}
                        <x-label>Stock total:</x-label>
                        <x-input type="text" value="{{ $this->totalStock() }}"
                            class="w-full bg-gray-100 dark:bg-gray-900" disabled="true"></x-input>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Suma de las sucursales</p>
                    </div>
                </div>

                {{-- Stock por sucursal. Aplica igual a repuestos y accesorios:
                     un accesorio también tiene existencias. --}}
                <div class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                    <x-label>Stock por sucursal</x-label>

                    @forelse ($stockSucursales as $sucursalId => $cantidad)
                        {{-- wire:key con el índice: el wire:model se enlaza por
                             posición y reutilizar la fila dejaría vivo el enlace
                             anterior, con dos inputs escribiendo en el mismo sitio. --}}
                        <div class="flex items-end gap-2 mt-2" wire:key="stock-{{ $sucursalId }}-{{ $loop->index }}">
                            <div class="flex-1">
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ $sucursalesPorId[$sucursalId]->nombre ?? 'Sucursal #' . $sucursalId }}
                                </span>
                            </div>
                            <div class="w-32">
                                <x-input type="number" min="0" class="w-full"
                                    wire:model="stockSucursales.{{ $sucursalId }}"
                                    onfocus="this.select()"></x-input>
                            </div>
                            <button type="button" wire:click="quitarSucursalStock({{ $sucursalId }})"
                                class="px-3 py-2 text-red-600 hover:text-red-800 dark:text-red-400 text-xl font-bold leading-none"
                                title="Quitar esta sucursal">&times;</button>
                        </div>
                        <x-input-error for="stockSucursales.{{ $sucursalId }}"></x-input-error>
                    @empty
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Sin stock asignado a ninguna sucursal.
                        </p>
                    @endforelse

                    @if ($sucursalesDisponibles->isNotEmpty())
                        <div class="flex items-end gap-2 mt-3">
                            <div class="flex-1">
                                <select wire:model="sucursalNueva"
                                    class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                                           focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                                    <option value="">Agregar una sucursal...</option>
                                    @foreach ($sucursalesDisponibles as $sucursal)
                                        <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="button" wire:click="agregarSucursalStock"
                                class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-md text-sm">
                                Agregar
                            </button>
                        </div>
                    @endif
                </div>

            </x-slot>
            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                <x-primary-button class="ml-2" wire:click="store()" wire:loading.attr="disabled">
                    Guardar
                </x-primary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
