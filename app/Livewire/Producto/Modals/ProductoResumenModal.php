<?php

namespace App\Livewire\Producto\Modals;

use App\Support\ResumenInventario;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Cuantos equipos vendibles hay en cada sucursal, por modelo y almacenamiento,
 * en texto listo para pegar en WhatsApp.
 *
 * Los conteos salen de ResumenInventario, los mismos del inicio. Lo que se ve
 * en el modal es el mismo texto que se copia, armado en un solo lugar.
 */
class ProductoResumenModal extends Component
{
    public bool $openModal = false;

    #[On('openProductoResumenModal')]
    public function open(): void
    {
        abort_unless(auth()->user()?->can('producto.index'), 403);

        $this->openModal = true;
    }

    public function closeModal(): void
    {
        $this->reset('openModal');
    }

    /** El texto que se muestra y se copia. Los asteriscos son negrita en WhatsApp. */
    private function texto(array $bloques): string
    {
        if (!$bloques) {
            return 'No hay equipos en inventario.';
        }

        $texto = '*Inventario disponible* — ' . now()->format('d/m/Y') . "\n";
        foreach ($bloques as $bloque) {
            $texto .= "\n*{$bloque['nombre']}* ({$bloque['total']})\n";
            foreach ($bloque['lineas'] as $linea) {
                $texto .= "{$linea['texto']}: {$linea['total']}\n";
            }
        }

        return $texto . "\n*Total: " . array_sum(array_column($bloques, 'total')) . '*';
    }

    public function render()
    {
        return view('livewire.producto.modals.producto-resumen-modal', [
            'texto' => $this->openModal ? $this->texto(ResumenInventario::bloques()) : '',
        ]);
    }
}
