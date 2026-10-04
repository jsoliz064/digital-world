<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                <p class="text-center">Estado del equipo: {{ $producto->imei ?? '' }}</p>
            </x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label>Descripción:</x-label>
                    <x-input type="text" value="{{ $producto->descripcion }}" class="w-full" disabled="true"></x-input>
                </div>

                <div class="m-2">
                    <x-label>Sucursal:</x-label>
                    <x-input type="text" value="{{ $producto->sucursal?->nombre }}" class="w-full" disabled="true"></x-input>
                </div>

                @if ($this->esVendido())
                    {{-- Vendido o a credito: lo escribe la venta. Aqui solo se ve y se anula. --}}
                    <div class="m-2 rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900 dark:border-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-100">
                        <p class="font-semibold">{{ \App\Enums\ProductoEstado::labelDe($producto->estado) }}
                            @if ($lineaVenta)
                                en la venta
                                <a href="{{ route('ventas.detalles', $lineaVenta->venta_id) }}" class="underline">#{{ $lineaVenta->venta_id }}</a>
                            @endif
                        </p>
                        @if ($lineaVenta)
                            <p class="mt-1">
                                {{ $lineaVenta->venta->nombreCliente() ?? 'Sin cliente' }} · {{ $lineaVenta->created_at->format('d/m/Y') }}
                                · Bs {{ number_format((float) $lineaVenta->subtotal, 2) }}
                                @if ($lineaVenta->garantia_fecha_exp)
                                    · garantía hasta {{ $lineaVenta->garantia_fecha_exp->format('d/m/Y') }}
                                @endif
                            </p>
                        @endif
                    </div>
                    @can('venta.detalle.delete')
                        <div class="m-2">
                            <x-danger-button wire:click="cancelarVenta()" wire:loading.attr="disabled">Anular la venta de este equipo</x-danger-button>
                        </div>
                    @endcan
                @elseif ($this->enReclamo())
                    {{-- Reclamo: lo escribe ReclamoService. Se cierra desde la compra. --}}
                    <div class="m-2 rounded-lg border border-rose-300 bg-rose-50 p-3 text-sm text-rose-900 dark:border-rose-700 dark:bg-rose-900/40 dark:text-rose-100">
                        @if ($reclamo)
                            <p class="font-semibold">En reclamo a {{ $reclamo->compra?->proveedor?->nombre }}</p>
                            <p class="mt-1">«{{ $reclamo->motivo }}» · desde el {{ $reclamo->created_at->format('d/m/Y') }}</p>
                            @can('compra.detalle')
                                <a href="{{ route('compras.detalle', $reclamo->compra_id) }}" class="mt-1 inline-block underline">Ir a la compra #{{ $reclamo->compra_id }} para cerrar el reclamo</a>
                            @endcan
                        @else
                            <p class="font-semibold">En reclamo, pero sin reclamo abierto registrado.</p>
                        @endif
                    </div>
                @elseif ($this->esReservado())
                    {{-- Reserva: la escribe ReservaService. Aqui se ve, se concreta o se cancela. --}}
                    <div class="m-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-100">
                        @if ($reserva)
                            <p class="font-semibold">Reservado para {{ $reserva->cliente?->nombre }}</p>
                            <p class="mt-1">
                                Seña Bs {{ number_format((float) $reserva->sena, 2) }} en {{ $reserva->metodo?->nombre }}
                                · desde el {{ $reserva->created_at->format('d/m/Y') }}
                                @if ($reserva->nota) · {{ $reserva->nota }} @endif
                            </p>
                        @else
                            <p class="font-semibold">En reserva, pero sin reserva activa registrada.</p>
                        @endif
                    </div>
                    @if ($reserva)
                        <div class="m-2 flex flex-wrap gap-2">
                            @can('venta.create')
                                <x-primary-button wire:click="concretarReserva()" wire:loading.attr="disabled">
                                    <i class="fa-solid fa-cash-register mr-1"></i> Concretar la venta
                                </x-primary-button>
                            @endcan
                            @can('reserva.cancelar')
                                <x-danger-button wire:click="cancelarReserva()" wire:loading.attr="disabled">Cancelar la reserva</x-danger-button>
                            @endcan
                        </div>
                    @endif
                @else
                    <div class="m-2">
                        <x-label>Estado:</x-label>
                        <x-select wire:model.live="estado" :options="$productoEstados" placeholder="Seleccione un estado" />
                        <x-input-error for="estado"></x-input-error>
                    </div>

                    @if ($producto->estaDisponible())
                        <div class="m-2 flex flex-wrap gap-2">
                            @can('venta.create')
                                <x-primary-button wire:click="vender()" wire:loading.attr="disabled">
                                    <i class="fa-solid fa-cash-register mr-1"></i> Vender este equipo
                                </x-primary-button>
                            @endcan
                            @can('reserva.create')
                                <x-secondary-button wire:click="reservar()" wire:loading.attr="disabled">
                                    <i class="fa-solid fa-bookmark mr-1"></i> Reservar
                                </x-secondary-button>
                            @endcan
                        </div>
                    @endif

                    <hr>

                    @if (in_array($estado, ['Fuera', 'Roto'], true))
                        <div class="m-2">
                            <x-label>
                                @if ($estado === 'Fuera') ¿A quién se le dio o dónde está? @else ¿Qué tiene? @endif
                            </x-label>
                            <x-input type="text" wire:model="nota" class="w-full"></x-input>
                            <x-input-error for="nota"></x-input-error>
                        </div>
                        @if ($estado === 'Fuera' && $estadoOrigen === 'Fuera')
                            <div class="m-2">
                                <x-primary-button wire:click="finalizarFuera()" wire:loading.attr="disabled">El equipo volvió al local</x-primary-button>
                            </div>
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
                            {{-- De qué sucursal salen las piezas. Queda congelado en cada línea:
                                 al terminar la reparación el equipo se muda al Almacén. --}}
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

                            <div class="flex gap-2" data-escaner>
                                <x-input type="text" class="w-full" placeholder="Escanee o escriba nombre, SKU o código..."
                                    wire:model.live.debounce.500ms="searchRepuesto"
                                    x-on:keydown.enter.prevent="$wire.elegirRepuestoPorCodigo($el.value); $el.value = ''"
                                    wire:keydown.escape="$set('searchRepuesto', '')"
                                    wire:keydown.tab="$set('searchRepuesto', '')" autocomplete="off"></x-input>
                                <x-boton-escaner />
                            </div>

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
                                <x-label>Piezas del negocio que se montan:</x-label>
                                <div class="overflow-x-auto mt-4">
                                    <table class="min-w-full border-collapse text-xs">
                                        <thead>
                                            <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                                <th class="p-2 border border-gray-300 dark:border-gray-600">Repuesto</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-600">Costo</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-600">Cant.</th>
                                                <th class="p-2 border border-gray-300 dark:border-gray-600">Subtotal</th>
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
                                <x-label>Mano de obra del técnico (Bs):</x-label>
                                <x-input type="number" wire:model.lazy="reparacion.costo" class="w-full" onfocus="this.select()"></x-input>
                                <x-input-error for="reparacion.costo"></x-input-error>
                            </div>
                            <div>
                                <x-label>Repuestos (Bs):</x-label>
                                <x-input type="number" wire:model.lazy="reparacion.costo_repuestos" class="w-full" onfocus="this.select()"></x-input>
                                <x-input-error for="reparacion.costo_repuestos"></x-input-error>
                            </div>
                            <div>
                                <x-label>Total reparación (Bs):</x-label>
                                <x-input type="number" :value="$reparacion['costo_total'] ?? 0" class="w-full" disabled="true"></x-input>
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

                        @if (isset($reparacion['id']))
                            <x-primary-button class="ml-2" wire:click="finalizarReparacion()"
                                wire:loading.attr="disabled">
                                Marcar Como Terminado
                            </x-primary-button>
                        @endif

                    @endif
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cerrar
                </x-secondary-button>

                @unless ($this->esVendido() || $this->esReservado() || $this->enReclamo())
                    <x-primary-button class="ml-2" wire:click="update()" wire:loading.attr="disabled">
                        Actualizar
                    </x-primary-button>
                @endunless
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
