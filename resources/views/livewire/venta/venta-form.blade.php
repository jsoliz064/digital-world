<div>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
            {{ $this->esEdicion() ? 'Editar venta #' . $ventaId : 'Nueva venta' }}
        </h2>
        <a href="{{ $this->esEdicion() ? route('ventas.detalles', $ventaId) : route('ventas') }}"
            class="text-sm text-brand-600 hover:underline">Volver</a>
    </div>

    {{-- Cabecera --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <x-label value="Sucursal" />
            {{-- Se bloquea en cuanto hay lineas (o en edicion): el stock sale de ella. --}}
            @if (!$this->esEdicion() && count($lineas) === 0)
                <select wire:model.live="venta.sucursal_id"
                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm h-10">
                    <option value="">Seleccione una sucursal</option>
                    @foreach ($sucursales as $sucursal)
                        <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                    @endforeach
                </select>
                <x-input-error for="venta.sucursal_id" class="mt-1" />
            @else
                <x-input type="text" disabled="true" class="mt-1 w-full"
                    value="{{ $sucursales->firstWhere('id', $venta['sucursal_id'])?->nombre ?? '—' }}" />
            @endif
        </div>
        <div>
            <x-label value="Precio de lista (equipos)" />
            <select wire:model.live="tipo_precio"
                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm h-10">
                <option value="Vendedor">Vendedor</option>
                <option value="Cliente">Cliente</option>
            </select>
        </div>
        <div>
            <x-cliente-picker :search="$searchCliente" :sugerencias="$filteredClientes"
                :nombre="$venta['cliente'] ?? null" :cliente-id="$venta['cliente_id'] ?? null" label="Cliente" />
            <x-input-error for="cliente" class="mt-1" />
            {{-- Avisa, no bloquea (docs/04): venderle a quien ya debe es decision del vendedor. --}}
            @if ($deudaCliente > 0)
                <p class="mt-1 text-xs font-semibold text-amber-700 dark:text-amber-300">
                    <i class="fa-solid fa-triangle-exclamation"></i> Ya debe Bs {{ number_format($deudaCliente, 2) }} de otras ventas.
                </p>
            @endif
        </div>
    </div>

    {{-- Buscador --}}
    <div class="mt-4 bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        @include('livewire.partials.buscador-articulos', ['placeholder' => 'Escanee o escriba IMEI, código, SKU o nombre...'])
        <div class="mt-2">
            <button type="button" wire:click="abrirSelectorEquipos" @disabled($this->sucursalDelDocumento() === null)
                class="text-sm text-brand-600 hover:underline disabled:opacity-40">
                <i class="fa-solid fa-mobile-screen-button"></i> Ver equipos disponibles
            </button>
        </div>
    </div>

    {{-- Lineas --}}
    @if (count($lineas) > 0 || count($cobrosExistentes) > 0)
        <div class="mt-4 bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                        <tr>
                            <th class="p-2 text-left">Detalle</th>
                            <th class="p-2 text-right w-20">Cant.</th>
                            <th class="p-2 text-right w-28">Precio Bs</th>
                            <th class="p-2 text-right w-24">Desc. Bs</th>
                            <th class="p-2 text-right w-20">Garantía</th>
                            <th class="p-2 text-right w-28">Subtotal</th>
                            <th class="p-2 w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lineas as $index => $linea)
                            @php($esEquipo = $linea['tipo'] === 'Producto')
                            {{-- wire:key con el indice: los inputs se enlazan por posicion. --}}
                            <tr class="border-t border-gray-200 dark:border-gray-700 align-top" wire:key="linea-{{ $linea['tipo'] }}-{{ $linea['id'] }}-{{ $index }}">
                                <td class="p-2">
                                    {!! \App\Enums\LineaTipo::badge($linea['tipo']) !!}
                                    <span class="text-gray-900 dark:text-gray-100">{{ $linea['descripcion'] }}</span>
                                    @if ($linea['codigo'])
                                        <span class="block text-xs text-gray-500 font-mono">{{ $esEquipo ? 'IMEI ' : 'SKU ' }}{{ $linea['codigo'] }}</span>
                                    @endif
                                    @if (!$esEquipo)
                                        <span class="block text-xs text-gray-500">Stock aquí: {{ $linea['stock'] }}</span>
                                    @endif
                                    @if ($esEquipo && ($linea['repuestos_elegibles'] ?? 0) > 0)
                                        @php($elegidos = count($repuestosVenta[$linea['id']] ?? []))
                                        <button type="button" wire:click="abrirRepuestosDe({{ $linea['id'] }})"
                                            class="block text-xs underline {{ $elegidos ? 'text-green-600 dark:text-green-400 font-semibold' : 'text-brand-600 dark:text-brand-400' }}">
                                            {{ $elegidos ? $elegidos . ' repuesto(s) de taller a cobrar' : $linea['repuestos_elegibles'] . ' repuesto(s) de taller cobrable(s)' }}
                                        </button>
                                    @endif
                                </td>
                                <td class="p-2">
                                    @if ($esEquipo)
                                        <span class="block text-right">1</span>
                                    @else
                                        <x-input type="number" min="1" max="{{ $linea['stock'] }}" class="w-full text-right"
                                            wire:model.live.debounce.400ms="lineas.{{ $index }}.cantidad" onfocus="this.select()" />
                                    @endif
                                </td>
                                <td class="p-2"><x-input type="number" min="0" step="0.01" class="w-full text-right" wire:model.live.debounce.400ms="lineas.{{ $index }}.precio" onfocus="this.select()" /></td>
                                <td class="p-2"><x-input type="number" min="0" step="0.01" class="w-full text-right" wire:model.live.debounce.400ms="lineas.{{ $index }}.descuento" onfocus="this.select()" /></td>
                                <td class="p-2">
                                    @if ($esEquipo)
                                        <x-input type="number" min="0" max="60" class="w-full text-right" wire:model.live.debounce.400ms="lineas.{{ $index }}.garantia_meses" title="Meses de garantía" />
                                    @else
                                        <span class="block text-right text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="p-2 text-right whitespace-nowrap">{{ number_format($this->subtotalLinea($linea), 2) }}</td>
                                <td class="p-2 text-center">
                                    <button type="button" wire:click="quitarLinea({{ $index }})" class="text-red-600 hover:text-red-800 text-lg" title="Quitar">&times;</button>
                                </td>
                            </tr>
                        @endforeach
                        @foreach ($cobrosExistentes as $cobro)
                            <tr class="border-t border-gray-200 dark:border-gray-700 bg-green-50 dark:bg-green-900/30" wire:key="cobro-{{ $loop->index }}">
                                <td class="p-2" colspan="5">
                                    {!! \App\Enums\LineaTipo::badge('Repuesto') !!} {{ $cobro['nombre'] }}
                                    <span class="block text-xs text-gray-500">Cobro de taller (ya registrado). Se anula desde el detalle de la venta.</span>
                                </td>
                                <td class="p-2 text-right">{{ number_format($cobro['subtotal'], 2) }}</td>
                                <td></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('lineas.*.cantidad') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            <x-input-error for="lineas" class="mt-1" />
            <x-input-error for="detalles" class="mt-1" />
        </div>

        {{-- Totales --}}
        @php($t = $this->totales())
        <div class="mt-4 bg-white dark:bg-gray-800 rounded-lg shadow p-4 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <x-label value="Subtotal Bs" />
                <p class="mt-2 text-lg font-semibold">{{ number_format($t['subtotal'], 2) }}</p>
                @if ($this->totalCobrosNuevos() > 0)
                    <p class="text-xs text-green-700 dark:text-green-300">Incluye Bs {{ number_format($this->totalCobrosNuevos(), 2) }} de repuestos de taller</p>
                @endif
            </div>
            <div>
                <x-label value="Descuento Bs" />
                <x-input type="number" min="0" step="0.01" class="mt-1 w-full" wire:model.live.debounce.400ms="venta.descuento" onfocus="this.select()" />
                <x-input-error for="venta.descuento" />
            </div>
            <div>
                <x-label value="Mano de obra Bs" />
                <x-input type="number" min="0" step="0.01" class="mt-1 w-full" wire:model.live.debounce.400ms="venta.mano_obra" onfocus="this.select()" />
                <x-input-error for="venta.mano_obra" />
            </div>
            <div>
                <x-label value="Total Bs" />
                <p class="mt-2 text-2xl font-bold text-brand-700 dark:text-brand-300">{{ number_format($t['total'], 2) }}</p>
            </div>
        </div>

        {{-- Cobro --}}
        @php($saldo = $this->saldoPrevisto())
        <div class="mt-4 bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Cobro</h3>
                @if (!$this->esEdicion())
                    <button type="button" wire:click="agregarPago" class="text-sm text-brand-600 hover:underline">
                        <i class="fa-solid fa-plus"></i> Agregar método
                    </button>
                @endif
            </div>

            @if ($this->esEdicion())
                {{-- Los cobros nuevos van por el modal de cobro, desde el detalle. --}}
                <ul class="mt-2 divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                    @forelse ($pagosExistentes as $pago)
                        <li class="py-1 flex justify-between" wire:key="pago-existente-{{ $loop->index }}">
                            <span class="text-gray-600 dark:text-gray-300">{{ $pago['fecha'] }} · {{ $pago['metodo'] }}</span>
                            <span>Bs {{ number_format($pago['monto'], 2) }}</span>
                        </li>
                    @empty
                        <li class="py-1 text-gray-500">Sin pagos registrados.</li>
                    @endforelse
                </ul>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Para cobrar o anular un pago, usa el detalle de la venta.</p>
            @else
                <div class="mt-2 space-y-2">
                    @foreach ($pagos as $i => $pago)
                        <div class="flex gap-2" wire:key="pago-{{ $i }}">
                            <select wire:model.live="pagos.{{ $i }}.metodo_pago_id"
                                class="block w-1/2 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm h-10">
                                <option value="">Método...</option>
                                @foreach ($metodos as $metodo)
                                    <option value="{{ $metodo->id }}">{{ $metodo->nombre }}</option>
                                @endforeach
                            </select>
                            <x-input type="number" min="0" step="0.01" class="w-1/2 text-right"
                                wire:model.live.debounce.400ms="pagos.{{ $i }}.monto" onfocus="this.select()" />
                            @if (count($pagos) > 1)
                                <button type="button" wire:click="quitarPago({{ $i }})" class="text-red-600 hover:text-red-800 text-lg px-1" title="Quitar">&times;</button>
                            @endif
                        </div>
                    @endforeach
                </div>
                @error('pagos.*.monto') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            @endif
            <x-input-error for="pagos" class="mt-1" />

            <div class="mt-3 flex flex-wrap justify-between gap-2 text-sm">
                <span>Cobrado: <strong>Bs {{ number_format($this->cobradoPrevisto(), 2) }}</strong></span>
                @if ($saldo > 0)
                    <span class="font-semibold text-amber-700 dark:text-amber-300">
                        <i class="fa-solid fa-hand-holding-dollar"></i> Queda a crédito: Bs {{ number_format($saldo, 2) }}
                        @if (empty($venta['cliente_id']))
                            · elige el cliente
                        @endif
                    </span>
                @elseif ($saldo < 0)
                    <span class="font-semibold text-red-600">Se cobra Bs {{ number_format(-$saldo, 2) }} de más</span>
                @else
                    <span class="font-semibold text-green-700 dark:text-green-300">Pagada</span>
                @endif
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <x-primary-button wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">
                {{ $this->esEdicion() ? 'Guardar cambios' : 'Registrar venta' }}
            </x-primary-button>
        </div>
    @elseif ($this->sucursalDelDocumento() !== null)
        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400 text-center">Escanee o busque lo que se vende.</p>
    @endif

    @livewire('articulo.modals.articulo-selector-modal')
    @livewire('producto.modals.producto-selector-modal')
    @livewire('producto.modals.producto-repuestos-venta-modal')
    @livewire('cliente.modals.cliente-selector-modal')
    @livewire('cliente.modals.cliente-create-modal')
</div>
