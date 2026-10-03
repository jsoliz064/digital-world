<div>
    <div class="flex items-center justify-between">
        <a href="{{ route('compras') }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium bg-gray-600 text-white rounded-lg shadow hover:bg-gray-700 transition">
            Volver
        </a>
        @can('compra.edit')
            <a href="{{ route('compras.editar', $compra->id) }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium bg-brand-600 text-white rounded-lg shadow hover:bg-brand-700 transition">
                Editar compra
            </a>
        @endcan
    </div>

    <div class="text-center my-4">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Compra #{{ $compra->id }}</h2>
        <p class="text-sm text-gray-600 dark:text-gray-300">
            {{ $compra->proveedor?->nombre }} · {{ $compra->fecha->format('d/m/Y') }}
            · Entra a {{ $compra->sucursal?->nombre ?? '—' }}
        </p>
    </div>

    <div class="grid grid-cols-3 gap-3 mb-4">
        <div class="bg-brand-100 dark:bg-brand-900 p-3 rounded-xl border border-brand-200 dark:border-brand-700">
            <p class="text-xs text-brand-700 dark:text-brand-300">Equipos</p>
            <p class="text-xl font-semibold text-brand-900 dark:text-brand-100">{{ $equipos }}</p>
        </div>
        <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded-xl border border-blue-200 dark:border-blue-700">
            <p class="text-xs text-blue-700 dark:text-blue-300">Artículos</p>
            <p class="text-xl font-semibold text-blue-900 dark:text-blue-100">{{ $articulos->sum('cantidad') }}</p>
        </div>
        <div class="bg-desert-100 dark:bg-desert-900 p-3 rounded-xl border border-desert-200 dark:border-desert-700">
            <p class="text-xs text-desert-700 dark:text-desert-200">Total</p>
            <p class="text-xl font-semibold text-desert-900 dark:text-desert-50">Bs {{ number_format((float) $compra->total, 2) }}</p>
        </div>
    </div>

    <x-collapse-card title="Agregar equipos por modelo" :open="false">
        @livewire('compra-lote.compra-model-selector', ['compraId' => $compra->id])
    </x-collapse-card>

    @can('producto.estado-masivo')
        <div class="flex justify-start mb-2">
            <button wire:click="openProductoEstadoMasivoModal()" wire:loading.attr="disabled"
                class="inline-flex items-center px-3 py-1.5 text-sm font-medium bg-yellow-600 text-white rounded-lg shadow hover:bg-yellow-700 disabled:opacity-50 transition"
                title="Cambiar estado o tipo de venta de los equipos de esta compra">
                Cambio masivo
            </button>
        </div>
    @endcan

    @livewire('compra-lote.compra-lote-table', ['compraId' => $compra->id])

    <x-collapse-card title="Repuestos y accesorios de la compra" :open="$articulos->isNotEmpty()">
        @if ($articulos->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400 p-2">
                Esta compra no trae repuestos ni accesorios.
                @can('compra.edit')
                    <a href="{{ route('compras.editar', $compra->id) }}" class="text-brand-600 hover:underline">Agregarlos</a>
                @endcan
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700">
                        <tr>
                            <th class="p-2 text-left">Artículo</th>
                            <th class="p-2 text-right">Cantidad</th>
                            <th class="p-2 text-right">Costo unit.</th>
                            <th class="p-2 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($articulos as $d)
                            <tr class="border-t border-gray-200 dark:border-gray-700" wire:key="cd-{{ $d->id }}">
                                <td class="p-2">{!! \App\Enums\LineaTipo::badge($d->tipo) !!} {{ $d->articulo()?->nombre }}</td>
                                <td class="p-2 text-right">{{ $d->cantidad }}</td>
                                <td class="p-2 text-right">Bs {{ number_format((float) $d->costo, 2) }}</td>
                                <td class="p-2 text-right">Bs {{ number_format((float) $d->subtotal, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-collapse-card>

    @livewire('compra-lote.modals.compra-lote-add-model-modal', ['compra_lote' => $compra])
    @livewire('compra-lote.modals.compra-lote-producto-edit-modal')
    @livewire('compra-lote.modals.compra-lote-producto-destroy-modal')
    @livewire('producto.modals.producto-estado-modal')
    @livewire('producto.modals.producto-estado-masivo-modal')
</div>
