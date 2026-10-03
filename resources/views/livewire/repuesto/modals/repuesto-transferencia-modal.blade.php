<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Transferir Stock
            </x-slot>

            <x-slot name="content">
                <hr>

                @if (!$repuesto)
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                        El artículo ya no existe.
                    </p>
                @else
                    <div class="mt-4">
                        <x-label>Artículo</x-label>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {!! $repuesto->getNombreConColor() !!}
                        </p>
                    </div>

                    {{-- El reparto actual a la vista: sin esto el usuario elige el
                         origen a ciegas y descubre que no hay stock al confirmar. --}}
                    <div class="mt-4">
                        <x-label>Stock por sucursal</x-label>
                        <div class="mt-1 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden text-sm">
                            <table class="min-w-full">
                                <thead class="bg-gray-100 dark:bg-gray-700">
                                    <tr>
                                        <th class="p-2 text-left font-medium">Sucursal</th>
                                        <th class="p-2 text-right font-medium">Unidades</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($reparto as $fila)
                                        <tr class="border-t border-gray-200 dark:border-gray-700">
                                            <td class="p-2">{{ $fila->sucursal?->nombre ?? 'Sin sucursal' }}</td>
                                            <td
                                                class="p-2 text-right {{ (int) $fila->cantidad < 0 ? 'text-red-600 font-semibold' : '' }}">
                                                {{ (int) $fila->cantidad }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="p-3 text-center text-gray-400">
                                                Este artículo no tiene stock en ninguna sucursal.
                                            </td>
                                        </tr>
                                    @endforelse
                                    <tr class="border-t border-gray-200 dark:border-gray-700 font-semibold">
                                        <td class="p-2">Total</td>
                                        <td class="p-2 text-right">{{ (int) $repuesto->cantidad }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4">
                        <x-label>Desde (origen)</x-label>
                        <select wire:model="sucursal1_id"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                                   focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                            <option value="">Seleccione una sucursal...</option>
                            {{-- Solo las que tienen unidades: es el "conjunto del que
                                 se puede sacar", igual que el modal de productos. --}}
                            @foreach ($origenes as $fila)
                                <option value="{{ $fila->sucursal_id }}">
                                    {{ $fila->sucursal?->nombre ?? 'Sin sucursal' }} ({{ (int) $fila->cantidad }})
                                </option>
                            @endforeach
                        </select>
                        @if ($origenes->isEmpty())
                            <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                Ninguna sucursal tiene unidades de este artículo, así que no hay nada que transferir.
                            </p>
                        @endif
                        <x-input-error for="sucursal1_id"></x-input-error>
                    </div>

                    <div class="mt-4">
                        <x-label>Hacia (destino)</x-label>
                        <select wire:model="sucursal2_id"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                                   focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                            <option value="">Seleccione una sucursal...</option>
                            @foreach ($sucursales as $sucursal)
                                <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                            @endforeach
                        </select>
                        <x-input-error for="sucursal2_id"></x-input-error>
                    </div>

                    <div class="mt-4">
                        <x-label>Unidades a transferir</x-label>
                        <x-input type="number" min="1" step="1" class="w-full" wire:model="cantidad"
                            onfocus="this.select()" autocomplete="off" />
                        <x-input-error for="cantidad"></x-input-error>
                        {{-- La clave 'detalles' la pone StockRepuestoService::retirar()
                             al no haber stock. Sin este error el aviso se tragaría en
                             silencio y el usuario vería el modal sin explicación. --}}
                        <x-input-error for="detalles"></x-input-error>
                    </div>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                @if ($repuesto && $origenes->isNotEmpty())
                    <x-primary-button class="ml-2" wire:click="store()" wire:loading.attr="disabled"
                        wire:target="store">
                        Transferir
                    </x-primary-button>
                @endif
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
