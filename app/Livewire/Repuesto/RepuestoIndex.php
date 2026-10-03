<?php

namespace App\Livewire\Repuesto;

use App\Models\ProductoModelo;
use App\Models\Repuesto;
use App\Enums\RepuestoTipo;
use App\Models\RepuestoCategoria;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RepuestoIndex extends Component
{

    public $modelos = [];
    public $categorias = [];
    public $selectedMinStock = 0;

    /**
     * El tipo de articulo de esta pantalla, fijado por la ruta.
     *
     * Era un filtro con pestanas; ahora /inventario/repuestos y
     * /inventario/accesorios son dos paginas, y el tipo llega por mount() y no
     * por evento. Eso NO es preferencia: applyFilters() despacha
     * 'filtersUpdated' durante el mount del indice, cuando RepuestoTable
     * todavia no existe, asi que nadie recibe ese primer evento. Con el filtro
     * viejo pasaba inadvertido porque el default ('' = Todo) era lo deseado;
     * con un tipo por ruta, el primer render de /inventario/accesorios habria
     * mostrado TODO el inventario y lo habria reemplazado despues.
     *
     * #[Locked] porque un payload podria cambiarlo y ver el otro listado con
     * los permisos de este.
     */
    #[Locked]
    public string $tipo = '';

    public $selectedModelos = [];
    public $selectedCategorias = [];
    
    public $totalRepuestos = 0;
    public $totalBajoStock = 0;

    /**
     * Cuanto hay en cada sucursal: [['nombre', 'unidades', 'articulos'], ...].
     *
     * La columna "Por sucursal" de la tabla ya da el reparto articulo por
     * articulo, pero no contestaba "?cuanto hay en Comercial Norte?" sin leer
     * fila por fila.
     */
    public $sucursalesResumen = [];

    public function mount(string $tipo)
    {
        // from() y no tryFrom(): un tipo invalido tiene que reventar. scopeDeTipo()
        // trata el vacio como "sin filtro", asi que un wiring roto listaria todo
        // el catalogo en silencio en vez de fallar.
        $this->tipo = RepuestoTipo::from($tipo)->value;

        // Los dos catalogos solo si la pantalla los va a pintar: en accesorios
        // eran dos consultas y dos colecciones publicas viajando en el payload
        // de Livewire en cada request, para alimentar selects que no se muestran.
        if ($this->muestraCamposDeRepuesto()) {
            $this->modelos = ProductoModelo::orderBy('nombre')->get();
            $this->categorias = RepuestoCategoria::orderBy('nombre')->get();
        }

        $this->applyFilters();
    }

    public function render()
    {
        return view('livewire.repuesto.repuesto-index');
    }

    public function openRepuestoCreateModal()
    {
        $this->dispatch('openRepuestoCreateModal');
    }

    public function openRepuestoTipoCambioMasivoModal()
    {
        $this->dispatch('openRepuestoTipoCambioMasivoModal');
    }

    /**
     * Si esta pantalla muestra los atributos de una pieza de reparacion.
     *
     * Lo pregunta la vista para esconder los filtros de Modelos y Categorias, y
     * lo usa filtrosDePieza() para no aplicarlos nunca en accesorios.
     */
    public function muestraCamposDeRepuesto(): bool
    {
        return $this->tipoEnum()->tieneCamposDeRepuesto();
    }

    /**
     * Los filtros de pieza que esta pantalla admite de verdad.
     *
     * En accesorios, `producto_modelo_id` y `repuesto_categoria_id` son NULL
     * siempre, y un whereIn sobre NULL no devuelve NADA: elegir un modelo dejaba
     * la lista y los dos contadores en cero sin ninguna explicacion. Se ignoran
     * en el origen y no confiando en que la interfaz no los mande, porque son
     * propiedades publicas y un payload si puede mandarlos.
     *
     * @return array{modelos: array, categorias: array}
     */
    private function filtrosDePieza(): array
    {
        if (!$this->muestraCamposDeRepuesto()) {
            return ['modelos' => [], 'categorias' => []];
        }

        return [
            'modelos' => $this->selectedModelos,
            'categorias' => $this->selectedCategorias,
        ];
    }

    /**
     * Los articulos de ESTA pantalla, con sus filtros aplicados.
     *
     * Fuente unica: la usan los dos contadores y el resumen por sucursal, que
     * tienen que cuadrar entre si. Estaba escrita dentro de calculateTotals() y
     * el resumen habria sido la segunda copia de la misma lista de filtros.
     */
    private function articulosFiltrados(): Builder
    {
        $filtros = $this->filtrosDePieza();

        return Repuesto::query()
            ->when(!empty($filtros['modelos']), fn($q) => $q->whereIn('producto_modelo_id', $filtros['modelos']))
            ->when(!empty($filtros['categorias']), fn($q) => $q->whereIn('repuesto_categoria_id', $filtros['categorias']))
            ->when((bool)$this->selectedMinStock, fn($q) => $q->bajoStock())
            // where estricto y no deTipo(): ese scope ignora los valores vacios
            // a proposito, y aqui el tipo nunca puede faltar.
            ->where('tipo', $this->tipo);
    }

    public function calculateTotals()
    {
        $this->totalRepuestos = $this->articulosFiltrados()->count();
        // Sigue mirando el TOTAL y no cada sucursal: es el criterio que ya tenia
        // el sistema y repartir el stock no lo cambia.
        $this->totalBajoStock = $this->articulosFiltrados()->bajoStock()->count();
        $this->sucursalesResumen = $this->resumenPorSucursal();
    }

    /**
     * Unidades y articulos distintos en cada sucursal, con los filtros puestos.
     *
     * POR QUE withSum SOBRE sucursales Y NO UN GROUP BY SOBRE EL STOCK
     * Son subconsultas correlacionadas, asi que una sucursal SIN NINGUNA fila de
     * stock sigue saliendo, con NULL que aqui se normaliza a 0. Con un
     * `GROUP BY sucursal_id` sobre repuestos_sucursales, Comercial Norte y
     * Shopping Bolivar desaparecerian de la pantalla -- y es literalmente lo que
     * pasaria hoy: el backfill dejo TODO el stock en el Almacen y las otras dos
     * no tienen ni una fila. Ademas es una consulta, no una por sucursal.
     *
     * @return array<int, array{nombre: string, unidades: int, articulos: int}>
     */
    private function resumenPorSucursal(): array
    {
        // Como subconsulta y no como lista de ids: no se traen a PHP.
        $articulos = $this->articulosFiltrados()->select('repuestos.id');

        return Sucursal::query()
            ->withSum(
                ['stocksRepuestos as unidades' => fn($q) => $q->whereIn('repuesto_id', $articulos)],
                'cantidad'
            )
            ->withCount([
                'stocksRepuestos as articulos' => fn($q) => $q
                    ->whereIn('repuesto_id', $articulos)
                    // Mismo criterio que Repuesto::desgloseStock(): una fila en
                    // cero no es "hay este articulo aqui", es el rastro de que
                    // lo hubo. Por eso repuestos cuenta 6 y no 7: la Bateria
                    // tiene fila en el Almacen con cantidad 0.
                    ->where('cantidad', '<>', 0),
            ])
            // El card de productos no ordena y su filtro de sucursal si, asi que
            // las dos listas salen en orden distinto. Aqui se ordena.
            ->orderBy('nombre')
            ->get()
            ->map(fn($sucursal) => [
                'nombre' => $sucursal->nombre,
                // withSum da NULL cuando no hay ni una fila.
                'unidades' => (int) ($sucursal->unidades ?? 0),
                'articulos' => (int) $sucursal->articulos,
            ])
            ->all();
    }

    public function updated($propertyName)
    {
        if (in_array($propertyName, ['selectedModelos.0', 'selectedMinStock', 'selectedCategorias.0'])) {
            $this->applyFilters();
        }
    }

    /** El enum de esta pantalla, para los titulos y los permisos de la vista. */
    public function tipoEnum(): RepuestoTipo
    {
        return RepuestoTipo::from($this->tipo);
    }

    /** 'repuesto' o 'accesorio': el prefijo de los @can de esta pantalla. */
    public function permiso(): string
    {
        return $this->tipoEnum()->permiso();
    }

    public function resetFilters()
    {
        $this->reset(['selectedModelos', 'selectedMinStock', 'selectedCategorias']);
        $this->applyFilters();
    }

    public function applyFilters()
    {
        $this->calculateTotals();

        $filtros = $this->filtrosDePieza();

        $this->dispatch('filtersUpdated', [
            'selectedModelos' => $filtros['modelos'],
            'selectedMinStock' => $this->selectedMinStock,
            'selectedCategorias' => $filtros['categorias'],
        ]);
    }
}
