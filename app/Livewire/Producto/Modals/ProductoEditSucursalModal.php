<?php

namespace App\Livewire\Producto\Modals;

use App\Enums\ProductoEstado;
use App\Models\Producto;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\On;

class ProductoEditSucursalModal extends Component
{

    /** Lo mismo que en el modal masivo: con uno o dos digitos la lista es ruido. */
    private const MIN_BUSQUEDA = 3;

    public $openModal = false;

    public $searchImei = '';
    public $filteredProductos = [];

    /** Por que la lista salio vacia. Ver motivoSinResultados(). */
    public $motivoBusqueda = '';

    public $productos = [];

    public $sucursal1_id;
    public $sucursal1;
    public $sucursal2_id;
    public $sucursal2;

    public function render()
    {
        return view('livewire.producto.modals.producto-edit-sucursal-modal', [
            // Origen: todas, para poder vaciar una sucursal desactivada.
            // Destino: solo activas.
            'sucursales' => Sucursal::orderBy('nombre')->get(),
            'destinos' => Sucursal::activas()->orderBy('nombre')->get(),
        ]);
    }

    #[On('openProductoEditSucursalModal')]
    public function openModal()
    {
        $this->openModal = true;
    }

    /** @return string[] Un equipo vendido o dado de baja no se transfiere. */
    private function estadosTransferibles(): array
    {
        return [
            ...ProductoEstado::disponibles(),
            ProductoEstado::Roto->value,
            ProductoEstado::Reparacion->value,
            ProductoEstado::Transito->value,
        ];
    }

    /** El conjunto del que se puede sacar. Un solo sitio para las dos consultas. */
    private function baseQuery(): Builder
    {
        return Producto::whereIn('estado', $this->estadosTransferibles())
            ->where('sucursal_id', $this->sucursal1?->id);
    }

    public function updatedSearchImei($value)
    {
        // Se vacia lo primero y siempre: al borrar caracteres se quedaban en
        // pantalla los resultados del termino anterior.
        $this->filteredProductos = [];
        $this->motivoBusqueda = '';

        $termino = trim((string) $value);

        if ($termino === '' || !$this->sucursal1) {
            return;
        }

        if (mb_strlen($termino) < self::MIN_BUSQUEDA) {
            $this->motivoBusqueda = 'Escribe al menos ' . self::MIN_BUSQUEDA . ' dígitos del IMEI.';
            return;
        }

        $idsExistentes = collect($this->productos)->pluck('id')->toArray();

        $this->filteredProductos = $this->baseQuery()
            // La vista pinta modelo->nombre en cada fila.
            ->with('modelo:id,nombre')
            ->buscarPorImei($termino)
            ->whereNotIn('id', $idsExistentes)
            ->take(10)
            ->get();

        if ($this->filteredProductos->isEmpty()) {
            $this->motivoBusqueda = $this->motivoSinResultados($termino, $idsExistentes);
        }
    }

    /**
     * Por que no salio nada. Una consulta mas, y solo con la lista vacia. Los
     * motivos son, uno a uno, lo que descartan baseQuery() y el whereNotIn.
     */
    private function motivoSinResultados(string $termino, array $idsExistentes): string
    {
        $producto = Producto::buscarPorImei($termino)->first();

        if (!$producto) {
            return "Ningún IMEI coincide con \"{$termino}\".";
        }

        if (in_array($producto->id, $idsExistentes)) {
            return "El IMEI {$producto->imei} ya está en la lista de abajo.";
        }

        if (!in_array($producto->estado, $this->estadosTransferibles())) {
            return "El IMEI {$producto->imei} está en estado \"{$producto->estado}\" y no se transfiere.";
        }

        $sucursal = $producto->sucursal?->nombre ?? 'ninguna';

        return "El IMEI {$producto->imei} está en la sucursal \"{$sucursal}\", no en \"{$this->sucursal1->nombre}\".";
    }

    public function updatedSucursal1Id()
    {
        $this->sucursal1 = Sucursal::find($this->sucursal1_id);

        // Cambiar el origen invalida lo buscado y lo ya elegido: esos equipos
        // salieron de la otra sucursal y transferirlos no es lo que se pidio.
        $this->productos = [];
        $this->searchImei = '';
        $this->filteredProductos = [];
        $this->motivoBusqueda = '';
    }

    public function updatedSucursal2Id()
    {
        $this->sucursal2 = Sucursal::find($this->sucursal2_id);
    }

    public function selectProducto($imei)
    {
        // Se revalida contra el conjunto de origen. Con el `where('imei', ...)`
        // a secas de antes, buscar, cambiar la sucursal de origen y pulsar una
        // sugerencia vieja colaba en la transferencia un equipo de otra
        // sucursal. Es el agujero que ya se tapo en el modal masivo.
        $productoFound = $this->baseQuery()->where('imei', $imei)->first();
        if (!$productoFound) return;

        foreach ($this->productos as $producto) {
            if ($producto['id'] === $productoFound->id) return;
        }

        array_unshift($this->productos, [
            'id' => $productoFound->id,
            'imei' => $productoFound->imei,
            'descripcion' => $productoFound->descripcion,
            'estado' => $productoFound->estado
        ]);

        $this->searchImei = '';
        $this->filteredProductos = [];
        $this->motivoBusqueda = '';
    }

    public function eliminarDetalle($index)
    {
        unset($this->productos[$index]);
        $this->productos = array_values($this->productos);
    }

    public function store()
    {
        $this->validate([
            'productos' => 'required|array|min:1',
            'sucursal2_id' => 'required|exists:sucursales,id,activa,1',
        ], [
            'sucursal2_id.required' => 'Elige la sucursal de destino.',
            'sucursal2_id.exists' => 'La sucursal de destino no existe o está desactivada.',
        ]);

        DB::transaction(function () {
            foreach ($this->productos as $producto) {
                $productoFound = Producto::find($producto['id']);
                $productoFound->update([
                    'sucursal_id' => $this->sucursal2_id
                ]);
            }
        });

        toastr()->success('Productos transferidos exitosamente');
        $this->dispatch('refreshProductoTable');
        $this->closeModal();
    }

    public function closeModal()
    {
        $this->reset();
    }
}
