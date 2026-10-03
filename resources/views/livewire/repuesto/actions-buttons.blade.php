<div>
    <x-dropdown-table>
        @can($permiso . '.edit')
            <button wire:click="openRepuestoEditModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Editar
            </button>
        @endcan

        @can('repuesto.transferir')
            <button wire:click="openRepuestoTransferenciaModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Transferir
            </button>
        @endcan

        @can('repuesto.historial')
            <button wire:click="openRepuestoHistorial({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Historial
            </button>
        @endcan

        @can($permiso . '.delete')
            <button wire:click="openRepuestoDestroyModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-red-600 hover:bg-red-100 w-full text-left">
                Eliminar
            </button>
        @endcan
    </x-dropdown-table>
</div>
