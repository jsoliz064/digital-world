<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            @php
                $esExterno = $tipo === \App\Enums\ReparacionTipo::Externo->value;
            @endphp

            <x-slot name="title">
                <p class="text-center">
                    {{ $esExterno ? 'Trabajo Externo' : 'Garantia' }} de Producto: {{ $producto->imei ?? '' }}
                </p>
            </x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label>Descripcion:</x-label>
                    <x-input type="text" value="{{ $producto->descripcion }}" class="w-full" disabled="true"></x-input>
                </div>

                <div class="m-2 grid grid-cols-1 md:grid-cols-3 gap-6 animate-fade-in">
                    <div>
                        <x-label>Nro de Venta:</x-label>
                        <x-input type="text" value="{{ $producto->ventaProducto?->id }}" class="w-full"
                            disabled="true"></x-input>
                    </div>

                    <div>
                        <x-label>Fecha de Venta:</x-label>
                        <x-input type="text" value="{{ $producto->ventaProducto?->created_at }}" class="w-full"
                            disabled="true"></x-input>
                    </div>

                    <div>
                        <x-label>Vendido a:</x-label>
                        {{-- `ventaProducto?->cliente` apuntaba a una columna que no
                             existe en ventas_productos, asi que este campo salia
                             vacio SIEMPRE. El cliente vive en la cabecera. --}}
                        <x-input type="text" class="w-full" disabled="true"
                            value="{{ $producto->ventaProducto?->venta?->nombreCliente() ?? 'Sin cliente' }}"></x-input>
                    </div>
                </div>

                <div class="m-2 grid grid-cols-1 md:grid-cols-2 gap-6 animate-fade-in">
                    <div>
                        <x-label>Garantia Meses:</x-label>
                        <x-input type="text" value="{{ $detalle?->garantia_meses }}" class="w-full"
                            disabled="true"></x-input>
                    </div>

                    <div>
                        <x-label>Fecha de vencimiento:</x-label>
                        <x-input type="text" value="{{ $detalle?->garantia_fecha_exp }}" class="w-full"
                            disabled="true"></x-input>
                    </div>
                </div>

                <hr>

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
                    <x-label>Buscar y Agregar Nuevo Repuesto</x-label>
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
                        wire:model.live.debounce.500ms="searchRepuesto" wire:keydown.escape="$set('searchRepuesto', '')"
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
                                        <th class="p-2 border border-gray-300 dark:border-gray-600">Subtotal Bs</th>
                                        <th class="p-2 border border-gray-300 dark:border-gray-600">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-300">
                                    @foreach ($repuestos as $index => $detalle)
                                        <tr wire:key="detalle-{{ $detalle['repuesto_id'] }}-{{ $index }}">
                                            <td class="p-2 border border-gray-300 dark:border-gray-600">
                                                {{ $detalle['nombre'] }} {{ $detalle['fabricante'] }}
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
                                            <td class="p-2 border border-gray-300 dark:border-gray-600 text-center">
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

                {{-- Solo el trabajo externo se le cobra al cliente: una garantia
                     la asumimos nosotros. --}}
                @if ($esExterno)
                    @php
                        $cobro = (float) ($reparacion['cobro_cliente'] ?? 0);
                        // Con el campo recien abierto el cobro vale 0, y restarle
                        // el costo mostraba un negativo en rojo que parecia un
                        // error sin serlo. Hasta que se escriba una cifra no hay
                        // ganancia que calcular.
                        $hayCobro = $cobro > 0;
                        $ganancia = $cobro - (float) ($reparacion['costo_total_bs'] ?? 0);
                    @endphp
                    <div class="m-2 grid grid-cols-1 md:grid-cols-2 gap-6 animate-fade-in">
                        <div>
                            <x-label>Cobrado al Cliente (Bs):</x-label>
                            <x-input type="number" wire:model.lazy="reparacion.cobro_cliente" class="w-full"
                                onfocus="this.select()"></x-input>
                            <x-input-error for="reparacion.cobro_cliente"></x-input-error>
                        </div>

                        <div>
                            <x-label>Ganancia del Trabajo (Bs):</x-label>
                            <x-input type="text" value="{{ $hayCobro ? number_format($ganancia, 2) : '—' }}"
                                class="w-full" disabled="true"></x-input>
                            @if ($hayCobro)
                                <p class="mt-1 text-xs {{ $ganancia >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    Cobro ({{ number_format($cobro, 2) }}) menos el costo del trabajo
                                    ({{ number_format($reparacion['costo_total_bs'] ?? 0, 2) }}).
                                </p>
                            @else
                                <p class="mt-1 text-xs text-gray-500">
                                    Indica cuánto le cobras al cliente para ver la ganancia.
                                </p>
                            @endif
                        </div>
                    </div>
                @endif

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
                    <x-primary-button class="ml-2" wire:click="finalizarReparacion()" wire:loading.attr="disabled">
                        Marcar Como Terminado
                    </x-primary-button>
                @endif

            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>

                <x-primary-button class="ml-2" wire:click="update()" wire:loading.attr="disabled">
                    {{ isset($reparacion['id']) ? 'Actualizar' : 'Guardar' }}
                </x-primary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
