<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Registrar nueva compra de lote
            </x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label>Fecha:</x-label>
                    <x-input type="date" wire:model="compra.fecha_compra" class="w-full"></x-input>
                    <x-input-error for="compra.fecha_compra"></x-input-error>
                </div>

                <div class="m-2">
                    <x-label>Tipo de Cambio USD/BOB:</x-label>
                    <x-input type="number" wire:model="compra.tipo_cambio" class="w-full"></x-input>
                    <x-input-error for="compra.tipo_cambio"></x-input-error>
                </div>

                <div class="m-2">
                    <x-label>Proveedor:</x-label>
                    <x-select wire:model="compra.proveedor_id" :options="$proveedores->pluck('nombre', 'id')"
                        placeholder="Seleccione un proveedor" />
                    <x-input-error for="compra.proveedor_id"></x-input-error>
                </div>

            </x-slot>
            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                <x-primary-button class="ml-2" wire:click="store()" wire:loading.attr="disabled">
                    Guardar
                </x-primary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
