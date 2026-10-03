<div>
    @if ($openModal && $ventaProducto)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                <div class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600 mr-2" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Confirmar Eliminación
                </div>
            </x-slot>

            <x-slot name="content">
                <p class="text-gray-700 dark:text-gray-300">
                    ¿Estás seguro de que deseas eliminar el siguiente detalle de la venta?
                </p>

                {{-- Sección de detalles del producto a eliminar --}}
                <div
                    class="mt-4 p-4 bg-gray-100 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                    <p
                        class="font-bold text-lg text-gray-900 dark:text-white mb-3 pb-3 border-b border-gray-300 dark:border-gray-500">
                        {{ $ventaProducto->producto->descripcion ?? 'Producto no encontrado' }}
                    </p>

                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Precio:</span>
                        <span class="font-semibold text-gray-800 dark:text-gray-200 text-right">$
                            {{ number_format($ventaProducto->precio, 2) }}</span>

                        <span class="text-gray-500 dark:text-gray-400">Descuento:</span>
                        <span class="font-semibold text-gray-800 dark:text-gray-200 text-right">$
                            {{ number_format($ventaProducto->descuento, 2) }}</span>

                        <span class="text-gray-500 dark:text-gray-400 font-bold">Subtotal:</span>
                        <span class="font-bold text-gray-900 dark:text-gray-100 text-right">$
                            {{ number_format($ventaProducto->subtotal, 2) }}</span>

                        <span
                            class="text-gray-500 dark:text-gray-400 pt-2 border-t border-gray-300 dark:border-gray-500 mt-2 col-span-2"></span>

                        <span class="text-gray-500 dark:text-gray-400">Tipo Cambio:</span>
                        <span class="font-semibold text-gray-800 dark:text-gray-200 text-right">Bs.
                            {{ number_format($ventaProducto->tipo_cambio, 2) }}</span>

                        <span class="text-gray-500 dark:text-gray-400 font-bold">Subtotal Bs.:</span>
                        <span class="font-bold text-gray-900 dark:text-gray-100 text-right">Bs.
                            {{ number_format($ventaProducto->subtotal_bs, 2) }}</span>
                    </div>
                </div>

                <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                    Esta acción no se puede deshacer. El producto será devuelto al inventario y el total de la venta
                    será recalculado.
                </p>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>

                <x-danger-button class="ml-2" wire:click="eliminarDetalle()" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="eliminarDetalle">
                        Sí, Eliminar
                    </span>
                    <span wire:loading wire:target="eliminarDetalle">
                        Eliminando...
                    </span>
                </x-danger-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
