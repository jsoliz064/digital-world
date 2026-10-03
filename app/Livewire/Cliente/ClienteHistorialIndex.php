<?php

namespace App\Livewire\Cliente;

use App\Models\Cliente;
use App\Models\ClienteOrden;
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
        $fila = ClienteOrden::paraCliente($this->cliente->id)
            ->toBase()
            ->selectRaw('
                COUNT(*) as ordenes,
                COALESCE(SUM(total + total_repuestos), 0) as total_gastado,
                MAX(fecha) as ultima
            ')
            ->first();

        $this->ordenes = (int) ($fila->ordenes ?? 0);
        // El total suma los repuestos cobrados con un telefono: su importe va por
        // encima del total del equipo, asi que entra en lo que el cliente pago
        // aunque su venta enlazada no sea una orden aparte.
        $this->totalGastado = round((float) ($fila->total_gastado ?? 0), 2);
        $this->ultimaCompra = $fila->ultima ?? null;
    }

    public function render()
    {
        return view('livewire.cliente.cliente-historial-index');
    }
}
