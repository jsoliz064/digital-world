<div>
    @if ($openModal && $ventaRepuesto)
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
                    ¿Estás seguro de que deseas eliminar el siguiente detalle de la venta de repuestos?
                </p>

                {{-- Sección de detalles del producto a eliminar --}}
                <div
                    class="mt-4 p-4 bg-gray-100 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                    <p
                        class="font-bold text-lg text-gray-900 dark:text-white mb-3 pb-3 border-b border-gray-300 dark:border-gray-500">
                        Venta de Repuesto Nro. {{ $ventaRepuesto->id }}
                    </p>

                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Repuestos:</span>
                        <span class="font-semibold text-gray-800 dark:text-gray-200 text-right">
                            {{ $ventaRepuesto->cantidad_repuestos }}</span>

                        <span class="text-gray-500 dark:text-gray-400 font-bold">Subtotal:</span>
                        <span class="font-bold text-gray-900 dark:text-gray-100 text-right">$
                            {{ number_format($ventaRepuesto->subtotal, 2) }}</span>

                        <span class="text-gray-500 dark:text-gray-400">Descuento:</span>
                        <span class="font-semibold text-gray-800 dark:text-gray-200 text-right">$
                            {{ number_format($ventaRepuesto->descuento, 2) }}</span>

                        <span class="text-gray-500 dark:text-gray-400 font-bold">total:</span>
                        <span class="font-bold text-gray-900 dark:text-gray-100 text-right">$
                            {{ number_format($ventaRepuesto->total, 2) }}</span>

                        <span
                            class="text-gray-500 dark:text-gray-400 pt-2 border-t border-gray-300 dark:border-gray-500 mt-2 col-span-2"></span>

                        <span class="text-gray-500 dark:text-gray-400">Tipo Cambio:</span>
                        <span class="font-semibold text-gray-800 dark:text-gray-200 text-right">
                            {{ number_format((float) $ventaRepuesto->tipo_cambio, 2) }}
                        </span>

                        {{-- Lo que se cobro por encima (o por debajo) de la conversion.
                             Solo si lo hubo: en una venta normal esta linea no existe. --}}
                        @if (abs((float) $ventaRepuesto->ajuste_bs) >= 0.01)
                            <span class="text-gray-500 dark:text-gray-400">Ajuste:</span>
                            <span class="font-semibold text-right {{ $ventaRepuesto->ajuste_bs > 0 ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">
                                Bs. {{ $ventaRepuesto->ajuste_bs > 0 ? '+' : '' }}{{ number_format((float) $ventaRepuesto->ajuste_bs, 2) }}
                            </span>
                        @endif

                        <span class="text-gray-500 dark:text-gray-400 font-bold">Total Bs.:</span>
                        <span class="font-bold text-gray-900 dark:text-gray-100 text-right">Bs.
                            {{ number_format((float) $ventaRepuesto->total_bs, 2) }}
                        </span>
                    </div>
                </div>

                {{-- El aviso depende de si la venta nace de un equipo: para esas
                     lineas el stock NO vuelve, porque nunca salio de aqui. --}}
                @if ($ventaRepuesto->esEnlazada())
                    <div class="mt-4 rounded-lg bg-amber-50 dark:bg-amber-900 p-3 text-sm">
                        <p class="font-semibold text-amber-900 dark:text-amber-100">
                            Esta venta se cobró junto a la venta de producto #{{ $ventaRepuesto->venta_id }}.
                        </p>
                        <p class="mt-1 text-amber-800 dark:text-amber-200">
                            Los repuestos <span class="font-semibold">no</span> vuelven al inventario: siguen montados
                            en el equipo y su stock se descontó al repararlo. Quedarán disponibles para volver a
                            cobrarse en otra venta.
                        </p>
                    </div>
                @endif

                <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                    Esta acción no se puede deshacer. La venta será eliminada
                    @unless ($ventaRepuesto->esEnlazada())
                        y los repuestos serán devueltos al inventario.
                    @else
                        .
                    @endunless
                </p>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>

                <x-danger-button class="ml-2" wire:click="destroy()" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="destroy">
                        Sí, Eliminar
                    </span>
                    <span wire:loading wire:target="destroy">
                        Eliminando...
                    </span>
                </x-danger-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
