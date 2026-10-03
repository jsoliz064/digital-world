<div>
    <div class="max-w-8xl mx-auto">
        <div class="mb-4">
            <a href="{{ route('ventas.repuestos') }}"
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
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Editar Venta de Repuestos</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Venta ID: {{ $ventaRepuesto['id'] }}</p>

            {{-- De donde nace: sin esto no habia forma de saber que esta venta
                 se cobro junto a un telefono. --}}
            @if (!empty($ventaRepuesto['venta_id']))
                <div class="mt-3 inline-block rounded-lg bg-green-50 dark:bg-green-900 px-4 py-2 text-sm">
                    <span class="text-green-800 dark:text-green-200">Nace de la</span>
                    <a href="{{ route('ventas.detalles', $ventaRepuesto['venta_id']) }}"
                        class="font-semibold text-brand-600 dark:text-brand-400 hover:underline">
                        venta de producto #{{ $ventaRepuesto['venta_id'] }}
                    </a>
                    <span class="block text-xs text-green-700 dark:text-green-300">
                        Sus repuestos ya descontaron stock al montarse en la reparación, así que esas líneas no
                        vuelven a moverlo.
                    </span>
                </div>
            @endif
        </div>

        <div>
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <x-label>Fecha:</x-label>
                        <x-input type="date" wire:model="ventaRepuesto.created_at" disabled
                            class="w-full bg-gray-100 dark:bg-gray-700" />
                    </div>
                    <div>
                        {{-- Solo lectura, y no por comodidad: el stock de cada
                             línea ya salió de una sucursal concreta. El update()
                             tampoco escribe sucursal_id, así que no hay payload
                             que la mueva. --}}
                        <x-label for="sucursal_id" value="Sucursal:" />
                        <x-input type="text"
                            value="{{ $ventaRepuestoModel->sucursal->nombre ?? 'Sin sucursal' }}"
                            class="w-full" disabled="true" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            La sucursal de una venta no se cambia: el stock ya salió de ahí.
                        </p>
                    </div>
                    <div>
                        <x-cliente-picker :search="$searchCliente" :sugerencias="$filteredClientes"
                        :nombre="$ventaRepuesto['cliente'] ?? null" :cliente-id="$ventaRepuesto['cliente_id'] ?? null"
                        label="Cliente" />
                    </div>

                    <div>
                        <x-label>Vendedor:</x-label>
                        <x-input type="text" value="{{ $ventaRepuestoModel->user->name ?? 'Sin usuario' }}"
                            class="w-full" disabled />
                    </div>
                </div>

                <div class="mt-6 mb-4 relative" wire:ignore.self>
                    <x-label>Buscar y Agregar Nuevo Repuesto</x-label>
                    <x-input type="text" class="w-full" placeholder="Escriba el nombre del repuesto..."
                        wire:model.live.debounce.500ms="searchRepuesto" wire:keydown.escape="$set('searchRepuesto', '')"
                        wire:keydown.tab="$set('searchRepuesto', '')" autocomplete="off"
                        :disabled="!$this->puedeElegirArticulos()"></x-input>

                    @if (!empty($searchRepuesto))
                        <ul
                            class="absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto">
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
                                <li class="px-4 py-2 text-gray-400 text-sm">No se encontraron resultados</li>
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
                                <th class="p-2 border border-gray-300 dark:border-gray-600">Precio (USD)</th>
                                <th class="p-2 border border-gray-300 dark:border-gray-600">Cantidad</th>
                                <th class="p-2 border border-gray-300 dark:border-gray-600">Descuento (USD)</th>
                                <th class="p-2 border border-gray-300 dark:border-gray-600">Subtotal (USD)</th>
                                <th class="p-2 border border-gray-300 dark:border-gray-600">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-300">
                            @foreach ($detalles as $index => $detalle)
                                @php
                                    // ?? false porque selectRepuesto() no pone la clave en
                                    // las lineas nuevas: sin esto revienta al agregar una.
                                    $deReparacion = $detalle['stock_ya_descontado'] ?? false;
                                @endphp
                                <tr wire:key="detalle-{{ $detalle['repuesto_id'] }}-{{ $index }}"
                                    class="{{ $deReparacion ? 'bg-green-50 dark:bg-green-900' : '' }}">
                                    <td class="p-2 border border-gray-300 dark:border-gray-600">
                                        <x-articulo-etiqueta :nombre="$detalle['nombre']"
                                            :fabricante="$detalle['fabricante'] ?? null"
                                            :modelo="$detalle['modelo'] ?? null" />
                                        @if ($deReparacion)
                                            <span class="ml-1 inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">{{ 'De reparación' }}</span>
                                        @endif
                                    </td>
                                    {{-- Lo que hay en la sucursal del documento, no el total: el
                                         total global era justamente lo que hacia que el vendedor
                                         armara la venta entera y la validacion la rechazara. --}}
                                    <td class="p-2 border border-gray-300 dark:border-gray-600 text-center">
                                        {{ $stocksAqui[$detalle['repuesto_id']] ?? 0 }}
                                    </td>
                                    <td class="p-2 border border-gray-300 dark:border-gray-600">
                                        <input type="number" step="0.01"
                                            wire:model.lazy="detalles.{{ $index }}.precio"
                                            class="w-24 rounded text-xs p-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-300"
                                            onfocus="this.select()" />
                                    </td>
                                    <td class="p-2 border border-gray-300 dark:border-gray-600">
                                        {{-- La cantidad cobrada es la que se monto en la
                                             reparacion: cambiarla descuadraria el cobro
                                             frente a la pieza que lleva el equipo. --}}
                                        @if ($deReparacion)
                                            <span class="block text-center">{{ $detalle['cantidad'] }}</span>
                                        @else
                                            <input type="number" wire:model.lazy="detalles.{{ $index }}.cantidad"
                                                class="w-24 rounded text-xs p-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-300"
                                                onfocus="this.select()" />
                                        @endif
                                    </td>
                                    <td class="p-2 border border-gray-300 dark:border-gray-600">
                                        <input type="number" step="0.01"
                                            wire:model.lazy="detalles.{{ $index }}.descuento"
                                            class="w-24 rounded text-xs p-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-300"
                                            onfocus="this.select()" />
                                    </td>
                                    <td class="p-2 border border-gray-300 dark:border-gray-600 text-right">
                                        {{ number_format($detalle['subtotal'] ?? 0, 2) }}
                                    </td>
                                    <td class="p-2 border border-gray-300 dark:border-gray-600 text-center">
                                        @if ($deReparacion)
                                            <span class="text-gray-400 text-xs"
                                                title="Se descobra cancelando la venta del equipo.">{{ 'Cobrado con el equipo' }}</span>
                                        @else
                                            <button wire:click="eliminarDetalle({{ $index }})"
                                                class="text-red-600 dark:text-red-500 hover:text-red-800 dark:hover:text-red-400 text-2xl font-extrabold leading-none"
                                                title="Quitar detalle">
                                                ×
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <x-input-error for="detalles" class="mt-2" />
                </div>

                <!-- Totales -->
                <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 mb-6 mt-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-2">
                        <div>
                            <x-label>Cantidad de Ítems</x-label>
                            <x-input type="number" value="{{ $ventaRepuesto['cantidad_repuestos'] }}" disabled
                                class="w-full bg-gray-100 dark:bg-gray-700" />
                        </div>
                        <div>
                            <x-label>Subtotal (USD)</x-label>
                            <x-input type="number" value="{{ $ventaRepuesto['subtotal'] }}" disabled
                                class="w-full bg-gray-100 dark:bg-gray-700" />
                        </div>
                        <div>
                            <x-label>Descuento (USD)</x-label>
                            <x-input type="number" step="0.01" wire:model.lazy="ventaRepuesto.descuento"
                                class="w-full" onfocus="this.select()" />
                            <x-input-error for="ventaRepuesto.descuento"></x-input-error>
                        </div>
                        <div>
                            <x-label>Mano de Obra (USD)</x-label>
                            <x-input type="number" step="0.01" min="0"
                                wire:model.lazy="ventaRepuesto.mano_obra" onfocus="this.select()" class="w-full" />
                            <x-input-error for="ventaRepuesto.mano_obra"></x-input-error>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <x-label>Total (USD)</x-label>
                            <x-input type="number" value="{{ $ventaRepuesto['total'] }}" disabled
                                class="w-full bg-gray-100 dark:bg-gray-700" />
                        </div>

                        <div>
                            <x-label>Tipo de Cambio</x-label>
                            <x-input type="number" step="0.01" wire:model.live.debounce.500ms="ventaRepuesto.tipo_cambio"
                                class="w-full" onfocus="this.select()" />
                            <x-input-error for="ventaRepuesto.tipo_cambio"></x-input-error>
                        </div>

                        <div>
                            <x-label>Total (Bs)</x-label>
                            <x-input type="number" step="0.01" wire:model.live.debounce.500ms="ventaRepuesto.total_bs"
                                class="w-full" onfocus="this.select()" />
                            <x-input-error for="ventaRepuesto.total_bs"></x-input-error>
                        </div>

                    </div>

                    {{-- Solo cuando hay algo que contar: si el Bs cobrado es la
                         conversion limpia, este bloque no existe. Las clases de
                         color van literales en cada rama porque tailwind.config.js
                         no tiene safelist. --}}
                    @if ($this->hayAjuste())
                        @php $ajuste = $this->ajusteBs(); @endphp
                        <div
                            class="mt-4 flex items-center justify-between rounded-lg p-3 text-sm {{ $ajuste > 0 ? 'bg-green-50 dark:bg-green-900' : 'bg-red-50 dark:bg-red-900' }}">
                            <span class="{{ $ajuste > 0 ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200' }}">
                                Ajuste (Bs)
                                <span class="block text-xs {{ $ajuste > 0 ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">
                                    Base: {{ number_format($this->baseBs(), 2) }}
                                    ({{ number_format((float) $ventaRepuesto['total'], 2) }} x
                                    {{ number_format((float) $ventaRepuesto['tipo_cambio'], 2) }}).
                                    No toca el total en USD ni los costos.
                                </span>
                            </span>
                            <span class="flex items-center gap-3 whitespace-nowrap">
                                <span class="font-semibold {{ $ajuste > 0 ? 'text-green-900 dark:text-green-100' : 'text-red-900 dark:text-red-100' }}">
                                    {{ $ajuste > 0 ? '+' : '' }}{{ number_format($ajuste, 2) }}
                                </span>
                                <button type="button" wire:click="limpiarAjuste"
                                    class="text-xs underline text-gray-600 dark:text-gray-300">
                                    Quitar
                                </button>
                            </span>
                        </div>
                    @endif

                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        El tipo de cambio es fijo. Si cobras un total en Bs distinto al de la conversión, la diferencia
                        queda registrada como ajuste.
                    </p>
                </div>
            @else
                <div class="text-center py-8 bg-white dark:bg-gray-800 rounded-lg shadow-md">
                    <p class="text-gray-500 dark:text-gray-400">No hay repuestos en esta venta. Agregue uno para
                        comenzar.</p>
                </div>
            @endif

            <div class="flex justify-end mt-6">
                @if (count($detalles) > 0)
                    <x-primary-button wire:click="confirmUpdate" wire:loading.attr="disabled">
                        Actualizar Venta
                    </x-primary-button>
                @endif
            </div>
        </div>
    </div>

    @if ($confirmingUpdate)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" x-data>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 m-2 max-w-sm mx-auto"
                @click.away="$wire.set('confirmingUpdate', false)">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Confirmar Actualización
                </h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    ¿Estás seguro de que deseas actualizar esta venta? El inventario se ajustará automáticamente.
                </p>
                <div class="mt-4 flex justify-end space-x-3">
                    <x-secondary-button wire:click="$set('confirmingUpdate', false)" wire:loading.attr="disabled">
                        Cancelar
                    </x-secondary-button>
                    <x-primary-button wire:click="update" wire:loading.attr="disabled">
                        Sí, Actualizar
                    </x-primary-button>
                </div>
            </div>
        </div>
    @endif
@livewire('repuesto.modals.repuesto-selector-modal')
@livewire('cliente.modals.cliente-selector-modal')
@livewire('cliente.modals.cliente-create-modal')

</div>
