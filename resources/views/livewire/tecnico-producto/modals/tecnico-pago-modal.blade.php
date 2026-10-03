<div>
    @if ($openModal)
        <x-dialog-modal wire:model.live="openModal" maxWidth="2xl">
            <x-slot name="title">
                <p class="text-center text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Realizar Pago a: <span class="text-brand-600">{{ $tecnico->nombre ?? '' }}</span>
                </p>
            </x-slot>

            <x-slot name="content">
                <hr class="mb-4">

                @if ($reparaciones && $reparaciones->isNotEmpty())
                    <div class="mt-4 text-sm">
                        <h2 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-2">Reparaciones Pendientes
                            de Pago</h2>
                        <div class="overflow-y-auto max-h-80 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <table class="table-auto w-full bg-white dark:bg-gray-800 shadow-sm text-sm">
                                <thead class="bg-gray-100 dark:bg-gray-700 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-2 text-left border-b dark:border-gray-600">ID</th>
                                        <th class="px-4 py-2 text-left border-b dark:border-gray-600">Producto</th>
                                        <th class="px-4 py-2 text-left border-b dark:border-gray-600">Estado</th>
                                        <th class="px-4 py-2 text-right border-b dark:border-gray-600">Costo (Bs)</th>
                                        <th class="px-4 py-2 text-center border-b dark:border-gray-600">Quitar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($reparaciones as $reparacion)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700"
                                            wire:key="reparacion-{{ $reparacion->id }}">
                                            <td class="px-4 py-2 border-b dark:border-gray-600">
                                                {{ $reparacion->id }}</td>
                                            <td class="px-4 py-2 border-b dark:border-gray-600">
                                                {{ $reparacion->producto->descripcion }}</td>
                                            <td class="px-4 py-2 border-b dark:border-gray-600">
                                                <span
                                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $reparacion->estado == 'Terminado' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                    {{ $reparacion->estado }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 border-b dark:border-gray-600 text-right">
                                                {{ number_format($reparacion->costo, 2, ',', '.') }}</td>
                                            <td class="px-4 py-2 border-b dark:border-gray-600 text-center">
                                                <button wire:click="quitarReparacion({{ $reparacion->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="quitarReparacion({{ $reparacion->id }})"
                                                    class="text-red-500 hover:text-red-700 transition duration-150 ease-in-out"
                                                    title="Quitar de la lista de pago">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                                        fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                        stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg flex justify-between items-center">
                        <span class="text-lg font-semibold text-gray-800 dark:text-gray-200">TOTAL A PAGAR:</span>
                        <span class="text-2xl font-bold text-brand-600 dark:text-brand-400">
                            Bs {{ number_format($total, 2, ',', '.') }}
                        </span>
                    </div>
                @else
                    {{-- Mensaje cuando no hay reparaciones --}}
                    <div class="text-center py-10">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" aria-hidden="true">
                            <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round"
                                stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-200">Todo Pagado</h3>
                        <p class="mt-1 text-sm text-gray-500">No hay reparaciones pendientes de pago para este técnico.
                        </p>
                    </div>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cerrar
                </x-secondary-button>

                @if ($reparaciones->count() > 0)
                    <x-primary-button class="ml-2" wire:click="marcarComoPagado" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="marcarComoPagado">Marcar Como Pagados</span>
                        <span wire:loading wire:target="marcarComoPagado">Procesando...</span>
                    </x-primary-button>
                @endif
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
