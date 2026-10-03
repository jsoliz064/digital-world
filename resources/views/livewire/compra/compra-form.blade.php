<div>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
            {{ $this->esEdicion() ? 'Editar compra #' . $compraId : 'Nueva compra' }}
        </h2>
        <a href="{{ $this->esEdicion() ? route('compras.detalle', $compraId) : route('compras') }}"
            class="text-sm text-brand-600 hover:underline">Volver</a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <x-label>Proveedor</x-label>
            <x-select wire:model="compra.proveedor_id" :options="$proveedores->pluck('nombre', 'id')" placeholder="Seleccione el proveedor" />
            <x-input-error for="compra.proveedor_id" />
        </div>
        <div>
            <x-label>Fecha</x-label>
            <x-input type="date" wire:model="compra.fecha" class="w-full" />
            <x-input-error for="compra.fecha" />
        </div>
        <div>
            <x-label>Sucursal (a donde entra)</x-label>
            @if ($this->esEdicion())
                <x-input type="text" class="w-full" disabled="true"
                    value="{{ $sucursales->firstWhere('id', $compra['sucursal_id'])?->nombre ?? '—' }}" />
                <p class="mt-1 text-xs text-gray-500">El stock ya entró en esta sucursal: no se cambia.</p>
            @else
                <x-select wire:model.live="compra.sucursal_id" :options="$sucursales->pluck('nombre', 'id')" placeholder="Seleccione la sucursal" />
                <x-input-error for="compra.sucursal_id" />
            @endif
        </div>
    </div>

    <div class="mt-4 bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <h3 class="font-semibold text-gray-800 dark:text-gray-100">Repuestos y accesorios</h3>
            <div class="flex gap-2">
                @can('repuesto.create')
                    <button type="button" wire:click="abrirCrearArticulo('Repuesto')"
                        class="text-xs px-2 py-1 rounded border border-brand-300 text-brand-700 hover:bg-brand-50 dark:text-brand-300">+ Repuesto nuevo</button>
                @endcan
                @can('accesorio.create')
                    <button type="button" wire:click="abrirCrearArticulo('Accesorio')"
                        class="text-xs px-2 py-1 rounded border border-brand-300 text-brand-700 hover:bg-brand-50 dark:text-brand-300">+ Accesorio nuevo</button>
                @endcan
            </div>
        </div>

        @include('livewire.partials.buscador-articulos', ['placeholder' => 'Escanee o escriba código, SKU o nombre...', 'escanerContinuo' => true])

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                    <tr>
                        <th class="p-2 text-left">Artículo</th>
                        <th class="p-2 text-right w-24">Cantidad</th>
                        <th class="p-2 text-right w-32">Costo unit. Bs</th>
                        <th class="p-2 text-right w-28">Subtotal</th>
                        <th class="p-2 w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lineas as $index => $linea)
                        {{-- wire:key con el indice: el wire:model se enlaza por posicion. --}}
                        <tr class="border-t border-gray-200 dark:border-gray-700" wire:key="linea-{{ $linea['tipo'] }}-{{ $linea['id'] }}-{{ $index }}">
                            <td class="p-2">
                                {!! \App\Enums\LineaTipo::badge($linea['tipo']) !!}
                                <span class="text-gray-900 dark:text-gray-100">{{ $linea['nombre'] }}</span>
                                @if ($linea['sku'])
                                    <span class="text-xs text-gray-500">· {{ $linea['sku'] }}</span>
                                @endif
                            </td>
                            <td class="p-2"><x-input type="number" min="1" class="w-full text-right" wire:model.live.debounce.400ms="lineas.{{ $index }}.cantidad" onfocus="this.select()" /></td>
                            <td class="p-2"><x-input type="number" min="0" step="0.01" class="w-full text-right" wire:model.live.debounce.400ms="lineas.{{ $index }}.costo" onfocus="this.select()" /></td>
                            <td class="p-2 text-right">{{ number_format((int) $linea['cantidad'] * (float) $linea['costo'], 2) }}</td>
                            <td class="p-2 text-center">
                                <button type="button" wire:click="quitarLinea({{ $index }})" class="text-red-600 hover:text-red-800 text-lg" title="Quitar">&times;</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-4 text-center text-gray-400">
                                Sin repuestos ni accesorios. Una compra puede traer solo equipos: se cargan después en su detalle.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t border-gray-300 dark:border-gray-600 font-semibold">
                        <td class="p-2" colspan="3">Total de artículos</td>
                        <td class="p-2 text-right">Bs {{ number_format($this->total(), 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            @error('lineas.*.cantidad') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            @error('lineas.*.costo') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            <x-input-error for="detalles" />
        </div>
    </div>

    <div class="mt-4 flex justify-end gap-2">
        <a href="{{ $this->esEdicion() ? route('compras.detalle', $compraId) : route('compras') }}">
            <x-secondary-button>Cancelar</x-secondary-button>
        </a>
        <x-primary-button wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">
            {{ $this->esEdicion() ? 'Guardar cambios' : 'Registrar compra' }}
        </x-primary-button>
    </div>

    @livewire('articulo.modals.articulo-selector-modal')
    @livewire('articulo.modals.articulo-form-modal', ['tipo' => 'Repuesto', 'conStock' => false], key('alta-repuesto'))
    @livewire('articulo.modals.articulo-form-modal', ['tipo' => 'Accesorio', 'conStock' => false], key('alta-accesorio'))
</div>
