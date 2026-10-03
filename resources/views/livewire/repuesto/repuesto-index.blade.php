<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">
        {{ $this->tipoEnum()->plural() }}
    </h2>

    {{-- Dos columnas desde el movil: son dos cifras cortas y apiladas
         desperdiciaban toda la primera pantalla. El padding y el tipo de letra
         crecen con el ancho. --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-6 mb-6" wire:loading.class="opacity-50 animate-pulse"
        wire:target="applyFilters">

        <div
            class="bg-green-100 dark:bg-green-800 p-4 sm:p-6 rounded-2xl shadow-lg border border-green-200 dark:border-green-700">
            <h3 class="text-xs sm:text-sm font-medium text-green-700 dark:text-green-300">Total de Articulos</h3>
            <p class="mt-1 text-xl sm:text-2xl font-semibold tracking-tight text-green-900 dark:text-green-100">
                {{ $totalRepuestos }}
            </p>
        </div>

        <div
            class="bg-red-100 dark:bg-red-800 p-4 sm:p-6 rounded-2xl shadow-lg border border-red-200 dark:border-red-700">
            <h3 class="text-xs sm:text-sm font-medium text-red-700 dark:text-red-300">Articulos con Bajo Stock</h3>
            <p class="mt-1 text-xl sm:text-2xl font-semibold tracking-tight text-red-900 dark:text-red-100">
                {{ $totalBajoStock }}
            </p>
        </div>

    </div>

    {{-- Mismo componente y mismo titulo que la pantalla de productos, para que
         las dos se lean igual. Abierto en escritorio y plegado en movil: ahi
         empujaria la tabla fuera de la primera pantalla.

         Sin @can propio: la pantalla ya esta cerrada por ruta
         (can:accesorio.index / can:repuesto.index) y esto no dice nada que la
         columna "Por sucursal" de la tabla no diga ya. --}}
    <x-collapse-card title="Resumen por Sucursales" :open-on-desktop="true">
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 sm:gap-4"
            wire:loading.class="opacity-50 animate-pulse" wire:target="applyFilters">

            @foreach ($sucursalesResumen as $sucursal)
                <div
                    class="p-4 bg-gray-50 dark:bg-gray-900 shadow rounded-lg border border-gray-200 dark:border-gray-700">
                    <h3
                        class="font-semibold text-sm sm:text-base text-gray-700 dark:text-gray-200 mb-2 truncate">
                        {{ $sucursal['nombre'] }}
                    </h3>

                    <ul class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                        <li class="flex justify-between">
                            <span>Unidades</span>
                            {{-- Los negativos en rojo, igual que
                                 Repuesto::desgloseStock(): hay stock negativo real
                                 del backfill y esconderlo seria esconder justo lo
                                 que hay que corregir. Clases literales en las dos
                                 ramas: no hay safelist en tailwind.config.js. --}}
                            <span
                                class="{{ $sucursal['unidades'] < 0 ? 'font-bold text-red-600 dark:text-red-400' : 'font-bold' }}">
                                {{ $sucursal['unidades'] }}
                            </span>
                        </li>

                        <li class="flex justify-between">
                            <span>Artículos</span>
                            <span class="font-bold">{{ $sucursal['articulos'] }}</span>
                        </li>
                    </ul>
                </div>
            @endforeach

        </div>
    </x-collapse-card>

    {{-- Plegable: en movil los tres selects multiples empujaban la tabla fuera
         de la pantalla. Arranca abierto en escritorio y cerrado en movil. --}}
    <x-collapse-card title="Filtros" :open-on-desktop="true">
        <x-slot name="actions">
            {{-- Solo cuenta los filtros que ESTA pantalla muestra: en accesorios,
                 modelos y categorias no se pintan y siempre llegan vacios. --}}
            @if (($this->muestraCamposDeRepuesto() && (!empty($selectedModelos) || !empty($selectedCategorias))) || (bool) $selectedMinStock)
                <button wire:click="resetFilters"
                    class="px-3 py-1 text-sm font-medium text-white bg-red-600 rounded-lg shadow-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Limpiar Filtros
                </button>
            @endif
        </x-slot>

        {{-- En accesorios solo queda "Control de Stock", y a tres columnas el
             select se quedaba flotando a un tercio de ancho. Las clases van
             literales en las dos ramas: no hay safelist en tailwind.config.js. --}}
        <div class="{{ $this->muestraCamposDeRepuesto() ? 'grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 gap-4 p-2' : 'grid grid-cols-1 gap-4 p-2' }}">
            {{-- Modelo y categoria describen el telefono al que encaja una pieza:
                 un accesorio los tiene en NULL a proposito (ver
                 RepuestoAccesorioTrait, que es lo mismo que esconden crear y
                 editar). Y no era solo ruido: un whereIn sobre NULL no acierta
                 nunca, asi que elegir un modelo aqui vaciaba la lista y los dos
                 contadores sin decir por que. --}}
            @if ($this->muestraCamposDeRepuesto())
                <div>
                    <label for="Modelos" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Modelos</label>
                    <select wire:model.live="selectedModelos" id="modelos" multiple
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white h-30">
                        @foreach ($modelos as $modelo)
                            <option value="{{ $modelo->id }}">{{ $modelo->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="categorias" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Categorias</label>
                    <select wire:model.live="selectedCategorias" id="categorias" multiple
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white h-30">
                        @foreach ($categorias as $categoria)
                            <option value="{{ $categoria->id }}">{{ $categoria->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label for="stock" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Control de
                    Stock</label>
                <select wire:model.live="selectedMinStock" id="stock"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="0">Todos</option>
                    <option value="1">Bajo Stock</option>
                </select>
            </div>
        </div>
    </x-collapse-card>

    {{-- Aqui habia pestanas de tipo sobre una sola tabla, y el comentario
         explicaba que montar una tabla por pestana hacia colisionar el nombre
         de tabla del paquete ('table' por defecto en todas), la query string y
         el evento toggle-row-content de Alpine.
         Eso sigue siendo cierto para tablas que conviven en el MISMO DOM; estas
         son dos paginas distintas (/inventario/repuestos y
         /inventario/accesorios), asi que no coinciden nunca y la colision no
         existe. El tipo llega por mount(), no por evento: ver RepuestoIndex. --}}

    <div class="flex flex-wrap gap-2 mb-2">
        @can($this->permiso() . '.create')
            <x-primary-button wire:click="openRepuestoCreateModal()">
                Crear Articulo
            </x-primary-button>
        @endcan
        @can('repuesto.tipo-cambio-masivo')
            <x-secondary-button wire:click="openRepuestoTipoCambioMasivoModal()"
                wire:loading.attr="disabled" wire:target="openRepuestoTipoCambioMasivoModal">
                Actualizar Tipo de Cambio
            </x-secondary-button>
        @endcan
    </div>
    <div class="mt-4">
        {{-- El tipo por parametro y no por el evento filtersUpdated, que en el
             primer render no llega a nadie. La key ata la instancia al tipo. --}}
        @livewire('repuesto.repuesto-table', ['tipo' => $tipo], key('tabla-' . $tipo))
    </div>
    {{-- El modal de crear nace ya con el tipo de la pantalla: crear un
         "Repuesto" desde /inventario/accesorios producia un articulo que
         desaparecia de la lista al guardar, sin explicacion. --}}
    @livewire('repuesto.modals.repuesto-create-modal', ['tipo' => $tipo])
    @livewire('repuesto.modals.repuesto-edit-modal')
    @livewire('repuesto.modals.repuesto-destroy-modal')
    @livewire('repuesto.modals.repuesto-tipo-cambio-masivo-modal')
    @livewire('repuesto.modals.repuesto-transferencia-modal')
</div>
