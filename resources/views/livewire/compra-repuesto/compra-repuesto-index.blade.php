<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">Compras de Repuestos</h2>
    @can('compra.repuesto.create')
        <x-primary-button wire:click="compraRepuestoCreate()">
            Registrar Compra de Repuestos y Accesorios
        </x-primary-button>
    @endcan
    <div class="mt-4">
        @livewire('compra-repuesto.compra-repuesto-table')
    </div>
    @livewire('compra-repuesto.modals.compra-repuesto-destroy-modal')
</div>
