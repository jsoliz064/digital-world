<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">
        Productos en Reparación del Técnico: {{ $tecnico->nombre }} {!! $tecnico->getDivColor() !!}
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">

        {{-- Tarjeta: Productos Pendientes --}}
        <div class="bg-brand-100 border-l-4 border-brand-500 text-brand-700 p-4 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 bg-brand-500 rounded-full text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-brand-900">Productos Pendientes</p>
                    <p class="text-2xl font-bold">{{ $cant_productos_pendientes }}</p>
                </div>
            </div>
        </div>

        {{-- Tarjeta: Productos por Pagar --}}
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 bg-yellow-500 rounded-full text-white">

                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>

                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-yellow-900">Productos por Pagar</p>
                    <p class="text-2xl font-bold">{{ $cant_productos_no_pagados }}</p>
                </div>
            </div>
        </div>

        {{-- Tarjeta: Total por Pagar --}}
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md">
            <div class="flex items-center">
                <div class="p-3 bg-red-500 rounded-full text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-red-900">Total por Pagar</p>
                    <p class="text-2xl font-bold">Bs. {{ number_format($total_productos_no_pagados, 2, ',', '.') }}</p>
                </div>
            </div>
        </div>

    </div>

    @can('tecnico.productos.pagos')
        <x-primary-button wire:click="openTecnicoPagoModal({{ $tecnico->id }})">
            Realizar Pagos
        </x-primary-button>
    @endcan
    @can('tecnico.productos.terminar')
        <button wire:click="openTecnicoTerminarModal({{ $tecnico->id }})" class="mt-2 inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest shadow-sm hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
             Terminar Reparaciones
        </button>
    @endcan
    @can('tecnico.productos.exportar')
        <button wire:click="exportProductosExcel" wire:loading.attr="disabled"
            class="my-2 inline-flex items-center px-3 py-1.5 text-sm font-medium 
           bg-green-600 text-white rounded-lg shadow hover:bg-green-700 
           disabled:opacity-50 disabled:cursor-not-allowed transition"
            title="Exportar excel">
            <i class="fas fa-file-excel mr-2"></i>
            Exportar Productos Pendientes

            <!-- Spinner -->
            <div wire:loading wire:target="exportProductosExcel"
                class="ml-2 inline-block h-4 w-4 border-2 border-white border-t-transparent rounded-full animate-spin">
            </div>
        </button>
    @endcan
    <div class="mt-4">
        @livewire('tecnico-producto.tecnico-producto-table', ['tecnico_id' => $tecnico->id])
    </div>

    @livewire('tecnico-producto.modals.reparacion-edit-modal')
    @livewire('tecnico-producto.modals.reparacion-show-modal')
    @livewire('tecnico-producto.modals.tecnico-pago-modal')
    @livewire('tecnico-producto.modals.tecnico-terminar-modal')
</div>
