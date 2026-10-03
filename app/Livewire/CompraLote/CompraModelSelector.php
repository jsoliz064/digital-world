<?php

namespace App\Livewire\CompraLote;

use App\Models\Producto;
use App\Models\ProductoModelo;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class CompraModelSelector extends Component
{
    public $models;
    public $compraId;

    public function mount($compraId)
    {
        $this->compraId = is_numeric($compraId) ? (int)$compraId : null;
    }

    #[On('loadModelCounts')]
    public function loadModelCounts()
    {
        // 1. Obtenemos todos los modelos y sucursales
        $models = ProductoModelo::orderBy('id')->get();
        $sucursales = Sucursal::all();

        // 2. Obtenemos todos los conteos de productos en una sola consulta
        $counts = Producto::query()
            ->select('producto_modelo_id', 'sucursal_id', 'estado', DB::raw('COUNT(*) as total'))
            ->where('compra_id', $this->compraId) // Filtro clave para esta vista
            ->whereNotNull('sucursal_id')
            ->groupBy('producto_modelo_id', 'sucursal_id', 'estado')
            ->get();

        // 3. Procesamos los datos para estructurarlos
        $processedCounts = [];
        foreach ($counts as $countData) {
            $modelId = $countData->producto_modelo_id;
            $sucursalId = $countData->sucursal_id;
            $estado = $countData->estado;

            if (!isset($processedCounts[$modelId])) {
                $processedCounts[$modelId] = [];
            }
            if (!isset($processedCounts[$modelId][$sucursalId])) {
                $processedCounts[$modelId][$sucursalId] = [
                    'inventario' => 0,
                    'vendido' => 0,
                    'reparacion' => 0,
                    'fuera' => 0,
                    'roto' => 0,
                    'oferta' => 0,
                    'transito' => 0,
                ];
            }

            if ($estado) {
                $estadoKey = strtolower($estado);
                if (array_key_exists($estadoKey, $processedCounts[$modelId][$sucursalId])) {
                    $processedCounts[$modelId][$sucursalId][$estadoKey] += $countData->total;
                }
            }

            // El contador de ofertas lo llena solo el bloque de arriba: Oferta
            // es un estado mas y entra por strtolower($estado).
        }

        // 4. Llenamos los modelos con los datos resumidos
        foreach ($models as $model) {
            $model->sucursales_summary = collect();
            $totalProductos = 0;

            foreach ($sucursales as $sucursal) {
                $summary = [
                    'nombre' => $sucursal->nombre,
                    'total' => 0,
                    'inventario' => 0,
                    'reparacion' => 0,
                    'fuera' => 0,
                    'roto' => 0,
                    'oferta' => 0,
                    'transito' => 0,
                ];

                if (isset($processedCounts[$model->id][$sucursal->id])) {
                    $sucursalData = $processedCounts[$model->id][$sucursal->id];
                    $summary['inventario'] = $sucursalData['inventario'];
                    $summary['reparacion'] = $sucursalData['reparacion'];
                    $summary['fuera'] = $sucursalData['fuera'];
                    $summary['roto'] = $sucursalData['roto'];
                    $summary['oferta'] = $sucursalData['oferta'];
                    $summary['transito'] = $sucursalData['transito'];
                    // Sin los vendidos, que ya no son stock. 'oferta' SI suma:
                    // cuando salia de tipo_venta era un subconteo de inventario
                    // y sumarlo contaba doble, pero ahora es un estado aparte y
                    // dejarlo fuera borraba esos equipos del total del modelo.
                    $summary['total'] = $sucursalData['inventario'] + $sucursalData['oferta'] + $sucursalData['reparacion'] + $sucursalData['fuera'] + $sucursalData['roto'] + $sucursalData['transito'];
                }

                // Añadimos el total de esta sucursal al total general del modelo
                $totalProductos += $summary['total'];

                $model->sucursales_summary->put($sucursal->id, $summary);
            }

            $model->productos_count = $totalProductos;
        }

        $this->models = $models;
    }

    public function openModalSelector($modelId)
    {
        $this->dispatch('openModalSelector', modelId: $modelId);
    }

    public function render()
    {
        $this->loadModelCounts();
        return view('livewire.compra-lote.compra-model-selector');
    }
}
