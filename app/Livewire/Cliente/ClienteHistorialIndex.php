<?php

namespace App\Livewire\Cliente;

use App\Models\Cliente;
use App\Models\Venta;
use Livewire\Component;

class ClienteHistorialIndex extends Component
{
    public $cliente;

    /** Cifras FIJAS de la ficha: no se mueven con los filtros de la tabla. */
    public int $ordenes = 0;
    public float $totalGastado = 0;
    public ?string $ultimaCompra = null;

    public function mount($cliente_id)
    {
        $this->cliente = Cliente::findOrFail($cliente_id);

        // Van aqui y no en la tabla a proposito: el pie de la tabla dice "esto es
        // lo que estoy mirando"; estas tarjetas dicen "esto es lo que este cliente
        // lleva comprado". Es el mismo reparto que ProductoHistorialIndex.
        //
        // Estrictamente por cliente_id: es lo unico que sigue a la persona. Una
        // venta es una orden, con todo lo que lleve (equipos, repuestos,
        // accesorios, los cobros de reparacion y la mano de obra).
        $fila = Venta::where('cliente_id', $this->cliente->id)
            ->toBase()
            ->selectRaw('
                COUNT(*) as ordenes,
                COALESCE(SUM(total), 0) as total_gastado,
                MAX(created_at) as ultima
            ')
            ->first();

        $this->ordenes = (int) ($fila->ordenes ?? 0);
        $this->totalGastado = round((float) ($fila->total_gastado ?? 0), 2);
        $this->ultimaCompra = $fila->ultima ?? null;
    }

    public function render()
    {
        return view('livewire.cliente.cliente-historial-index');
    }
}
