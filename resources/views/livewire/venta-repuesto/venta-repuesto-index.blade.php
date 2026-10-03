<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">Ventas de Repuestos y Accesorios</h2>

    {{-- Dos columnas desde el movil. Son cinco tarjetas, asi que la ultima
         (Ganancia) ocupa el ancho completo en la ultima fila en vez de dejar un
         hueco; en escritorio vuelve a una sola columna de las cinco. --}}
    @can('venta.reporte')
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-6 mb-6"
            wire:loading.class="opacity-50 animate-pulse" wire:target="applyFilters">

            <div
                class="bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h3 class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">Total Venta (USD)</h3>
                <p class="mt-1 text-xl sm:text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                    $. {{ number_format($totalVenta, 2) }}
                </p>
            </div>

            <div
                class="bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h3 class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">Ventas Realizadas</h3>
                <p class="mt-1 text-xl sm:text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                    {{ $cantidadVentas }}
                </p>
            </div>

            <!-- Unidades vendidas, separadas por tipo de articulo -->
            <div
                class="bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h3 class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">Repuestos Vendidos</h3>
                <p class="mt-1 text-xl sm:text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                    {{ $cantidadRepuestos }}
                </p>
            </div>

            <div
                class="bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700">
                <h3 class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">Accesorios Vendidos</h3>
                <p class="mt-1 text-xl sm:text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                    {{ $cantidadAccesorios }}
                </p>
            </div>

            <div
                class="col-span-2 lg:col-span-1 bg-green-100 dark:bg-green-800 p-4 sm:p-6 rounded-2xl shadow-lg border border-green-200 dark:border-green-700">
                <h3 class="text-xs sm:text-sm font-medium text-green-700 dark:text-green-300">Ganancia (USD)</h3>
                <p class="mt-1 text-xl sm:text-2xl font-semibold tracking-tight text-green-900 dark:text-green-100">
                    $ {{ number_format($totalGanancia, 2) }}
                </p>
            </div>
        </div>
    @endcan

    {{-- Plegable: los dos <select multiple> de h-32 empujaban la tabla fuera de
         la pantalla en movil. Abierto en escritorio, cerrado en movil. --}}
    <x-collapse-card title="Filtros" :open-on-desktop="true">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 p-2">
            <!-- Filtro Desde -->
            <div>
                <label for="fechaDesde" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Desde</label>
                <input wire:model.live="fechaDesde" type="date" id="fechaDesde"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
            <!-- Filtro Hasta -->
            <div>
                <label for="fechaHasta" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Hasta</label>
                <input wire:model.live="fechaHasta" type="date" id="fechaHasta"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
            <!-- Filtro Usuarios -->
            <div>
                <label for="users"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">Vendedores</label>
                <select wire:model.live="selectedUsers" id="users" multiple
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white h-32">
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <!-- Filtro Sucursales -->
            <div>
                <label for="sucursales"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sucursales</label>
                <select wire:model.live="selectedSucursales" id="sucursales" multiple
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white h-32">
                    @foreach ($sucursales as $sucursal)
                        <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-collapse-card>

    @can('venta.repuesto.create')
        <x-primary-button wire:click="ventaRepuestoCreate()">
            Registrar Venta de Repuestos
        </x-primary-button>
    @endcan
    <div class="mt-4">
        @livewire('venta-repuesto.venta-repuesto-table')
    </div>
    
    @livewire('venta-repuesto.modals.venta-repuesto-destroy-modal')
</div>
