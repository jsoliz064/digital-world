<div>
    @livewire('producto.modals.producto-estado-modal')

    <div class="h-full">

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 p-4 gap-4">

            <div
                class="bg-brand-500 dark:bg-gray-800 shadow-lg rounded-md flex items-center justify-between p-3 border-b-4 border-brand-600 dark:border-gray-600 text-white font-medium group">
                <div
                    class="flex justify-center items-center w-14 h-14 bg-white rounded-full transition-all duration-300 transform group-hover:rotate-12">
                    <svg width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        class="stroke-current text-brand-800 dark:text-gray-800 transform transition-transform duration-500 ease-in-out">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                        </path>
                    </svg>
                </div>
                <div class="text-right">
                    <p class="text-2xl">Bs {{ number_format($ventas_dia, 2) }} / {{ $ventas_dia_cant }}</p>
                    <p>Ventas del Dia</p>
                </div>
            </div>

            <div
                class="bg-brand-500 dark:bg-gray-800 shadow-lg rounded-md flex items-center justify-between p-3 border-b-4 border-brand-600 dark:border-gray-600 text-white font-medium group">
                <div
                    class="flex justify-center items-center w-14 h-14 bg-white rounded-full transition-all duration-300 transform group-hover:rotate-12">
                    <svg width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        class="stroke-current text-brand-800 dark:text-gray-800 transform transition-transform duration-500 ease-in-out">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
                <div class="text-right">
                    <p class="text-2xl">Bs {{ number_format($ventas_mes, 2) }} / {{ $ventas_mes_cant }}</p>
                    <p>Ventas del Mes</p>
                </div>
            </div>

            <div
                class="bg-brand-500 dark:bg-gray-800 shadow-lg rounded-md flex items-center justify-between p-3 border-b-4 border-brand-600 dark:border-gray-600 text-white font-medium group">
                <div
                    class="flex justify-center items-center w-14 h-14 bg-white rounded-full transition-all duration-300 transform group-hover:rotate-12">
                    <svg width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        class="stroke-current text-brand-800 dark:text-gray-800 transform transition-transform duration-500 ease-in-out">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20 12v6a2 2 0 01-2 2H6a2 2 0 01-2-2v-6m16 0H4m16 0l-1.5-9h-11L4 12m5 4h6">
                        </path>
                    </svg>
                </div>
                <div class="text-right">
                    <p class="text-2xl">{{ $productos_inventario }}</p>
                    <p>Productos en Inventario</p>
                </div>
            </div>

            {{-- Morado, el mismo color con que las tablas pintan el estado.
                 Es un subconteo del inventario de al lado, no una cifra aparte. --}}
            <div
                class="bg-purple-500 dark:bg-gray-800 shadow-lg rounded-md flex items-center justify-between p-3 border-b-4 border-purple-600 dark:border-gray-600 text-white font-medium group">
                <div
                    class="flex justify-center items-center w-14 h-14 bg-white rounded-full transition-all duration-300 transform group-hover:rotate-12">
                    <svg width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        class="stroke-current text-purple-800 dark:text-gray-800 transform transition-transform duration-500 ease-in-out">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z">
                        </path>
                    </svg>
                </div>
                <div class="text-right">
                    <p class="text-2xl">{{ $productos_oferta }}</p>
                    <p>Productos en Oferta</p>
                </div>
            </div>

            <div
                class="bg-brand-500 dark:bg-gray-800 shadow-lg rounded-md flex items-center justify-between p-3 border-b-4 border-brand-600 dark:border-gray-600 text-white font-medium group">
                <div
                    class="flex justify-center items-center w-14 h-14 bg-white rounded-full transition-all duration-300 transform group-hover:rotate-12">
                    <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor"
                        class="stroke-current text-brand-800 dark:text-gray-800 transform transition-transform duration-500 ease-in-out">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15.232 5.232a3.75 3.75 0 01-5.304 5.304l-5.46 5.46a2.121 2.121 0 103 3l5.46-5.46a3.75 3.75 0 015.304-5.304l-3-3z" />
                    </svg>
                </div>
                <div class="text-right">
                    <p class="text-2xl">{{ $productos_reparacion }}</p>
                    <p>En reparación</p>
                    <p class="text-xs opacity-80">{{ $productos_reserva }} reservados · {{ $productos_credito }} a crédito</p>
                </div>
            </div>

        </div>
        <!-- ./Statistics Cards -->

        <div class="m-3 mt-5">
            <div class="text-center">
                <h4 class="text-lg font-semibold text-gray-600 dark:text-gray-200">Últimos Productos Vendidos</h4>
            </div>
            @livewire('venta.venta-producto-table')
        </div>

        <div class="m-3 mt-5">
            <div class="text-center">
                <h4 class="text-lg font-semibold text-gray-600 dark:text-gray-200">Productos que no estan en inventario
                </h4>
            </div>
            @livewire('dashboard.producto-dashboard-table')
        </div>

    </div>
</div>
