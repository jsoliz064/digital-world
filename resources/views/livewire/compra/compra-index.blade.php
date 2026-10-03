<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">Compras de Lotes</h2>
    @can('compra.create')
        <x-primary-button wire:click="openCompraCreateModal()">
            Registrar Compra de Lote
        </x-primary-button>
    @endcan
    <div class="mt-4">
        @livewire('compra.compra-table')
    </div>
    @livewire('compra.modals.compra-create-modal')
    @livewire('compra.modals.compra-edit-modal')
    @livewire('compra.modals.compra-destroy-modal')
</div>
