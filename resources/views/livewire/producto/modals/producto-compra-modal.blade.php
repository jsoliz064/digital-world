<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal" maxWidth="2xl">
            <x-slot name="title">
                {{ $cambiando ? 'Cambiar de compra' : 'Compra del equipo' }}
            </x-slot>

            <x-slot name="content">
                <hr>
                @if (!$producto)
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">El equipo ya no existe.</p>
                @else
                    <div class="mt-4">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ $producto->modelo?->nombre }} {{ $producto->almacenamiento }} · IMEI {{ $producto->imei }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Estado: {{ \App\Enums\ProductoEstado::labelDe($producto->estado) }}</p>
                    </div>

                    {{-- La compra actual --}}
                    @if ($compra)
                        <div class="mt-4 rounded-md border border-gray-200 dark:border-gray-600 p-3 text-sm text-gray-700 dark:text-gray-300">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-semibold text-gray-900 dark:text-gray-100">
                                    Compra #{{ $compra->id }} · {{ $compra->proveedor?->nombre ?? 'Sin proveedor' }}
                                </p>
                                {!! $compra->estado()->badge() !!}
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1">
                                <p><span class="text-gray-500">Fecha:</span> {{ $compra->fecha?->format('d/m/Y') }}</p>
                                <p><span class="text-gray-500">Sucursal:</span> {{ $producto->compraDetalle?->sucursal?->nombre ?? '—' }}</p>
                                <p><span class="text-gray-500">Costo del equipo:</span> Bs {{ number_format((float) $producto->compraDetalle?->costo, 2) }}</p>
                                <p><span class="text-gray-500">Total de la compra:</span> Bs {{ number_format((float) $compra->total, 2) }}</p>
                                <p><span class="text-gray-500">Saldo:</span> Bs {{ number_format((float) $compra->saldo, 2) }}</p>
                                <p>{!! $compra->badgePago() !!}</p>
                            </div>
                            @can('compra.detalle')
                                <a href="{{ route('compras.detalle', $compra->id) }}"
                                    class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-brand-700 hover:underline dark:text-brand-400">
                                    <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i> Abrir compra
                                </a>
                            @endcan
                        </div>
                    @endif

                    @if ($motivo)
                        <p class="mt-4 rounded-md bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">{{ $motivo }}</p>
                    @elseif ($compra && !$cambiando)
                        @can('compra.edit')
                            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                                ¿Se cargó en la compra equivocada? Pásalo a la correcta sin borrar el equipo.
                            </p>
                        @endcan
                    @endif

                    {{-- Elegir la compra nueva --}}
                    @if ($cambiando && !$motivo && $compra)
                        <div class="mt-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_9rem]">
                                <div>
                                    <x-label for="proveedorId" value="Proveedor" />
                                    <select wire:model.live="proveedorId" id="proveedorId"
                                        class="mt-1 block w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                        <option value="">Todos (últimas compras)</option>
                                        @foreach ($proveedores as $proveedor)
                                            <option value="{{ $proveedor->id }}">{{ $proveedor->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <x-label for="buscarNumero" value="N.º de compra" />
                                    <x-input wire:model.live.debounce.400ms="buscarNumero" id="buscarNumero" type="text" inputmode="numeric"
                                        class="mt-1 block w-full h-10" placeholder="#" />
                                </div>
                            </div>

                            <div class="mt-3 max-h-72 space-y-2 overflow-y-auto">
                                @forelse ($candidatas as $c)
                                    <button type="button" wire:click="elegir({{ $c->id }})" wire:key="compra-{{ $c->id }}"
                                        @class([
                                            'w-full rounded-md border p-3 text-left text-sm transition',
                                            'border-brand-500 bg-brand-50 ring-1 ring-brand-500 dark:bg-brand-900/30' => $destinoId === $c->id,
                                            'border-gray-200 hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700' => $destinoId !== $c->id,
                                        ])>
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <span class="font-semibold text-gray-900 dark:text-gray-100">#{{ $c->id }} · {{ $c->proveedor?->nombre ?? 'Sin proveedor' }}</span>
                                            {!! $c->estado()->badge() !!}
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $c->fecha?->format('d/m/Y') }} · {{ $c->equipos }} {{ $c->equipos === 1 ? 'equipo' : 'equipos' }} · Bs {{ number_format((float) $c->total, 2) }}
                                        </p>
                                    </button>
                                @empty
                                    <p class="text-sm text-gray-500 dark:text-gray-400">No hay otras compras con ese filtro.</p>
                                @endforelse
                            </div>

                            {{-- Lo que va a pasar, antes de confirmar --}}
                            @if ($destino)
                                @php
                                    $costo = (float) $producto->compraDetalle?->costo;
                                    $deBorrador = $compra->esBorrador();
                                    $aBorrador = $destino->esBorrador();
                                @endphp
                                <div class="mt-4 rounded-md border border-brand-200 bg-brand-50 p-3 text-sm text-gray-800 dark:border-brand-800 dark:bg-brand-900/20 dark:text-gray-200">
                                    @if ($motivo_destino)
                                        <p class="text-amber-800 dark:text-amber-200">{{ $motivo_destino }}</p>
                                    @else
                                        <p>
                                            El equipo pasa de la compra <b>#{{ $compra->id }}</b> ({{ $compra->proveedor?->nombre }})
                                            a la <b>#{{ $destino->id }}</b> ({{ $destino->proveedor?->nombre }}).
                                        </p>
                                        <ul class="mt-2 list-disc space-y-1 pl-5">
                                            <li>#{{ $compra->id }}: Bs {{ number_format((float) $compra->total, 2) }} → Bs {{ number_format((float) $compra->total - $costo, 2) }}</li>
                                            <li>#{{ $destino->id }}: Bs {{ number_format((float) $destino->total, 2) }} → Bs {{ number_format((float) $destino->total + $costo, 2) }}</li>
                                            @if ($deBorrador && !$aBorrador)
                                                <li>La #{{ $destino->id }} ya está finalizada: el equipo pasa a
                                                    {{ \App\Enums\ProductoEstado::labelDe($producto->compraDetalle?->estado_destino ?? 'Inventario') }}.</li>
                                            @elseif (!$deBorrador && $aBorrador)
                                                <li>La #{{ $destino->id }} está en borrador: el equipo vuelve a «En compra» y no se vende hasta finalizarla.</li>
                                            @endif
                                            @if ($origen_queda_vacia ?? false)
                                                <li class="font-semibold">La compra #{{ $compra->id }} queda vacía y se elimina.</li>
                                            @endif
                                        </ul>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif

                    <x-input-error for="compra" class="mt-3" />
                @endif
            </x-slot>

            <x-slot name="footer">
                <div class="flex w-full flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                    <x-secondary-button wire:click="closeModal" wire:loading.attr="disabled">Cerrar</x-secondary-button>

                    @if ($producto && $compra && !$motivo)
                        @can('compra.edit')
                            @if (!$cambiando)
                                <x-button wire:click="empezarCambio">Cambiar de compra</x-button>
                            @elseif ($destino && !($motivo_destino ?? null))
                                <x-button wire:click="mover" wire:loading.attr="disabled">
                                    Mover a la compra #{{ $destino->id }}
                                </x-button>
                            @endif
                        @endcan
                    @endif
                </div>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
