<div>
    <div>
        @livewire('repuesto.modals.repuesto-create-modal')
    </div>
    <div class="max-w-8xl mx-auto">
        <div class="mb-4">
            <a href="{{ route('compras.repuestos') }}"
                class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 17l-5-5m0 0l5-5m-5 5h12" />
                </svg>
                Volver
            </a>
        </div>

        <div class="text-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Nueva Compra de Repuestos</h1>
        </div>

        <div>
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 mb-6">
                {{-- Primero la sucursal: es el destino del stock, y se elige una
                     sola vez. En cuanto hay una línea el select queda bloqueado
                     —el servidor también lo rechaza, un disabled solo esconde el
                     control— porque cambiarla significaría que la mercadería
                     entró en un sitio y quedó registrada en otro. --}}
                <div class="mb-4">
                    <x-label>Sucursal a la que entra *</x-label>
                    <select wire:model.live="compraRepuesto.sucursal_id" @disabled(count($detalles) > 0)
                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                               focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm
                               disabled:opacity-60 disabled:cursor-not-allowed">
                        <option value="">Seleccione una sucursal...</option>
                        @foreach ($sucursales as $sucursal)
                            <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                        @endforeach
                    </select>
                    @if (count($detalles) > 0)
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Quita los repuestos cargados para poder cambiar de sucursal.
                        </p>
                    @endif
                    <x-input-error for="compraRepuesto.sucursal_id"></x-input-error>
                </div>

                <div class="mb-4">
                    <x-label>Fecha</x-label>
                    <x-input type="date" wire:model="compraRepuesto.fecha_compra" class="w-full" />
                    <x-input-error for="compraRepuesto.fecha_compra"></x-input-error>
                </div>

                <div class="mb-4">
                    <x-label>Tipo de Cambio</x-label>
                    <x-input type="number" wire:model="compraRepuesto.tipo_cambio" class="w-full" />
                    <x-input-error for="compraRepuesto.tipo_cambio"></x-input-error>
                </div>

                <div class="mb-4 relative" wire:ignore.self>
                    <x-label>Buscar Repuesto</x-label>
                    <x-input type="text" class="w-full" placeholder="Nombre del Repuesto..."
                        wire:model.live.debounce.500ms="searchRepuesto" wire:keydown.escape="$set('searchRepuesto', '')"
                        wire:keydown.tab="$set('searchRepuesto', '')" autocomplete="off"
                        :disabled="!$this->puedeElegirArticulos()"></x-input>

                    @if (!empty($searchRepuesto))
                        <ul
                            class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto">
                            @forelse ($filteredRepuestos as $repuesto)
                                <li class="cursor-pointer px-4 py-2 hover:bg-gray-100 text-sm text-gray-700"
                                    wire:click="selectRepuesto('{{ $repuesto->id }}')">
                                    <x-articulo-etiqueta :nombre="$repuesto->nombre"
                                        :fabricante="$repuesto->fabricante"
                                        :modelo="$repuesto->modelo?->nombre" />
                                    @if ($this->sucursalDelDocumento())
                                        <span class="text-xs text-gray-500">· Stock aquí:
                                            {{ $repuesto->stockEn($this->sucursalDelDocumento()) }}</span>
                                    @endif
                                </li>
                            @empty
                                <li class="px-4 py-2 text-gray-400 text-sm">No se encontraron resultados
                                </li>
                            @endforelse
                        </ul>
                    @endif
                </div>

                <div class="mb-4">
                {{-- :disabled y NO @disabled(): dentro de una etiqueta <x-...> la
                     directiva impide que el componente se compile y manda
                     un <x-secondary-button> literal al navegador. --}}
                <x-secondary-button type="button" wire:click="openRepuestoSelector"
                    class="disabled:cursor-not-allowed" :disabled="!$this->puedeElegirArticulos()">
                    Buscar en catalogo...
                </x-secondary-button>

                @unless ($this->puedeElegirArticulos())
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $this->motivoSinSucursal() }}
                    </p>
                @endunless
            </div>

            @can('compra.repuesto.create')
                    <div class="mb-4">
                        <x-primary-button wire:click="openRepuestoCreateModal()">
                            Nuevo Repuesto
                        </x-primary-button>
                    </div>
                @endcan
            </div>

            @php
                // Una consulta para todas las lineas, no una por fila.
                $stocksAqui = $this->stocksDeLasLineas();
            @endphp
            @if (count($detalles) > 0)
                <div class="overflow-x-auto mt-4">
                    <table class="min-w-full border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                <th class="p-2 border border-gray-300 dark:border-gray-600">Repuesto</th>
                                <th class="p-2 border border-gray-300 dark:border-gray-600">Stock aquí</th>
                                <th class="p-2 border border-gray-300 dark:border-gray-600">Costo (USD)</th>
                                <th class="p-2 border border-gray-300 dark:border-gray-600">Cantidad</th>
                                <th class="p-2 border border-gray-300 dark:border-gray-600">Subtotal (USD)</th>
                                <th class="p-2 border border-gray-300 dark:border-gray-600">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-300">
                            @foreach ($detalles as $index => $detalle)
                                <tr>
                                    <td class="p-2 border border-gray-300 dark:border-gray-600">
                                        <x-articulo-etiqueta :nombre="$detalle['nombre']"
                                            :fabricante="$detalle['fabricante'] ?? null"
                                            :modelo="$detalle['modelo'] ?? null" />
                                    </td>
                                    {{-- Lo que hay en la sucursal del documento, no el total: el
                                         total global era justamente lo que hacia que el vendedor
                                         armara la venta entera y la validacion la rechazara. --}}
                                    <td class="p-2 border border-gray-300 dark:border-gray-600 text-center">
                                        {{ $stocksAqui[$detalle['repuesto_id']] ?? 0 }}
                                    </td>
                                    <td class="p-2 border border-gray-300 dark:border-gray-600">
                                        {{-- Input con estilos para modo oscuro --}}
                                        <input type="number" wire:model.lazy="detalles.{{ $index }}.costo"
                                            class="w-24 rounded text-xs p-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-300"
                                            onfocus="this.select()" />
                                    </td>
                                    <td class="p-2 border border-gray-300 dark:border-gray-600">
                                        {{-- Input con estilos para modo oscuro --}}
                                        <input type="number" wire:model.lazy="detalles.{{ $index }}.cantidad"
                                            class="w-24 rounded text-xs p-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-300"
                                            onfocus="this.select()" />
                                    </td>
                                    <td class="p-2 border border-gray-300 dark:border-gray-600">
                                        {{ number_format($detalle['subtotal'], 2) }}
                                    </td>
                                    <td class="p-2 border border-gray-300 dark:border-gray-600 text-center">
                                        <button wire:click="eliminarDetalle({{ $index }})"
                                            class="text-red-600 dark:text-red-500 hover:text-red-800 dark:hover:text-red-400 text-2xl font-extrabold leading-none"
                                            title="Quitar detalle">
                                            ×
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <x-input-error for="detalles"></x-input-error>
                </div>

                <!-- Totales -->
                <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 mb-6 mt-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <x-label>Costo Total (USD)</x-label>
                            <x-input type="number" value="{{ $compraRepuesto['costo_total'] }}" disabled
                                class="w-full" />
                            <x-input-error for="compraRepuesto.costo_total"></x-input-error>
                        </div>
                        <div>
                            <x-label>Cantidad de Repuestos</x-label>
                            <x-input type="number" value="{{ $compraRepuesto['cantidad_repuestos'] }}" disabled
                                class="w-full" />
                            <x-input-error for="compraRepuesto.cantidad_repuestos"></x-input-error>
                        </div>
                    </div>

                </div>

            @endif

            <div class="flex justify-end mt-6">
                @if (count($detalles) > 0)
                    <x-primary-button class="ml-2" wire:click="confirmStore" wire:loading.attr="disabled">
                        Registrar Compra
                    </x-primary-button>
                @endif
            </div>

        </div>
    </div>

    @if ($confirmingStore)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" x-data>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 m-2 max-w-sm mx-auto"
                @click.away="$wire.set('confirmingStore', false)">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Confirmar Compra
                </h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    ¿Estás seguro de que deseas registrar esta compra? Esta acción no se puede deshacer.
                </p>
                <div class="mt-4 flex justify-end space-x-3">
                    <x-secondary-button wire:click="$set('confirmingStore', false)" wire:loading.attr="disabled">
                        Cancelar
                    </x-secondary-button>
                    <x-primary-button wire:click="store" wire:loading.attr="disabled">
                        Sí, Registrar
                    </x-primary-button>
                </div>
            </div>
        </div>
    @endif

@livewire('repuesto.modals.repuesto-selector-modal')
</div>
