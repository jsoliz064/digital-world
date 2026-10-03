<div class="flex items-center space-x-1 relative overflow-visible">
    @can('compra.productos')
        <button wire:click="AgregarProductosCompra({{ $row->id }})"
            class="p-1 text-brand-600 hover:text-brand-900 hover:bg-brand-50 rounded-md" title="Agregar productos">
            <img src="{{ asset('icons/add.svg') }}" class="h-5 w-5" alt="Agregar productos">
        </button>
    @endcan
    <x-dropdown-table>
        @can('compra.edit')
            <button wire:click="openCompraEditModal({{ $row->id }})"
                class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900"
                role="menuitem">
                <img src="{{ asset('icons/edit.svg') }}" class="h-5 w-5 mr-2 text-gray-400" alt="Editar">
                Editar
            </button>
        @endcan

        {{-- @can('compra.delete')
            <button wire:click="openCompraDestroyModal({{ $row->id }})"
                class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-gray-100 hover:text-red-800"
                role="menuitem">
                <img src="{{ asset('icons/remove.svg') }}" class="h-5 w-5 mr-2 text-red-400" alt="Eliminar">
                Eliminar
            </button>
        @endcan --}}
    </x-dropdown-table>
</div>
