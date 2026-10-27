<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal" maxWidth="lg">
            <x-slot name="title">
                Resumen de inventario
            </x-slot>

            <x-slot name="content">
                <hr>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Equipos en Inventario por sucursal, listos para pegar en WhatsApp.
                </p>
                <div class="mt-3 max-h-[60vh] overflow-y-auto whitespace-pre-wrap rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-800 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200">{{ $texto }}</div>
            </x-slot>

            <x-slot name="footer">
                {{-- Copiar no va al servidor. Sin HTTPS no hay navigator.clipboard:
                     ahi se copia desde un textarea temporal. --}}
                <div class="flex w-full flex-col-reverse gap-3 sm:flex-row sm:justify-between"
                    x-data="{
                        copiado: false,
                        texto: @js($texto),
                        async copiar() {
                            try {
                                await navigator.clipboard.writeText(this.texto);
                            } catch (e) {
                                const area = document.createElement('textarea');
                                area.value = this.texto;
                                area.style.position = 'fixed';
                                area.style.opacity = '0';
                                document.body.appendChild(area);
                                area.select();
                                document.execCommand('copy');
                                area.remove();
                            }
                            this.copiado = true;
                            setTimeout(() => this.copiado = false, 2000);
                        },
                    }">
                    <x-secondary-button wire:click="closeModal">Cerrar</x-secondary-button>
                    <x-button type="button" x-on:click="copiar()">
                        <i class="far fa-copy mr-2"></i>
                        <span x-text="copiado ? '¡Copiado!' : 'Copiar todo'">Copiar todo</span>
                    </x-button>
                </div>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
