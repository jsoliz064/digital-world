{{--
    Las tres cifras de comisiones de docs/05: pendiente, por pagar y pagado.
    Recibe el arreglo de Comision::cifras(). Lo usan la pantalla de comisiones,
    «Mis comisiones» y las fichas del usuario y del técnico.
--}}
@props(['cifras', 'columnas' => 'sm:grid-cols-3'])

@php
    $bs = fn($v) => 'Bs ' . number_format((float) $v, 2, ',', '.');
@endphp

<div {{ $attributes->merge(['class' => 'grid grid-cols-1 gap-4 ' . $columnas]) }}>
    <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-lg shadow-sm dark:bg-gray-800">
        <p class="text-sm font-medium text-amber-900 dark:text-amber-200">Pendiente</p>
        <p class="text-2xl font-bold text-amber-800 dark:text-amber-100">{{ $bs($cifras['pendiente'] ?? 0) }}</p>
        <p class="text-xs text-amber-700 dark:text-amber-300">Ventas sin cobrar entera o reparaciones sin terminar</p>
    </div>
    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg shadow-sm dark:bg-gray-800">
        <p class="text-sm font-medium text-blue-900 dark:text-blue-200">Por pagar</p>
        <p class="text-2xl font-bold text-blue-800 dark:text-blue-100">{{ $bs($cifras['por_pagar'] ?? 0) }}</p>
        <p class="text-xs text-blue-700 dark:text-blue-300">Ya ganado, esperando liquidación</p>
    </div>
    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg shadow-sm dark:bg-gray-800">
        <p class="text-sm font-medium text-green-900 dark:text-green-200">Pagado</p>
        <p class="text-2xl font-bold text-green-800 dark:text-green-100">{{ $bs($cifras['pagado'] ?? 0) }}</p>
        <p class="text-xs text-green-700 dark:text-green-300">Lo que ya se entregó</p>
    </div>
</div>
