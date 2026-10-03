<div>
    <a href="{{ route('compras') }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium 
           bg-gray-600 text-white rounded-lg shadow hover:bg-gray-700 
           disabled:opacity-50 disabled:cursor-not-allowed transition">
        Volver
    </a>

    <div class="text-center mb-4">
        <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white">Compra del lote
            #{{ $compra_lote->id }}
        </h2>
        <p>Fecha: {{ $compra_lote->fecha_compra }}</p>
    </div>

    <x-collapse-card title="Modelos de Productos" :open="false">
        @livewire('compra-lote.compra-model-selector', ['compraId' => $compra_lote->id])
    </x-collapse-card>
    
    @can('producto.estado-masivo')
    <div class="flex justify-start mb-2">
        <button wire:click="openProductoEstadoMasivoModal()" wire:loading.attr="disabled"
            class="inline-flex items-center px-3 py-1.5 text-sm font-medium 
           bg-yellow-600 text-white rounded-lg shadow hover:bg-yellow-700 
           disabled:opacity-50 disabled:cursor-not-allowed transition"
            title="Cambiar estado o tipo de venta de los productos de este lote">
            Cambio Masivo

            <div wire:loading wire:target="openProductoEstadoMasivoModal"
                class="ml-2 inline-block h-4 w-4 border-2 border-white border-t-transparent rounded-full animate-spin">
            </div>
        </button>
    </div>
    @endcan
    @livewire('compra-lote.compra-lote-table', ['compraId' => $compra_lote->id])
    @livewire('compra-lote.modals.compra-lote-add-model-modal', ['compra_lote' => $compra_lote])
    @livewire('compra-lote.modals.compra-lote-producto-edit-modal')
    @livewire('compra-lote.modals.compra-lote-producto-destroy-modal', ['compra_lote' => $compra_lote])
    @livewire('producto.modals.producto-estado-modal')
    @livewire('producto.modals.producto-estado-masivo-modal')

</div>
