<div>
    @if ($openModal && $venta)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">Anular venta #{{ $venta->id }}</x-slot>
            <x-slot name="content">
                <hr>
                <p class="m-2 text-sm text-gray-700 dark:text-gray-300">
                    Se anulan sus {{ $venta->detalles_count }} línea(s) por Bs {{ number_format((float) $venta->total, 2) }}:
                    los equipos vuelven al inventario, los repuestos y accesorios a su stock, y los cobros de taller se descobran.
                </p>
            </x-slot>
            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">Cancelar</x-secondary-button>
                <x-danger-button class="ml-2" wire:click="anular()" wire:loading.attr="disabled">Anular venta</x-danger-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
