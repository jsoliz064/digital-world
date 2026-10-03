<div>
    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <a href="{{ route('ventas') }}" class="text-sm text-brand-600 hover:underline">Volver a ventas</a>
        <div class="flex gap-2">
            @can('venta.edit')
                <x-secondary-button wire:click="ventaEditar">Editar</x-secondary-button>
            @endcan
            @can('venta.delete')
                <x-danger-button wire:click="anularVenta">Anular venta</x-danger-button>
            @endcan
        </div>
    </div>

    <div class="text-center mb-4">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Venta Nº {{ str_pad($venta->id, 6, '0', STR_PAD_LEFT) }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $venta->created_at->format('d/m/Y H:i') }} · {{ $venta->sucursal?->nombre }}</p>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 grid grid-cols-2 md:grid-cols-4 gap-4">
        <div>
            <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400">Cliente</h3>
            <p class="font-semibold text-gray-900 dark:text-white">{{ $venta->nombreCliente() ?? 'Sin cliente' }}</p>
        </div>
        <div>
            <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400">Vendedor</h3>
            <p class="font-semibold text-gray-900 dark:text-white">{{ $venta->user?->name ?? '—' }}</p>
        </div>
        <div>
            <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400">Líneas</h3>
            <p class="font-semibold text-gray-900 dark:text-white">{{ $venta->detalles_count }}</p>
        </div>
        <div>
            <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400">Total</h3>
            <p class="text-xl font-bold text-brand-700 dark:text-brand-300">Bs {{ number_format((float) $venta->total, 2) }}</p>
        </div>
    </div>

    <div class="mt-3 bg-white dark:bg-gray-800 shadow rounded-lg p-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div><span class="text-gray-500">Subtotal</span><br>Bs {{ number_format((float) $venta->subtotal, 2) }}</div>
        <div><span class="text-gray-500">Descuento</span><br>Bs {{ number_format((float) $venta->descuento, 2) }}</div>
        <div><span class="text-gray-500">Mano de obra</span><br>Bs {{ number_format((float) $venta->mano_obra, 2) }}</div>
        @can('venta.reporte')
            <div><span class="text-gray-500">Ganancia</span><br><span class="font-semibold text-green-700 dark:text-green-400">Bs {{ number_format($venta->ganancia(), 2) }}</span></div>
        @endcan
    </div>

    <div class="mt-6">
        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-2">Lo que se vendió</h3>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden py-2 px-2">
            @livewire('venta.venta-detalle-table', ['ventaId' => $venta->id], key('detalle-table-' . $venta->id))
        </div>
    </div>

    @livewire('venta.modals.venta-detalle-destroy-modal')
    @livewire('venta.modals.venta-destroy-modal')
</div>
