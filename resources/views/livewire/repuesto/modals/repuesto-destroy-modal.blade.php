<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Eliminar Repuesto: {{ $repuesto->nombre }}
            </x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label>Nombre:</x-label>
                    <x-input type="text" value="{{ $repuesto->nombre }}" class="w-full" disabled="true"></x-input>
                </div>

                {{-- Solo para una pieza de reparación, igual que en crear y editar:
                     un accesorio no es pieza de ningún modelo y tiene esas tres
                     columnas en NULL a propósito (ver RepuestoAccesorioTrait).
                     Antes esto hacía `$repuesto->modelo->nombre` a pelo, así que el
                     modal reventaba con "property nombre on null" al intentar
                     eliminar cualquier accesorio. --}}
                @if ($repuesto->tieneCamposDeRepuesto())
                    <div class="m-2">
                        <x-label>Fabricante:</x-label>
                        <x-input value="{{ $repuesto->fabricante }}" class="w-full" disabled="true"></x-input>
                    </div>

                    <div class="m-2">
                        <x-label>Modelo:</x-label>
                        <x-input value="{{ $repuesto->modelo?->nombre ?? 'Sin modelo' }}" class="w-full"
                            disabled="true"></x-input>
                    </div>

                    <div class="m-2">
                        <x-label>Categoria:</x-label>
                        <x-input value="{{ $repuesto->categoria?->nombre ?? 'Sin categoría' }}" class="w-full"
                            disabled="true"></x-input>
                    </div>
                @endif

                <div class="m-2">
                    <x-label>Stock por sucursal:</x-label>
                    <div class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        {!! $repuesto->desgloseStock() !!}
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Al eliminar el artículo se borra también su stock de todas las sucursales.
                    </p>
                </div>

            </x-slot>
            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                <x-danger-button class="ml-2" wire:click="destroy()" wire:loading.attr="disabled">
                    Eliminar
                </x-danger-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
