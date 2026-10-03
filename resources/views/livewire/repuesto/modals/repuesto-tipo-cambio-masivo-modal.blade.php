<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Actualizar Tipo de Cambio
            </x-slot>

            <x-slot name="content">
                <hr>

                @if (!$confirmando)
                    {{-- Paso 1: contexto + valor nuevo --}}
                    <div class="mt-4">
                        <x-label>Tipo de cambio actual del catalogo</x-label>
                        <div
                            class="mt-1 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden text-sm">
                            <table class="min-w-full">
                                <thead class="bg-gray-100 dark:bg-gray-700">
                                    <tr>
                                        <th class="p-2 text-left font-medium">Tipo de cambio</th>
                                        <th class="p-2 text-right font-medium">Repuestos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($resumenActual as $fila)
                                        <tr class="border-t border-gray-200 dark:border-gray-700">
                                            <td class="p-2">{{ number_format($fila->tipo_cambio, 2) }}</td>
                                            <td class="p-2 text-right">{{ $fila->total }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="p-3 text-center text-gray-400">
                                                No hay repuestos registrados.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4">
                        <x-label>Nuevo tipo de cambio</x-label>
                        <x-input type="number" step="0.01" min="1" class="w-full" wire:model="tipoCambio"
                            placeholder="Ej: 6.96" autocomplete="off" />
                        <x-input-error for="tipoCambio" class="mt-1" />
                    </div>

                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                        Se aplicara a los <span class="font-semibold">{{ $totalRepuestos }}</span> repuestos del
                        catalogo. Los precios y costos en dolares no se modifican.
                    </p>
                @else
                    {{-- Paso 2: confirmacion explicita --}}
                    <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700 p-4">
                        <p class="text-sm text-gray-800 dark:text-gray-100">
                            Se pondra el tipo de cambio en
                            <span class="font-bold">{{ number_format((float) $tipoCambio, 2) }}</span>
                            para los
                            <span class="font-bold">{{ $totalRepuestos }}</span>
                            repuestos del catalogo, reemplazando el valor que cada uno tenga hoy.
                        </p>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            Los precios y costos en dolares no cambian, y las ventas y compras ya registradas
                            conservan el tipo de cambio con el que se hicieron.
                        </p>
                    </div>
                @endif
            </x-slot>

            <x-slot name="footer">
                @if (!$confirmando)
                    <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                        Cancelar
                    </x-secondary-button>
                    <x-primary-button class="ml-2" wire:click="confirmar()" wire:loading.attr="disabled">
                        Continuar
                    </x-primary-button>
                @else
                    <x-secondary-button wire:click="volver()" wire:loading.attr="disabled">
                        Volver
                    </x-secondary-button>
                    <x-danger-button class="ml-2" wire:click="update()" wire:loading.attr="disabled">
                        Si, actualizar {{ $totalRepuestos }} repuesto(s)
                    </x-danger-button>
                @endif
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
