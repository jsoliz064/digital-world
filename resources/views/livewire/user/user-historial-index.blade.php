<div>
    <div>
        <a href="{{ route('users') }}"
            class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-full">
            Ir a Usuarios</a>
    </div>

    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mt-4 mb-1">
        Historial de {{ $usuario->name }}
    </h2>
    <p class="text-center text-sm text-gray-500 dark:text-gray-400 mb-6">
        {{ $usuario->email }}
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div
            class="bg-brand-100 dark:bg-brand-900 p-6 rounded-2xl shadow-lg border border-brand-200 dark:border-brand-700">
            <h3 class="text-sm font-medium text-brand-700 dark:text-brand-300">Acciones registradas</h3>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-brand-900 dark:text-brand-100">
                {{ $acciones }}
            </p>
            {{-- La aclaracion importa: antes de la bitacora solo se anotaban los
                 cambios de estado de los telefonos, y el numero pareceria corto. --}}
            <p class="mt-1 text-xs text-brand-700 dark:text-brand-300">
                Antes de la bitácora solo quedaban registrados los cambios de estado de los teléfonos.
            </p>
        </div>

        <div
            class="bg-green-100 dark:bg-green-900 p-6 rounded-2xl shadow-lg border border-green-200 dark:border-green-700">
            <h3 class="text-sm font-medium text-green-700 dark:text-green-300">Hoy</h3>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-green-900 dark:text-green-100">
                {{ $accionesHoy }}
            </p>
        </div>

        <div
            class="bg-gray-100 dark:bg-gray-900 p-6 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Última acción</h3>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                {{ $ultimaAccion ? \Carbon\Carbon::parse($ultimaAccion)->format('d/m/Y H:i') : '—' }}
            </p>
        </div>
    </div>

    <div class="mt-4">
        @livewire('bitacora.bitacora-table', ['usuarioId' => $usuario->id], key('bitacora-usuario-' . $usuario->id))
    </div>
</div>
