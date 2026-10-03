<?php

namespace App\Livewire\RepuestoHistorial;

use App\Enums\RepuestoTipo;
use App\Models\Repuesto;
use App\Models\RepuestoMovimiento;
use Livewire\Component;

class RepuestoHistorialIndex extends Component
{
    public $repuesto;

    /** Entradas − Salidas según los documentos. NO es el stock real: ver la vista. */
    public int $balanceCalculado = 0;

    public function mount($repuesto_id)
    {
        $this->repuesto = Repuesto::with('stocks.sucursal')->findOrFail($repuesto_id);

        // categoria y modelo solo si el articulo las tiene: en un accesorio son
        // NULL por diseño y eran dos consultas para alimentar una linea de ficha
        // que ahora ni se pinta.
        if ($this->tipoDelArticulo()->tieneCamposDeRepuesto()) {
            $this->repuesto->load(['categoria', 'modelo']);
        }

        $fila = RepuestoMovimiento::paraRepuesto($this->repuesto->id)
            ->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN direccion = 'Entrada' THEN cantidad ELSE -cantidad END), 0) as saldo")
            ->first();

        $this->balanceCalculado = (int) ($fila->saldo ?? 0);
    }

    /**
     * El tipo del articulo, con fallback a Repuesto.
     *
     * El historial es UNA pantalla para los dos tipos, asi que casi todo lo que
     * decide aqui -- la linea de la ficha, el destino del boton de volver --
     * depende de esto.
     */
    public function tipoDelArticulo(): RepuestoTipo
    {
        return RepuestoTipo::tryFrom($this->repuesto->tipo ?? '') ?? RepuestoTipo::Repuesto;
    }

    public function render()
    {
        return view('livewire.repuesto-historial.index', [
            'tipoDelArticulo' => $this->tipoDelArticulo(),
        ]);
    }
}
