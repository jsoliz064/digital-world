<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                <p class="text-center">
                    Detalle de la Venta Nro.{{ $venta->id }}
                </p>
            </x-slot>

            <x-slot name="content">
                <hr>

                <div>
                    <x-label>Fecha de Venta:</x-label>
                    <x-input type="text" value="{{ $venta->created_at }}" class="w-full" disabled />
                </div>

                <div>
                    <x-label>Cliente:</x-label>
                    <x-input type="text" value="{{ $venta->nombreCliente() ?? 'Sin cliente' }}" class="w-full" disabled />
                </div>

                <div>
                    <x-label>Vendido por:</x-label>
                    <x-input type="text" value="{{ $venta->user->name ?? 'Sin usuario' }}" class="w-full" disabled />
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <x-label>Subtotal ($):</x-label>
                        <x-input type="text" value="{{ number_format($venta->subtotal, 2) }}" class="w-full"
                            disabled />
                    </div>

                    <div>
                        <x-label>Descuento ($):</x-label>
                        <x-input type="text" value="{{ number_format($venta->descuento, 2) }}" class="w-full"
                            disabled />
                    </div>

                    <div>
                        <x-label>Total ($):</x-label>
                        <x-input type="text" value="{{ number_format($venta->total, 2) }}" class="w-full" disabled />
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-label>Tipo de cambio:</x-label>
                        <x-input type="text" value="{{ $venta->tipo_cambio }}" class="w-full" disabled />
                    </div>

                    <div>
                        <x-label>Total en Bs:</x-label>
                        <x-input type="text" value="{{ number_format($venta->total_bs, 2) }}" class="w-full"
                            disabled />
                    </div>
                </div>


                <div class="mt-4 text-xs">
                    <h2 class="text-xs font-semibold text-gray-800">Detalles de la venta</h2>
                    <div class="overflow-x-auto mt-2">
                        <table class="table-auto w-full bg-white border border-gray-300 shadow-sm text-xs">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-1 py-1 text-left border-b">Producto</th>
                                    <th class="px-1 py-1 text-left border-b">Precio</th>
                                    <th class="px-1 py-1 text-left border-b">Descuento</th>
                                    <th class="px-1 py-1 text-left border-b">Subtotal</th>
                                    <th class="px-1 py-1 text-left border-b">Subtotal Bs</th>
                                    <th class="px-1 py-1 text-left border-b">Garantía</th>
                                    <th class="px-1 py-1 text-left border-b">Expiración</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($venta->detalles as $detalle)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-1 py-1 border-b">
                                            {{ $detalle->producto->descripcion }}</td>
                                        <td class="px-1 py-1 border-b">{{ number_format($detalle->precio, 2) }}</td>
                                        <td class="px-1 py-1 border-b">{{ number_format($detalle->descuento, 2) }}</td>
                                        <td class="px-1 py-1 border-b">{{ number_format($detalle->subtotal, 2) }}</td>
                                        <td class="px-1 py-1 border-b">{{ number_format($detalle->subtotal_bs, 2) }}
                                        </td>
                                        <td class="px-1 py-1 border-b">{{ $detalle->garantia_meses }}</td>
                                        <td class="px-1 py-1 border-b">{{ $detalle->garantia_fecha_exp }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cerrar
                </x-secondary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
