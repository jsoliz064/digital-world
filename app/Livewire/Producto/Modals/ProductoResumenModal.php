<?php

namespace App\Livewire\Producto\Modals;

use App\Models\Producto;
use App\Models\Sucursal;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Cuantos equipos vendibles hay en cada sucursal, por modelo y almacenamiento,
 * en texto listo para pegar en WhatsApp.
 *
 * Cuenta solo lo disponible (Inventario y sin baja, Producto::disponibles()):
 * es lo que se le puede ofrecer a un cliente. Lo que se ve en el modal es el
 * mismo texto que se copia, armado en un solo lugar.
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

    /**
     * Los renglones de cada sucursal, con las sucursales por nombre (como el
     * card de ProductoIndex). Solo las que tienen equipos: una sucursal vacia no
     * le dice nada a quien recibe el mensaje.
     *
     * @return array<int, array{nombre: string, total: int, lineas: array<int, array{texto: string, total: int}>}>
     */
    private function bloques(): array
    {
        $filas = Producto::query()
            ->disponibles()
            ->join('productos_modelos as m', 'm.id', '=', 'productos.producto_modelo_id')
            ->selectRaw('productos.sucursal_id, m.nombre as modelo, productos.almacenamiento, COUNT(*) as total')
            ->groupBy('productos.sucursal_id', 'm.nombre', 'productos.almacenamiento')
            ->toBase()
            ->get()
            ->groupBy(fn($fila) => (int) $fila->sucursal_id);

        $nombres = Sucursal::whereIn('id', $filas->keys())->orderBy('nombre')->pluck('nombre', 'id');

        // Un equipo sin sucursal no deberia existir, pero si existe no puede
        // desaparecer del conteo: va al final, en su propio bloque.
        $orden = $nombres->keys()->all();
        if ($filas->has(0)) {
            $orden[] = 0;
        }

        return collect($orden)->map(function (int $id) use ($filas, $nombres) {
            // Orden natural: "64GB" antes que "128GB", que como texto saldria al reves.
            $lineas = $filas[$id]
                ->map(fn($fila) => [
                    'texto' => trim($fila->modelo . ' ' . $fila->almacenamiento),
                    'total' => (int) $fila->total,
                ])
                ->sort(fn($a, $b) => strnatcasecmp($a['texto'], $b['texto']))
                ->values()
                ->all();

            return [
                'nombre' => $nombres[$id] ?? 'Sin sucursal',
                'total' => array_sum(array_column($lineas, 'total')),
                'lineas' => $lineas,
            ];
        })->all();
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
            'texto' => $this->openModal ? $this->texto($this->bloques()) : '',
        ]);
    }
}
