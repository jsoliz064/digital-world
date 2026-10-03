<div>
    <div class="max-w-8xl mx-auto">

        <div class="mb-4">
            <a href="{{ url()->previous() }}"
                class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 17l-5-5m0 0l5-5m-5 5h12" />
                </svg>
                Volver
            </a>
        </div>

        <div class="text-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Detalle de Venta</h1>
            <p class="text-lg text-gray-500 dark:text-gray-400">Venta Nro. {{ $venta->id }}</p>
        </div>

        <!-- Sección de Detalles Generales -->
        <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 mb-6">
            <!-- Detalles Generales -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Cliente</h3>
                    <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $venta->nombreCliente() ?? 'Sin cliente' }}</p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Vendido por</h3>
                    <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $venta->user->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Fecha de Venta</h3>
                    <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ \Carbon\Carbon::parse($venta->created_at)->format('d/m/Y H:i A') }}</p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Sucursal</h3>
                    <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $venta->sucursal->nombre }}
                    </p>
                </div>
            </div>

            <!-- Separador y Detalles Financieros -->
            <div class="border-t border-gray-200 dark:border-gray-700 mt-6 pt-6 grid grid-cols-2 md:grid-cols-3 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Productos</h3>
                    <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $venta->detalles->count() }}
                    </p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Subtotal ($)</h3>
                    <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ number_format($venta->subtotal, 2) }}</p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Descuento ($)</h3>
                    <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ number_format($venta->descuento, 2) }}</p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Tipo de Cambio</h3>
                    <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ number_format($venta->tipo_cambio, 2) }}</p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total ($)</h3>
                    <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ number_format($venta->total, 2) }}</p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total (Bs.)</h3>
                    <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ number_format($venta->total_bs, 2) }}</p>
                </div>
            </div>

            {{-- Los repuestos se cobraron encima del precio del telefono, asi
                 que no estan dentro del Total de arriba y se muestran aparte. --}}
            @if ($venta->totalRepuestosCobrados() > 0)
                <div class="mt-6 flex items-center justify-between rounded-lg bg-green-50 dark:bg-green-900 p-3 text-sm">
                    <span class="text-green-800 dark:text-green-200">
                        Repuestos cobrados aparte
                        <span class="block text-xs text-green-700 dark:text-green-300">
                            No entran en el Total ($) de esta venta.
                        </span>
                    </span>
                    <span class="font-semibold text-green-900 dark:text-green-100 whitespace-nowrap">
                        $ {{ number_format($venta->totalRepuestosCobrados(), 2) }}
                    </span>
                </div>
            @endif
        </div>

        <!-- Sección de Productos Vendidos -->
        <div class="mt-8">
            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Productos de la Venta</h3>
            <div class="mb-4">
                @can('venta.create')
                    <x-primary-button wire:click="ventaEditar({{ $venta->id }})">
                        Agregar Producto
                    </x-primary-button>
                @endcan
            </div>
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden py-4 px-2">
                @livewire('venta.venta-detalle-table', ['venta' => $venta])
            </div>
        </div>

        <div class="mt-8">
            <x-repuestos-cobrados :ventas-repuestos="$venta->ventasRepuestos" />
        </div>
    </div>

    @livewire('venta.modals.venta-detalle-destroy-modal')
</div>
