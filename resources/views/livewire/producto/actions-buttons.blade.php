<div>
    <x-dropdown-table>
        @can('producto.edit')
            <button wire:click="openProductoEditModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Editar
            </button>
        @endcan
        @if ($row->estado == 'Vendido')
            @can('producto.garantia')
                <button wire:click="openProductoGarantiaModal({{ $row->id }})"
                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                    Garantia
                </button>
            @endcan
            @can('producto.trabajo-externo')
                <button wire:click="openProductoTrabajoExternoModal({{ $row->id }})"
                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                    Trabajo Externo
                </button>
            @endcan
        @endif
        @can('producto.historial')
            <button wire:click="openProductoHistorial({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Historial
            </button>
        @endcan
        @can('producto.delete')
            <button wire:click="openProductoDestroyModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-red-600 hover:bg-red-100 w-full text-left">
                Eliminar
            </button>
        @endcan
    </x-dropdown-table>
</div>
