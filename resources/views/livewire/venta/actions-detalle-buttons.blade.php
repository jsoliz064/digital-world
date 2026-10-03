<div>
    <x-dropdown-table>
        @can('venta.detalle.delete')
            <button wire:click="openVentaDetalleDestroyModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-red-700 hover:bg-gray-100 w-full text-left">
                Anular línea
            </button>
        @endcan
    </x-dropdown-table>
</div>
