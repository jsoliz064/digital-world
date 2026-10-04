<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">
        Productos en Reparación del Técnico: {{ $tecnico->nombre }} {!! $tecnico->getDivColor() !!}
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-4">

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

    </div>

    <x-comision-cifras :cifras="$cifras" class="mb-6" />

    @can('comision.liquidar')
        <x-primary-button wire:click="liquidar">
            Liquidar comisiones
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
    @can('comision.liquidar')
        @livewire('comision.modals.comision-liquidar-modal')
    @endcan
    @livewire('tecnico-producto.modals.tecnico-terminar-modal')
</div>
