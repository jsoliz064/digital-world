<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal" maxWidth="4xl">
            <x-slot name="title">
                <p class="text-center">Seleccionar Repuestos o Accesorios</p>
            </x-slot>

            <x-slot name="content">
                <hr>

                {{-- Dos columnas cuando solo quedan Buscar y Tipo: a cinco se
                     quedaban estrechos y con tres huecos. Clases literales en las
                     dos ramas (no hay safelist en tailwind.config.js). --}}
                <div class="{{ $soloAccesorios ? 'mt-4 grid grid-cols-1 md:grid-cols-2 gap-3' : 'mt-4 grid grid-cols-1 md:grid-cols-5 gap-3' }}">
                    <div>
                        <x-label>Buscar</x-label>
                        {{-- Un accesorio no tiene fabricante: prometerlo en el
                             placeholder era pedir un dato que no existe. --}}
                        <x-input type="text" class="w-full"
                            placeholder="{{ $soloAccesorios ? 'Nombre...' : 'Nombre o fabricante...' }}"
                            wire:model.live.debounce.500ms="search" autocomplete="off" />
                    </div>

                    <div>
                        <x-label>Tipo</x-label>
                        <select wire:model.live="filtroTipo"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm sm:text-sm">
                            <option value="">Todos</option>
                            @foreach ($tipos as $valorTipo => $etiquetaTipo)
                                <option value="{{ $valorTipo }}">{{ $etiquetaTipo }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Categoría, modelo y color describen una pieza de reparación.
                         Con Tipo = Accesorio son combinaciones imposibles y daban
                         cero resultados sin explicar por qué, así que desaparecen.
                         Con "Todos" o "Repuesto" se quedan: ahí la lista mezcla los
                         dos tipos y el badge de Tipo distingue cada fila. --}}
                    @unless ($soloAccesorios)
                        <div>
                            <x-label>Categoria</x-label>
                            <select wire:model.live="filtroCategoria"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm sm:text-sm">
                                <option value="">Todas</option>
                                @foreach ($categorias as $c)
                                    <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-label>Modelo</x-label>
                            <select wire:model.live="filtroModelo"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm sm:text-sm">
                                <option value="">Todos</option>
                                @foreach ($modelos as $m)
                                    <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-label>Color</x-label>
                            <select wire:model.live="filtroColor"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm sm:text-sm">
                                <option value="">Todos</option>
                                <option value="{{ $sinColor }}">Sin color</option>
                                @foreach ($colores as $nombreColor => $hexColor)
                                    <option value="{{ $nombreColor }}">{{ $nombreColor }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endunless
                </div>

                <div class="mt-3 flex items-center justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-300">
                        <span class="font-semibold">{{ count($seleccionados) }}</span> seleccionado(s)
                        @if ($repuestos)
                            &middot; {{ $repuestos->total() }} resultado(s)
                        @endif
                    </span>
                    <div class="space-x-3">
                        @if (count($seleccionados))
                            <button type="button" wire:click="limpiarSeleccion"
                                class="text-red-600 hover:underline">Limpiar seleccion</button>
                        @endif
                        <button type="button" wire:click="limpiarFiltros"
                            class="text-brand-600 hover:underline">Limpiar filtros</button>
                    </div>
                </div>

                <div
                    class="overflow-x-auto overflow-y-auto max-h-80 mt-3 border border-gray-200 dark:border-gray-700 shadow-sm rounded-lg">
                    <table class="min-w-full text-xs relative">
                        <thead class="sticky top-0 bg-gray-100 dark:bg-gray-700 shadow-sm z-10">
                            <tr>
                                <th class="p-2 border-b w-10"></th>
                                <th class="p-2 border-b text-left">Articulo</th>
                                <th class="p-2 border-b text-center">Tipo</th>
                                @unless ($soloAccesorios)
                                    <th class="p-2 border-b text-left">Fabricante</th>
                                    <th class="p-2 border-b text-left">Categoria</th>
                                    <th class="p-2 border-b text-left">Modelo</th>
                                @endunless
                                <th class="p-2 border-b text-right">Precio</th>
                                <th class="p-2 border-b text-center">
                                    {{ $sucursalId ? 'Stock en ' . $nombreSucursal : 'Stock total' }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($repuestos as $r)
                                @php
                                    // El stock que decide: el de la sucursal del documento si
                                    // hay una, el total si no.
                                    $stock = $sucursalId ? $r->stockEn($sucursalId) : (int) $r->cantidad;
                                    $bloqueado = $soloConStock && $stock <= 0;
                                    $marcado = in_array((string) $r->id, array_map('strval', $seleccionados), true);

                                    // Clases LITERALES en cada rama: Tailwind no tiene safelist y
                                    // una clase compuesta no se genera nunca.
                                    if ($bloqueado) {
                                        $claseFila = 'opacity-50 cursor-not-allowed';
                                    } elseif ($marcado) {
                                        $claseFila = 'bg-brand-50 dark:bg-brand-900 cursor-pointer';
                                    } else {
                                        $claseFila = 'hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer';
                                    }
                                @endphp
                                {{-- Toda la fila selecciona, no solo la casilla de 14px. El
                                     wire:click vive AQUI y el checkbox no lleva wire:model: con
                                     los dos enlaces puestos, un clic sobre la casilla disparaba
                                     ambos y el cambio se anulaba solo. --}}
                                <tr wire:key="rep-{{ $r->id }}" class="{{ $claseFila }}"
                                    @if (!$bloqueado) wire:click="alternar({{ $r->id }})" @endif
                                    title="{{ $bloqueado ? 'Sin stock en ' . $nombreSucursal : '' }}">
                                    <td class="p-2 border text-center">
                                        {{-- Deshabilitada y NO escondida: filtrar la fila haria que
                                             buscar el articulo por su nombre devolviera "sin
                                             resultados" sin decir por que. El title explica.

                                             pointer-events-none para que el clic lo reciba el <tr>
                                             y no haya dos caminos que coordinar. --}}
                                        <x-checkbox class="pointer-events-none" :checked="$marcado"
                                            :disabled="$bloqueado" />
                                    </td>
                                    <td class="p-2 border">{!! $r->getNombreConColor() !!}</td>
                                    <td class="p-2 border text-center">{!! $r->getBadgeTipo() !!}</td>
                                    @unless ($soloAccesorios)
                                        <td class="p-2 border">{{ $r->fabricante ?: '-' }}</td>
                                        <td class="p-2 border">{{ $r->categoria?->nombre ?? '-' }}</td>
                                        <td class="p-2 border">{{ $r->modelo?->nombre ?? '-' }}</td>
                                    @endunless
                                    <td class="p-2 border text-right">{{ number_format($r->precio, 2) }}</td>
                                    <td class="p-2 border text-center">
                                        @if ($stock <= 0)
                                            <span class="text-red-600 font-bold">0</span>
                                        @elseif ($stock <= $umbralBajoStock)
                                            <span class="text-amber-600 font-semibold">{{ $stock }}</span>
                                        @else
                                            <span class="text-green-600">{{ $stock }}</span>
                                        @endif
                                        @if ($sucursalId)
                                            <span class="block text-xs text-gray-400">de {{ (int) $r->cantidad }} en total</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    {{-- El colspan sigue al numero de columnas: con los tres
                                         campos de pieza escondidos son cinco, y clavado a 8
                                         la fila de "sin resultados" se desalineaba. --}}
                                    <td colspan="{{ $soloAccesorios ? 5 : 8 }}" class="p-4 text-center text-gray-400">
                                        No se encontraron articulos con esos filtros.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($repuestos && $repuestos->lastPage() > 1)
                    <div class="mt-3 flex items-center justify-between text-sm">
                        <button type="button" wire:click="irAPagina({{ $repuestos->currentPage() - 1 }})"
                            @disabled($repuestos->onFirstPage())
                            class="px-3 py-1 border rounded-md disabled:opacity-40 dark:border-gray-600">
                            Anterior
                        </button>
                        <span class="text-gray-600 dark:text-gray-300">
                            Pagina {{ $repuestos->currentPage() }} de {{ $repuestos->lastPage() }}
                        </span>
                        <button type="button" wire:click="irAPagina({{ $repuestos->currentPage() + 1 }})"
                            @disabled(!$repuestos->hasMorePages())
                            class="px-3 py-1 border rounded-md disabled:opacity-40 dark:border-gray-600">
                            Siguiente
                        </button>
                    </div>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                <x-primary-button class="ml-2" wire:click="agregarSeleccionados" wire:loading.attr="disabled"
                    :disabled="count($seleccionados) === 0">
                    Agregar {{ count($seleccionados) }} seleccionado(s)
                </x-primary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
