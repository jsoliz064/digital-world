{{--
    Los repuestos que se cobraron junto a la venta de un telefono.

    Lo comparten las tres pantallas de lectura -- detalle de venta, modal de
    estado de un producto ya vendido y modal del historial -- por la misma
    razon que x-reparacion-detalle: tres copias del mismo bloque acabarian
    divergiendo en cuanto una de ellas se retoque.

    Solo muestra: no recalcula nada. Los importes se leen tal cual se
    guardaron.
--}}
@props(['ventasRepuestos', 'titulo' => 'Repuestos cobrados con esta venta'])

@php
    // Solo las lineas que nacen de una reparacion. Una venta enlazada puede
    // llevar ademas repuestos normales agregados despues, y esos no pertenecen
    // a este bloque.
    $lineas = collect($ventasRepuestos)
        ->flatMap(fn($ventaRepuesto) => $ventaRepuesto->detalles)
        ->filter(fn($detalle) => $detalle->producto_reparacion_repuesto_id !== null);

    $total = round((float) $lineas->sum('subtotal'), 2);
@endphp

@if ($lineas->isNotEmpty())
    <div class="rounded-lg border border-green-200 dark:border-green-700 bg-green-50 dark:bg-green-900 p-4">
        <h3 class="text-sm font-semibold text-green-900 dark:text-green-100">{{ $titulo }}</h3>
        <p class="mt-1 text-xs text-green-700 dark:text-green-300">
            Se montaron en reparaciones del equipo y se cobraron aparte. No entran en el total del teléfono
            y no descontaron stock: la pieza salió del almacén al montarla.
        </p>

        <div class="overflow-x-auto mt-3">
            <table class="min-w-full text-xs">
                <thead class="text-green-900 dark:text-green-100">
                    <tr>
                        <th class="p-2 text-left">Repuesto</th>
                        <th class="p-2 text-center">Reparación</th>
                        <th class="p-2 text-center">Cantidad</th>
                        <th class="p-2 text-right">Precio ($)</th>
                        <th class="p-2 text-right">Subtotal ($)</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200">
                    @foreach ($lineas as $linea)
                        <tr wire:key="cobrado-{{ $linea->id }}">
                            <td class="p-2 border border-green-200 dark:border-green-700">
                                {{ $linea->repuesto?->nombre ?? 'Repuesto eliminado' }}
                            </td>
                            <td class="p-2 border border-green-200 dark:border-green-700 text-center">
                                @if ($linea->reparacionRepuesto)
                                    #{{ $linea->reparacionRepuesto->producto_reparacion_id }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="p-2 border border-green-200 dark:border-green-700 text-center">
                                {{ $linea->cantidad }}
                            </td>
                            <td class="p-2 border border-green-200 dark:border-green-700 text-right">
                                {{ number_format((float) $linea->precio, 2) }}
                            </td>
                            <td class="p-2 border border-green-200 dark:border-green-700 text-right">
                                {{ number_format((float) $linea->subtotal, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="text-green-900 dark:text-green-100 font-semibold">
                        <td class="p-2 text-right" colspan="4">Total cobrado en repuestos</td>
                        <td class="p-2 text-right">$ {{ number_format($total, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif
