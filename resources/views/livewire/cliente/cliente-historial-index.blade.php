<div>
    <div>
        <a href="{{ route('clientes') }}"
            class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-full">
            Ir a Clientes</a>
    </div>

    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mt-4 mb-1">
        Historial de {{ $cliente->nombre }}
    </h2>

    {{-- Solo las partes que existen: casi ningún cliente tiene CI y teléfono a
         la vez, y un «Sin CI» inventado es peor que el hueco. --}}
    <p class="text-center text-sm text-gray-500 dark:text-gray-400 mb-6">
        <x-cliente-etiqueta :nombre="$cliente->ci ? 'CI ' . $cliente->ci : null" :ci="$cliente->telefono" :telefono="$cliente->correo" />
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div
            class="bg-brand-100 dark:bg-brand-900 p-6 rounded-2xl shadow-lg border border-brand-200 dark:border-brand-700">
            <h3 class="text-sm font-medium text-brand-700 dark:text-brand-300">Órdenes</h3>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-brand-900 dark:text-brand-100">
                {{ $ordenes }}
            </p>
            {{-- La aclaración importa: los repuestos cobrados con un teléfono no
                 son una orden aparte, y sin decirlo el número parecería corto. --}}
            <p class="mt-1 text-xs text-brand-700 dark:text-brand-300">
                Los repuestos cobrados con un teléfono cuentan dentro de su venta.
            </p>
        </div>

        <div
            class="bg-green-100 dark:bg-green-900 p-6 rounded-2xl shadow-lg border border-green-200 dark:border-green-700">
            <h3 class="text-sm font-medium text-green-700 dark:text-green-300">Total comprado</h3>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-green-900 dark:text-green-100">
                $ {{ number_format($totalGastado, 2) }}
            </p>
            <p class="mt-1 text-xs text-green-700 dark:text-green-300">
                Incluye los repuestos cobrados por encima del precio del equipo.
            </p>
        </div>

        <div
            class="bg-gray-100 dark:bg-gray-900 p-6 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Última compra</h3>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                {{ $ultimaCompra ? \Carbon\Carbon::parse($ultimaCompra)->format('d/m/Y') : '—' }}
            </p>
        </div>
    </div>

    <div class="mt-4">
        @livewire('cliente.cliente-ordenes-table', ['cliente_id' => $cliente->id], key('ordenes-table-' . $cliente->id))
    </div>
</div>
