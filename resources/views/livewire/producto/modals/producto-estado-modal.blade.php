<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                <p class="text-center">
                    Estado de Producto: {{ $producto->imei ?? '' }}
                </p>
            </x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label>Descripcion:</x-label>
                    <x-input type="text" value="{{ $producto->descripcion }}" class="w-full" disabled="true"></x-input>
                </div>

                <div class="m-2">
                    <x-label>Sucursal:</x-label>
                    <x-input type="text" value="{{ $producto->sucursal->nombre }}" class="w-full"
                        disabled="true"></x-input>
                </div>

                <div class="m-2">
                    <x-label>Estado del Producto:</x-label>
                    {{-- Oferta entra por disponibles(): es Inventario con un cartel,
                         asi que desde ahi tambien se puede cambiar de estado. --}}
                    @if (in_array($producto->estado, App\Enums\ProductoEstado::disponibles(), true) || $producto->estado == 'Roto' || $producto->estado == 'Transito')
                        <x-select wire:model.live="estado" :options="$productoEstados" placeholder="Seleccione un estado" />
                    @else
                        <x-input type="text" value="{{ $producto->estado }}" class="w-full"
                            disabled="true"></x-input>
                    @endif
                    <x-input-error for="estado"></x-input-error>
                </div>

                <hr>

                @if ($estado == 'Fuera' || $estado == 'Transito')
                    <div class="m-2">
                        <x-label>Descripcion:</x-label>
                        <x-input type="text" wire:model="fueraTransito.descripcion" class="w-full"></x-input>
                        <x-input-error for="fueraTransito.descripcion"></x-input-error>
                    </div>

                    @if (isset($fueraTransito['id']))
                        <x-primary-button class="ml-2" wire:click="finalizarFueraTransito()"
                            wire:loading.attr="disabled">
                            Marcar Como Terminado
                        </x-primary-button>
                    @endif
                @endif

                @if ($estado == 'Vendido')
                    @php
                        $disabled = isset($vendido['id']) ? 'true' : '';
                    @endphp

                    @if ($producto->estado !== 'Vendido')
                        <div class="flex justify-center gap-4 m-2">
                            {{-- Precio Vendedor --}}
                            <div wire:click="seleccionarPrecio({{ $producto->precio_vendedor }})"
                                class="cursor-pointer p-4 border rounded-lg text-center w-40 transition 
                            {{ $precioSeleccionado == $producto->precio_vendedor ? 'border-brand-500 bg-brand-100' : 'border-gray-300' }}">
                                <div class="font-semibold">Precio Vendedor</div>
                                <div class="text-lg font-bold text-gray-700">
                                    $ {{ number_format($producto->precio_vendedor, 2) }}</div>
                                <input type="checkbox" class="mt-2" disabled
                                    {{ $precioSeleccionado == $producto->precio_vendedor ? 'checked' : '' }}>
                            </div>

                            {{-- Precio Cliente --}}
                            <div wire:click="seleccionarPrecio({{ $producto->precio_cliente }})"
                                class="cursor-pointer p-4 border rounded-lg text-center w-40 transition 
                            {{ $precioSeleccionado == $producto->precio_cliente ? 'border-green-500 bg-green-100' : 'border-gray-300' }}">
                                <div class="font-semibold">Precio Cliente</div>
                                <div class="text-lg font-bold text-gray-700">
                                    $ {{ number_format($producto->precio_cliente, 2) }}</div>
                                <input type="checkbox" class="mt-2" disabled
                                    {{ $precioSeleccionado == $producto->precio_cliente ? 'checked' : '' }}>
                            </div>
                        </div>
                    @endif

                    @if (isset($vendido['id']))
                        <div class="m-2">
                            <x-label>Fecha de Venta:</x-label>
                            <x-input type="text"
                                value="{{ date('Y-m-d H:i:s', strtotime($vendido['created_at'])) }}" class="w-full"
                                :disabled="$disabled"></x-input>
                        </div>
                    @endif

                    <div class="m-2 grid grid-cols-1 md:grid-cols-3 gap-6 animate-fade-in">
                        <div>
                            <x-label>Precio (USD):</x-label>
                            <x-input type="number" wire:model.lazy="vendido.precio" class="w-full"
                                onfocus="this.select()" :disabled="$disabled"></x-input>
                            <x-input-error for="vendido.precio"></x-input-error>
                        </div>

                        <div>
                            <x-label>Descuento (USD):</x-label>
                            <x-input type="number" wire:model.lazy="vendido.descuento" class="w-full"
                                onfocus="this.select()" :disabled="$disabled"></x-input>
                            <x-input-error for="vendido.descuento"></x-input-error>
                        </div>

                        <div>
                            <x-label>Total (USD):</x-label>
                            <x-input type="number" :value="$vendido['subtotal']" class="w-full" disabled="true"></x-input>
                            <x-input-error for="vendido.subtotal"></x-input-error>
                        </div>
                    </div>

                    <div class="m-2 grid grid-cols-1 md:grid-cols-2 gap-6 animate-fade-in">
                        <div>
                            <x-label>Tipo de Cambio:</x-label>
                            <x-input type="number" wire:model.lazy="vendido.tipo_cambio" class="w-full"
                                onfocus="this.select()" :disabled="$disabled"></x-input>
                            <x-input-error for="vendido.tipo_cambio"></x-input-error>
                        </div>

                        <div>
                            <x-label>Total (Bs):</x-label>
                            <x-input type="number" :value="$vendido['subtotal_bs']" class="w-full" disabled="true"></x-input>
                            <x-input-error for="vendido.subtotal_bs"></x-input-error>
                        </div>
                    </div>

                    <div class="m-2 grid grid-cols-1 md:grid-cols-2 gap-6 animate-fade-in">
                        <div>
                            <x-label>Meses de Garantia:</x-label>
                            <x-input type="number" wire:model.lazy="vendido.garantia_meses" class="w-full"
                                onfocus="this.select()" :disabled="$disabled"></x-input>
                            <x-input-error for="vendido.garantia_meses"></x-input-error>
                        </div>

                        <div>
                            <x-label>Fecha de Vencimiento de Garantia:</x-label>
                            <x-input type="text" wire:model.lazy="vendido.garantia_fecha_exp" class="w-full"
                                onfocus="this.select()" disabled="true"></x-input>
                            <x-input-error for="vendido.garantia_fecha_exp"></x-input-error>
                        </div>
                    </div>

                    <div class="m-2">
                        {{-- Con la venta ya hecha solo se muestra a quién se le vendió:
                             el picker escribiría en una venta cerrada. Antes esto era
                             un textarea de tres filas para un nombre. --}}
                        @if ($disabled)
                            <x-label>Cliente:</x-label>
                            <x-input class="w-full" disabled="true"
                                value="{{ $vendido['cliente'] ?? 'Sin cliente' }}"></x-input>
                        @else
                            <x-cliente-picker :search="$searchCliente" :sugerencias="$filteredClientes"
                                :nombre="$vendido['cliente'] ?? null" :cliente-id="$vendido['cliente_id'] ?? null"
                                label="Cliente" />
                        @endif
                    </div>

                    {{-- Con la venta ya hecha: lo que se cobro. Es el espejo de la
                         tabla de abajo, que solo sale antes de vender. --}}
                    @if (isset($vendido['id']))
                        <div class="m-2">
                            <x-repuestos-cobrados :ventas-repuestos="$ventasRepuestosCobradas"
                                titulo="Repuestos cobrados en esta venta" />
                        </div>
                    @endif

                    {{-- Repuestos que se montaron en reparaciones de este equipo y que
                         todavia no se han cobrado. Ya salieron del almacen, asi que
                         marcarlos solo registra el ingreso: el stock no se toca. --}}
                    @if ($repuestosElegibles->isNotEmpty())
                        <div class="m-2 rounded-lg border border-green-200 dark:border-green-700 bg-green-50 dark:bg-green-900 p-3">
                            <h4 class="text-sm font-semibold text-green-900 dark:text-green-100">
                                Repuestos de reparación a cobrar
                            </h4>
                            <p class="mt-1 text-xs text-green-700 dark:text-green-300">
                                Marca los que le cobras al cliente aparte del precio del teléfono. Se registran como
                                venta de repuestos y no descuentan stock, porque ya se descontó al montarlos.
                            </p>

                            <table class="min-w-full mt-3 text-xs">
                                <thead class="text-green-900 dark:text-green-100">
                                    <tr>
                                        <th class="p-1 w-8"></th>
                                        <th class="p-1 text-left">Repuesto</th>
                                        <th class="p-1 text-center">Rep.</th>
                                        <th class="p-1 text-center">Cant.</th>
                                        <th class="p-1 text-center">Precio ($)</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-800 dark:text-gray-200">
                                    @foreach ($repuestosElegibles as $linea)
                                        <tr wire:key="cobrar-{{ $linea->id }}">
                                            <td class="p-1 text-center">
                                                <x-checkbox wire:model.live="repuestosCobrar"
                                                    value="{{ $linea->id }}" />
                                            </td>
                                            <td class="p-1">{{ $linea->repuesto?->nombre ?? 'Repuesto' }}</td>
                                            <td class="p-1 text-center">#{{ $linea->producto_reparacion_id }}</td>
                                            <td class="p-1 text-center">{{ $linea->cantidad }}</td>
                                            <td class="p-1 text-center">
                                                <input type="number" step="0.01" min="0"
                                                    wire:model.lazy="preciosCobrar.{{ $linea->id }}"
                                                    class="w-24 rounded text-xs p-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-300"
                                                    onfocus="this.select()" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if (isset($vendido['id']))
                        @can('venta.detalle.delete')
                            <x-primary-button class="ml-2" wire:click="cancelarVenta()" wire:loading.attr="disabled">
                                Cancelar Venta
                            </x-primary-button>
                        @endcan
                    @endif
                @endif

                @if ($estado == 'Reparacion')
                    <div class="m-2 grid grid-cols-1 md:grid-cols-2 gap-6 animate-fade-in">
                        <div>
                            <x-label>
                                Tecnico:
                                {!! $tecnico ? $tecnico->getPColor() : '' !!}
                            </x-label>

                            <x-select-tecnicos wire:model="reparacion.tecnico_id" :options="$tecnicos"
                                placeholder="Seleccione una tecnico" />
                            <x-input-error for="reparacion.tecnico_id"></x-input-error>
                        </div>

                        <div>
                            <x-label>Fecha de Entrega:</x-label>
                            <x-input type="date" wire:model="reparacion.fecha_entrega" class="w-full"></x-input>
                            <x-input-error for="reparacion.fecha_entrega"></x-input-error>
                        </div>

                    </div>

                    <div class="m-2">
                        <x-label>Repuestos Técnico:</x-label>
                        <textarea wire:model.defer="reparacion.repuestos_tecnico" rows="2"
                            class="w-full mt-1 p-2 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 dark:focus:border-brand-600 focus:ring-brand-500 dark:focus:ring-brand-600 rounded-md shadow-sm"></textarea>
                        <x-input-error for="reparacion.repuestos_tecnico"></x-input-error>
                    </div>

                    <div class="m-2">
                        <x-label>Repuestos Propios:</x-label>
                        <textarea wire:model.defer="reparacion.repuestos_propios" rows="2"
                            class="w-full mt-1 p-2 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 dark:focus:border-brand-600 focus:ring-brand-500 dark:focus:ring-brand-600 rounded-md shadow-sm"></textarea>
                        <x-input-error for="reparacion.repuestos_propios"></x-input-error>
                    </div>

                    <div class="m-2">
                        <x-label>Repuestos a Devolver:</x-label>
                        <textarea wire:model.defer="reparacion.repuestos_devolver" rows="2"
                            class="w-full mt-1 p-2 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 dark:focus:border-brand-600 focus:ring-brand-500 dark:focus:ring-brand-600 rounded-md shadow-sm"></textarea>
                        <x-input-error for="reparacion.repuestos_devolver"></x-input-error>
                    </div>

                    <div class="m-2 mb-4 relative" wire:ignore.self>
                        <x-label>Buscar y Agregar Repuesto</x-label>
                        <div class="my-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-label>Categoria:</x-label>
                                <x-select wire:model.live="categoriaId" :options="$categorias->pluck('nombre', 'id')" placeholder="Ninguna" />
                                <x-input-error for="categoriaId"></x-input-error>
                            </div>
                            <div>
                                <x-label>Modelo:</x-label>
                                <x-select wire:model.live="modeloId" :options="$modelos->pluck('nombre', 'id')" placeholder="Ninguno" />
                                <x-input-error for="modeloId"></x-input-error>
                            </div>
                        </div>
                        {-- De qué sucursal salen las piezas. Lo elige el técnico y queda
                             congelado en cada línea: al terminar la reparación el equipo se
                             muda al Almacén, así que la sucursal actual del producto no dice
                             de dónde salió la pieza. --}
                        <div class="mb-3">
                            <x-label>Sucursal de donde salen las piezas *</x-label>
                            <select wire:model.live="sucursalRepuestos"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                                       focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                                <option value="">Seleccione una sucursal...</option>
                                @foreach ($sucursales as $sucursal)
                                    <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                                @endforeach
                            </select>
                            <x-input-error for="sucursalRepuestos"></x-input-error>
                        </div>

                        <x-input type="text" class="w-full" placeholder="Escriba el nombre del repuesto..."
                            wire:model.live.debounce.500ms="searchRepuesto"
                            wire:keydown.escape="$set('searchRepuesto', '')"
                            wire:keydown.tab="$set('searchRepuesto', '')" autocomplete="off"></x-input>

                        @if (!empty($searchRepuesto))
                            <ul
                                class="absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto">
                                @forelse ($filteredRepuestos as $repuesto)
                                    <li class="cursor-pointer px-4 py-2 hover:bg-gray-100 text-sm text-gray-700"
                                        wire:click="selectRepuesto('{{ $repuesto->id }}')">
                                        {{ $repuesto->nombre }}
                                        {{ $repuesto->fabricante ? ' - ' . $repuesto->fabricante : '' }}
                                        ({{ $repuesto->categoria ? $repuesto->categoria->nombre . ' - ' : '' }}
                                        {{ $repuesto->modelo ? $repuesto->modelo->nombre . ' - ' : '' }}
                                        Stock aquí: {{ $repuesto->stockEn($sucursalRepuestos) }})
                                    </li>
                                @empty
                                    <li class="px-4 py-2 text-gray-400 text-sm">No se encontraron resultados</li>
                                @endforelse
                            </ul>
                        @endif
                    </div>

                    @if (count($repuestos) > 0)
                        <div class="m-2">
                            <x-label>Repuestos Propios:</x-label>
                            <div class="overflow-x-auto mt-4">
                                <table class="min-w-full border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                            <th class="p-2 border border-gray-300 dark:border-gray-600">Repuesto</th>
                                            <th class="p-2 border border-gray-300 dark:border-gray-600">Costo</th>
                                            <th class="p-2 border border-gray-300 dark:border-gray-600">Cant.</th>
                                            <th class="p-2 border border-gray-300 dark:border-gray-600">Subtotal</th>
                                            <th class="p-2 border border-gray-300 dark:border-gray-600">Subtotal Bs
                                            </th>
                                            <th class="p-2 border border-gray-300 dark:border-gray-600">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-300">
                                        @foreach ($repuestos as $index => $detalle)
                                            <tr wire:key="detalle-{{ $detalle['repuesto_id'] }}-{{ $index }}">
                                                <td class="p-2 border border-gray-300 dark:border-gray-600">
                                                    {{ $detalle['nombre'] }} - {{ $detalle['fabricante'] }} -
                                                    {{ $detalle['modelo'] }}</td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-600">
                                                    <input type="number" step="0.01"
                                                        wire:model.lazy="repuestos.{{ $index }}.costo"
                                                        class="w-24 rounded text-xs p-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-300"
                                                        onfocus="this.select()" />
                                                </td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-600">
                                                    <input type="number"
                                                        wire:model.lazy="repuestos.{{ $index }}.cantidad"
                                                        class="w-24 rounded text-xs p-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-300"
                                                        onfocus="this.select()" />
                                                </td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-600 text-right">
                                                    {{ number_format($detalle['subtotal_costo'] ?? 0, 2) }}
                                                </td>
                                                <td class="p-2 border border-gray-300 dark:border-gray-600 text-right">
                                                    {{ number_format($detalle['subtotal_costo_bs'] ?? 0, 2) }}
                                                </td>
                                                <td
                                                    class="p-2 border border-gray-300 dark:border-gray-600 text-center">
                                                    <button wire:click="eliminarRepuesto({{ $index }})"
                                                        class="text-red-600 dark:text-red-500 hover:text-red-800 dark:hover:text-red-400 text-2xl font-extrabold leading-none"
                                                        title="Quitar detalle">
                                                        ×
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <x-input-error for="repuestos" class="mt-2" />
                            </div>
                        </div>
                    @endif

                    <div class="m-2 grid grid-cols-1 md:grid-cols-3 gap-6 animate-fade-in">
                        <div>
                            <x-label>Costo Reparacion (Bs):</x-label>
                            <x-input type="number" wire:model.lazy="reparacion.costo" class="w-full"
                                onfocus="this.select()"></x-input>
                            <x-input-error for="reparacion.costo"></x-input-error>
                        </div>

                        <div>
                            <x-label>Costo Repuestos (Bs):</x-label>
                            <x-input type="number" wire:model.lazy="reparacion.costo_repuestos" class="w-full"
                                onfocus="this.select()"></x-input>
                            <x-input-error for="reparacion.costo_repuestos"></x-input-error>
                        </div>

                        <div>
                            <x-label>Tipo de Cambio:</x-label>
                            <x-input type="number" wire:model.lazy="reparacion.tipo_cambio" class="w-full"
                                onfocus="this.select()"></x-input>
                            <x-input-error for="reparacion.tipo_cambio"></x-input-error>
                        </div>
                    </div>

                    <div class="m-2 grid grid-cols-1 md:grid-cols-2 gap-6 animate-fade-in">
                        <div>
                            <x-label>Total (Bs):</x-label>
                            <x-input type="number" :value="$reparacion['costo_total_bs']" class="w-full" disabled="true"></x-input>
                            <x-input-error for="reparacion.costo_total_bs"></x-input-error>
                        </div>

                        <div>
                            <x-label>Total (USD):</x-label>
                            <x-input type="number" :value="$reparacion['costo_total']" class="w-full" disabled="true"></x-input>
                            <x-input-error for="reparacion.costo_total"></x-input-error>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="animate-fade-in m-2">
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="reparacion.garantia_tecnico"
                                    class="form-checkbox h-5 w-5 text-brand-600 transition duration-150 ease-in-out rounded dark:bg-gray-700 dark:border-gray-600">
                                <span class="ml-2 text-gray-700 dark:text-gray-200">Garantia del Tecnico</span>
                            </label>
                            <x-input-error for="reparacion.garantia_tecnico" class="mt-1" />
                        </div>
                    </div>

                    <div class="animate-fade-in m-2">
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="reparacion.pagado"
                                class="form-checkbox h-5 w-5 text-brand-600 transition duration-150 ease-in-out rounded dark:bg-gray-700 dark:border-gray-600">
                            <span class="ml-2 text-gray-700 dark:text-gray-200">Pagado</span>
                        </label>
                        <x-input-error for="reparacion.pagado" class="mt-1" />
                    </div>

                    @if (isset($reparacion['id']))
                        <x-primary-button class="ml-2" wire:click="finalizarReparacion()"
                            wire:loading.attr="disabled">
                            Marcar Como Terminado
                        </x-primary-button>
                    @endif

                @endif

            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>

                @if ($producto->estado !== 'Vendido' || $estado !== 'Vendido')
                    <x-primary-button class="ml-2" wire:click="update()" wire:loading.attr="disabled">
                        Actualizar
                    </x-primary-button>
                @endif
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
