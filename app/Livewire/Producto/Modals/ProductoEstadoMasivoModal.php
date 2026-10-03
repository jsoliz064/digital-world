<?php

namespace App\Livewire\Producto\Modals;

use App\Enums\ProductoEstado;
use App\Models\Producto;
use App\Services\EstadoProductoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Attributes\On;

/**
 * Cambio masivo de estado de productos.
 *
 * Tuvo un segundo modo para cambiar el tipo de venta, y por eso todo el flujo
 * estaba generalizado sobre la columna a tocar. Ese atributo se retiro del
 * sistema -- la oferta paso a ser un estado mas -- y con el se fue la
 * generalizacion: queda un solo modo, un solo permiso y una sola columna.
 */
class ProductoEstadoMasivoModal extends Component
{
    /** El @can de la vista solo esconde el boton; esto protege el dato. */
    private const PERMISO = 'producto.estado-masivo';

    /**
     * Minimo de digitos para consultar. En el mostrador se teclean los ultimos
     * del IMEI; con uno o dos hay decenas de coincidencias y la lista, cortada
     * a diez, sale ruido.
     */
    private const MIN_BUSQUEDA = 3;

    public $openModal = false;

    public $searchImei = '';
    public $filteredProductos = [];

    /**
     * Por que la lista salio vacia. Un unico "No se encontraron resultados" no
     * distingue un IMEI que no existe de uno que si existe pero esta fuera del
     * origen elegido, que es el caso que hace perder tiempo.
     */
    public $motivoBusqueda = '';

    public $descripcion = '';
    public $productos = [];

    public $origen = '';
    public $destino = '';

    public $totalEnOrigen = 0;
    public $seleccionarTodos = false;
    public $compraId = null;

    public function render()
    {
        return view('livewire.producto.modals.producto-estado-masivo-modal', [
            'opciones' => $this->opciones(),
        ]);
    }

    #[On('openProductoEstadoMasivoModal')]
    public function openModal($compraId = null)
    {
        abort_unless(Auth::user()?->can(self::PERMISO), 403);

        $this->compraId = $compraId;
        $this->openModal = true;
    }

    /** @return array<string,string> valor => etiqueta */
    private function opciones(): array
    {
        // Subconjunto deliberado: Vendido y Reparacion quedan fuera porque
        // arrastran efectos (crear la venta, crear la reparacion, descontar
        // repuestos) que este modal no ejecuta. Se leen del enum para no
        // repetir cadenas sueltas.
        return collect([
            ProductoEstado::Inventario,
            ProductoEstado::Oferta,
            ProductoEstado::Fuera,
            ProductoEstado::Transito,
            ProductoEstado::Roto,
        ])->mapWithKeys(fn($caso) => [$caso->value => $caso->label()])->toArray();
    }

    /** El conjunto de origen. Un solo sitio para las tres consultas. */
    private function baseQuery(): Builder
    {
        $query = Producto::where('estado', $this->origen);

        if ($this->compraId) {
            $query->where('compra_id', $this->compraId);
        }

        return $query;
    }

    /** Fila de la tabla de elegidos. */
    private function aDetalle(Producto $producto): array
    {
        return [
            'id' => $producto->id,
            'imei' => $producto->imei,
            'descripcion' => $producto->descripcion,
            'estado' => $producto->estado,
        ];
    }

    // ==================================================================
    // Seleccion
    // ==================================================================

    public function updatedOrigen()
    {
        $this->productos = [];
        $this->searchImei = '';
        $this->filteredProductos = [];
        $this->motivoBusqueda = '';
        $this->seleccionarTodos = false;

        $this->totalEnOrigen = empty($this->origen) ? 0 : $this->baseQuery()->count();
    }

    public function updatedSeleccionarTodos($value)
    {
        if ($value && !empty($this->origen)) {
            $this->productos = $this->baseQuery()->get()
                ->map(fn($producto) => $this->aDetalle($producto))
                ->toArray();
        } else {
            $this->productos = [];
        }
    }

    public function updatedSearchImei($value)
    {
        // Se vacia lo primero y siempre: al borrar caracteres se quedaban en
        // pantalla los resultados del termino anterior.
        $this->filteredProductos = [];
        $this->motivoBusqueda = '';

        $termino = trim((string) $value);

        if ($termino === '') {
            return;
        }

        if (empty($this->origen)) {
            $this->motivoBusqueda = 'Elige primero un origen.';
            return;
        }

        if (mb_strlen($termino) < self::MIN_BUSQUEDA) {
            $this->motivoBusqueda = 'Escribe al menos ' . self::MIN_BUSQUEDA . ' dígitos del IMEI.';
            return;
        }

        $idsExistentes = collect($this->productos)->pluck('id')->toArray();

        $this->filteredProductos = $this->baseQuery()
            // La vista pinta modelo->nombre en cada fila: sin esto son diez
            // consultas mas por cada tecleo.
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
     * Por que no salio nada. Una consulta mas, y solo cuando la lista viene
     * vacia. Los motivos son, uno a uno, lo que descartan baseQuery() y el
     * whereNotIn de arriba, asi que el mensaje no puede contradecir a la
     * busqueda.
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

        if ($this->compraId && $producto->compra_id != $this->compraId) {
            return "El IMEI {$producto->imei} no pertenece a esta compra.";
        }

        return "El IMEI {$producto->imei} existe, pero su estado es \"{$producto->estado}\" y el origen es \"{$this->origen}\".";
    }

    public function selectProducto($imei)
    {
        // Se revalida contra el conjunto de origen. Antes era un
        // `where('imei', ...)` a secas, asi que buscar, cambiar el origen y
        // pulsar una sugerencia vieja colaba un producto que no pertenecia.
        $productoFound = $this->baseQuery()->where('imei', $imei)->first();

        if (!$productoFound)
            return;

        foreach ($this->productos as $producto) {
            if ($producto['id'] === $productoFound->id)
                return;
        }

        array_unshift($this->productos, $this->aDetalle($productoFound));

        $this->searchImei = '';
        $this->filteredProductos = [];
        $this->motivoBusqueda = '';
    }

    public function eliminarDetalle($index)
    {
        unset($this->productos[$index]);
        $this->productos = array_values($this->productos);

        // Ya no estan todos.
        $this->seleccionarTodos = false;
    }

    // ==================================================================
    // Guardado
    // ==================================================================

    public function store()
    {
        // La unica capa que protege el dato de verdad.
        abort_unless(Auth::user()?->can(self::PERMISO), 403);

        $valores = array_keys($this->opciones());

        $this->validate([
            'productos' => 'required|array|min:1',
            'descripcion' => 'required|string|min:1|max:255',
            // Rule::in y no `in:...`: en cuanto un valor lleve una coma, la
            // regla en cadena se partiria en silencio.
            'origen' => ['required', Rule::in($valores)],
            'destino' => ['required', Rule::in($valores)],
        ], [
            'origen.required' => 'Debe seleccionar un origen.',
            'destino.required' => 'Debe seleccionar un destino.',
        ]);

        if ($this->origen === $this->destino) {
            $this->addError('destino', 'El destino debe ser diferente al origen.');
            return;
        }

        $estados = app(EstadoProductoService::class);

        // Una sola transaccion para el lote entero, no una por producto: con
        // una por iteracion, fallar en el quinto de diez dejaria cuatro
        // cambiados y seis sin tocar, y el usuario viendo un error generico sin
        // saber cuales se aplicaron.
        DB::transaction(function () use ($estados) {
            foreach ($this->productos as $detalle) {
                // La precondicion exige que siga en el origen ELEGIDO. Si uno
                // se movio mientras el modal estaba abierto, revienta el lote
                // entero con un mensaje que lo nombra, en lugar de saltarselo en
                // silencio: el usuario eligio ese conjunto y tiene derecho a
                // saber que ya no es el que creia.
                //
                // Sin exigirPermiso: este modal tiene su propia puerta
                // (producto.estado-masivo) y su lista de destinos es un
                // subconjunto fijo, no la filtrada por permisos del select del
                // modal individual.
                $estados->cambiar(
                    (int) $detalle['id'],
                    ProductoEstado::from($this->origen),
                    ProductoEstado::from($this->destino),
                    $this->descripcion,
                );
            }
        });

        toastr()->success('Productos cambiados de estado exitosamente');

        $this->dispatch('refreshProductoTable');
        $this->closeModal();
    }

    public function closeModal()
    {
        $this->reset();
    }
}
