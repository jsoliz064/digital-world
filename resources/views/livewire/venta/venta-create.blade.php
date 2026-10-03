<div>
    <div class="max-w-8xl mx-auto">

        <div class="mb-4">
            <a href="{{ route('ventas') }}"
                class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 17l-5-5m0 0l5-5m-5 5h12" />
                </svg>
                Volver
            </a>
        </div>

        <div class="text-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Registrar Nueva Venta</h1>
        </div>

        <!-- Cabecera y buscador -->
        <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <x-label for="sucursal_id" value="Sucursal" />
                    {{-- La sucursal se bloquea en cuanto hay productos: cambiarla
                         despues dejaria las lineas apuntando a otra tienda. --}}
                    @if (count($detalles) == 0)
                        <select wire:model.live="venta.sucursal_id" id="sucursal_id"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm h-10">
                            <option value="">Seleccione una Sucursal</option>
                            @foreach ($sucursales as $sucursal)
                                <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                            @endforeach
                        </select>
                        <x-input-error for="venta.sucursal_id" class="mt-1" />
                    @else
                        <x-input type="text" value="{{ $venta['sucursal_nombre'] }}" disabled class="mt-1 w-full" />
                    @endif
                </div>

                <div>
                    <x-label for="tipo_precio" value="Tipo de Precio" />
                    <select wire:model.live="tipo_precio" id="tipo_precio"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm h-10">
                        <option value="Vendedor">Vendedor</option>
                        <option value="Cliente">Cliente</option>
                    </select>
                </div>

                <div>
<x-cliente-picker :search="$searchCliente" :sugerencias="$filteredClientes"
                        :nombre="$venta['cliente'] ?? null" :cliente-id="$venta['cliente_id'] ?? null"
                        label="Cliente" />
                </div>
            </div>

            @if ($venta['sucursal_id'] != null)
                <div class="mt-4 relative" wire:ignore.self>
                    <x-label value="Buscar producto por IMEI" />
                    <x-input class="mt-1 w-full" type="text" placeholder="IMEI..."
                        wire:model.live.debounce.500ms="searchImei" wire:keydown.escape="$set('searchImei', '')"
                        wire:keydown.tab="$set('searchImei', '')" autocomplete="off" />

                    @if (!empty($searchImei))
                        <ul
                            class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-auto">
                            @forelse ($filteredProductos as $producto)
                                <li class="cursor-pointer px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 text-sm text-gray-700 dark:text-gray-200"
                                    wire:click="selectProducto('{{ $producto->imei }}')">
                                    {{ $producto->imei }} - {{ $producto->descripcion }}
                                    <span class="text-gray-400">({{ $producto->sucursal->nombre }})</span>
                                </li>
                            @empty
                                <li class="px-4 py-2 text-gray-400 text-sm">No se encontraron resultados</li>
                            @endforelse
                        </ul>
                    @endif
                </div>

                {{-- El buscador por IMEI sirve cuando ya sabes cual es; el
                     catalogo, para mirar que hay. Son dos gestos distintos. --}}
                <div class="mt-3">
                    <x-secondary-button type="button" wire:click="openProductoSelector">
                        Buscar en catálogo...
                    </x-secondary-button>
                </div>
            @else
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    Selecciona una sucursal para empezar a agregar productos.
                </p>
            @endif
        </div>

        @if (count($detalles) > 0)
            <!-- Productos de la venta -->
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Productos de la Venta</h2>

                <div class="overflow-x-auto">
                    <table class="min-w-full border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                <th class="p-2 border border-gray-300 dark:border-gray-600 text-left">IMEI</th>
                                <th class="p-2 border border-gray-300 dark:border-gray-600 text-left">Descripción</th>
                                <th class="p-2 border border-gray-300 dark:border-gray-600">Precio ($)</th>
                                <th class="p-2 border border-gray-300 dark:border-gray-600">Descuento ($)</th>
                                <th class="p-2 border border-gray-300 dark:border-gray-600">Subtotal ($)</th>
                                <th class="p-2 border border-gray-300 dark:border-gray-600">Repuestos</th>
                                <th class="p-2 border border-gray-300 dark:border-gray-600">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-300">
                            @foreach ($detalles as $index => $detalle)
                                {{-- La clave lleva la posicion ademas del id: los inputs se
                                     enlazan por posicion y cada producto nuevo entra arriba,
                                     corriendo a los demas. Con la clave solo por id, Livewire
                                     reutiliza la fila, le reescribe el wire:model y deja vivo
                                     el enlace anterior, asi que dos inputs terminaban
                                     escribiendo en el mismo sitio. --}}
                                <tr wire:key="detalle-{{ $detalle['producto_id'] }}-{{ $index }}">
                                    <td class="p-2 border border-gray-300 dark:border-gray-600 font-mono">
                                        {{ $detalle['imei'] }}</td>
                                    <td class="p-2 border border-gray-300 dark:border-gray-600">
                                        {{ $detalle['descripcion'] }}</td>
                                    <td class="p-2 border border-gray-300 dark:border-gray-600">
                                        <input type="number" step="0.01"
                                            wire:model.lazy="detalles.{{ $index }}.precio"
                                            class="w-28 rounded text-sm p-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-300"
                                            onfocus="this.select()" />
                                    </td>
                                    <td class="p-2 border border-gray-300 dark:border-gray-600">
                                        <input type="number" step="0.01"
                                            wire:model.lazy="detalles.{{ $index }}.descuento"
                                            class="w-28 rounded text-sm p-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-300"
                                            onfocus="this.select()" />
                                    </td>
                                    <td class="p-2 border border-gray-300 dark:border-gray-600 text-right">
                                        {{ number_format($detalle['subtotal'], 2) }}</td>
                                    <td class="p-2 border border-gray-300 dark:border-gray-600 text-center">
                                        {{-- Repuestos montados en reparaciones de ESTE equipo que se
                                             pueden cobrar aparte. Sin reparaciones, no hay boton. --}}
                                        @php
                                            $elegibles = $detalle['repuestos_elegibles'] ?? 0;
                                            $elegidos = count($repuestosVenta[$detalle['producto_id']] ?? []);
                                        @endphp
                                        @if ($elegibles > 0)
                                            <button type="button"
                                                wire:click="abrirRepuestosDe({{ $detalle['producto_id'] }})"
                                                class="underline {{ $elegidos ? 'text-green-600 dark:text-green-400 font-semibold' : 'text-brand-600 dark:text-brand-400' }}">
                                                {{ $elegidos ? $elegidos . ' a cobrar' : $elegibles . ' disponible(s)' }}
                                            </button>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="p-2 border border-gray-300 dark:border-gray-600 text-center">
                                        <button wire:click="eliminarDetalle({{ $index }})"
                                            class="text-red-600 dark:text-red-500 hover:text-red-800 dark:hover:text-red-400 text-2xl font-extrabold leading-none"
                                            title="Quitar producto">
                                            &times;
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-input-error for="detalles" class="mt-2" />
            </div>

            <!-- Totales -->
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <x-label value="Cantidad de Productos" />
                        <x-input type="number" value="{{ count($detalles) }}" disabled class="mt-1 w-full" />
                    </div>
                    <div>
                        <x-label value="Subtotal ($)" />
                        <x-input type="number" value="{{ $venta['subtotal'] }}" disabled class="mt-1 w-full" />
                    </div>
                    <div>
                        <x-label value="Descuento ($)" />
                        <x-input type="number" step="0.01" wire:model.lazy="venta.descuento"
                            onfocus="this.select()" class="mt-1 w-full" />
                        <x-input-error for="venta.descuento" class="mt-1" />
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <x-label value="Total ($)" />
                        <x-input type="number" value="{{ $venta['total'] }}" disabled class="mt-1 w-full" />
                    </div>
                    <div>
                        <x-label value="Tipo de Cambio" />
                        <x-input type="number" step="0.01" wire:model.lazy="venta.tipo_cambio"
                            onfocus="this.select()" class="mt-1 w-full" />
                        <x-input-error for="venta.tipo_cambio" class="mt-1" />
                    </div>
                    <div>
                        <x-label value="Total (Bs.)" />
                        <x-input type="number" value="{{ $venta['total_bs'] }}" disabled class="mt-1 w-full" />
                    </div>
                </div>

                {{-- Los repuestos se cobran encima del precio del telefono, asi
                     que van fuera del total de arriba y se muestran aparte. --}}
                @if ($this->totalRepuestosVenta() > 0)
                    <div
                        class="mt-4 flex items-center justify-between rounded-lg bg-green-50 dark:bg-green-900 p-3 text-sm">
                        <span class="text-green-800 dark:text-green-200">
                            Repuestos cobrados aparte
                            <span class="block text-xs text-green-700 dark:text-green-300">
                                Se registran como venta de repuestos enlazada a esta venta. No entran en el total del
                                teléfono y no descuentan stock.
                            </span>
                        </span>
                        <span class="font-semibold text-green-900 dark:text-green-100 whitespace-nowrap">
                            $ {{ number_format($this->totalRepuestosVenta(), 2) }}
                        </span>
                    </div>
                @endif
            </div>

            <div class="flex justify-end mb-6">
                <x-primary-button wire:click="store()" wire:loading.attr="disabled" wire:target="store">
                    Registrar Venta
                </x-primary-button>
            </div>
        @endif
    </div>

    @livewire('producto.modals.producto-selector-modal')
    @livewire('producto.modals.producto-repuestos-venta-modal')
    @livewire('cliente.modals.cliente-selector-modal')
@livewire('cliente.modals.cliente-create-modal')
</div>
