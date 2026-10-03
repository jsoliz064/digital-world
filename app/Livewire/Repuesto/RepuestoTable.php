<?php

namespace App\Livewire\Repuesto;

use App\Enums\RepuestoTipo;
use App\Models\Repuesto;
use Livewire\Attributes\Locked;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Livewire\Attributes\On;
use Illuminate\Database\Eloquent\Builder;

class RepuestoTable extends DataTableComponent
{
    protected $model = Repuesto::class;
    public $selectedModelos = [];
    public $selectedCategorias = [];
    public $selectedMinStock = 0;

    /**
     * El tipo de la pantalla, por parametro de montaje y no por evento: ver el
     * comentario de RepuestoIndex::$tipo. #[Locked] por lo mismo.
     */
    #[Locked]
    public string $tipo = '';

    public function mount(string $tipo): void
    {
        $this->tipo = RepuestoTipo::from($tipo)->value;
    }

    /** El prefijo de permiso, que viaja a actions-buttons.blade.php. */
    public function permiso(): string
    {
        return RepuestoTipo::from($this->tipo)->permiso();
    }

    /**
     * Si esta tabla muestra los atributos de una pieza de reparacion.
     *
     * En accesorios, fabricante, modelo, categoria y color son NULL por diseño:
     * las columnas saldrian en blanco, con su sortable() y su searchable()
     * inertes, y las dos columnas de relacion harian un LEFT JOIN que no casa
     * nunca.
     */
    public function muestraCamposDeRepuesto(): bool
    {
        return RepuestoTipo::from($this->tipo)->tieneCamposDeRepuesto();
    }

    #[On('filtersUpdated')]
    public function updateTableFilters($filters)
    {
        // Los filtros de pieza se descartan en accesorios, y hace falta hacerlo
        // AQUI tambien y no solo en RepuestoIndex: #[Locked] protege $tipo, no
        // estos arrays, asi que un payload podria mandarlos para dejar la
        // pantalla de accesorios en cero filas (whereIn sobre NULL no acierta
        // nunca).
        $dePieza = $this->muestraCamposDeRepuesto();

        $this->selectedModelos = $dePieza ? ($filters['selectedModelos'] ?? []) : [];
        $this->selectedCategorias = $dePieza ? ($filters['selectedCategorias'] ?? []) : [];
        $this->selectedMinStock = $filters['selectedMinStock'] ?? 0;
        // El tipo NO llega por aqui: lo fija la ruta en mount().

        $this->setBuilder($this->builder());
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id');

        // El color solo se lee en el format de Nombre, que en accesorios no se
        // usa: sin esto serian dos columnas NULL en cada SELECT.
        if ($this->muestraCamposDeRepuesto()) {
            $this->setAdditionalSelects(['repuestos.color', 'repuestos.color_hex']);
        }
    }

    public function columns(): array
    {
        $columnas = [
            Column::make("Id", "id")
                ->sortable(),
        ];

        if ($this->muestraCamposDeRepuesto()) {
            // Nombre y color van juntos: el punto pintado y el nombre del color
            // entre parentesis se renderizan dentro de esta misma celda.
            $columnas[] = Column::make("Nombre", "nombre")
                ->format(fn($value, $row) => $row->getNombreConColor())
                ->html()
                ->sortable()
                // El buscador tambien mira `color`, que ya no tiene columna
                // propia donde escribir el termino.
                ->searchable(function (Builder $query, $term) {
                    $query->orWhere('repuestos.nombre', 'like', '%' . $term . '%')
                        ->orWhere('repuestos.color', 'like', '%' . $term . '%');
                });
        } else {
            // Un accesorio no tiene color, asi que ni se pinta el punto ni se
            // busca por un LIKE sobre una columna que siempre es NULL.
            $columnas[] = Column::make("Nombre", "nombre")
                ->sortable()
                ->searchable();
        }

        // Sin columna "Tipo": en esta pantalla su valor es constante. El badge
        // sigue vivo en el selector del catalogo, que si mezcla los dos tipos.
        //
        // Y sin las tres de pieza en accesorios. NO se declaran y se esconden con
        // hideIf(): el paquete las seguiria seleccionando y uniendo las
        // relaciones. Al no declararlas desaparecen tambien los dos LEFT JOIN.
        if ($this->muestraCamposDeRepuesto()) {
            $columnas[] = Column::make("Fabricante", "fabricante")
                ->sortable()
                ->searchable()
                ->collapseOnTablet();
            $columnas[] = Column::make("Categoria", "categoria.nombre")
                ->sortable()
                ->searchable();
            $columnas[] = Column::make("Modelo", "modelo.nombre")
                ->sortable()
                ->searchable();
        }

        return array_merge($columnas, [
            Column::make("Costo (USD)", "costo")
                ->sortable()
                ->collapseOnTablet(),
            Column::make("Precio (USD)", "precio")
                ->sortable()
                ->collapseOnTablet(),
            Column::make("Tipo de Cambio", "tipo_cambio")
                ->sortable()
                ->collapseOnTablet(),
            // El TOTAL, que sigue siendo columna fisica y por eso ordenable. El
            // umbral sale de la constante del modelo: estaba escrito cuatro
            // veces con dos redacciones distintas (>= 10 aqui, <= 9 en el
            // filtro) que por suerte significaban lo mismo.
            Column::make("Cantidad", "cantidad")
                ->format(function ($value) {
                    if ($value > Repuesto::UMBRAL_BAJO_STOCK) {
                        return '<span class="text-green-500">' . $value . '</span>';
                    }
                    return '<span class="text-red-500">' . $value . '</span>';
                })
                ->html()
                ->sortable()
                ->collapseOnTablet(),
            // El desglose. No ordenable a proposito: son varias filas de la
            // subtabla y no hay un criterio unico por el que ordenarlas.
            Column::make("Por sucursal")
                ->label(fn($row) => $row->desgloseStock())
                ->html()
                ->setCustomSlug('por-sucursal'),
            Column::make('Acciones', 'id')
                ->format(function ($value, $row, Column $column) {
                    return view('livewire.repuesto.actions-buttons', [
                        'row' => $row,
                        // El prefijo del permiso, para que el parcial no tenga
                        // que saber de que pantalla cuelga.
                        'permiso' => $this->permiso(),
                    ]);
                }),
        ]);
    }

    public function builder(): Builder
    {
        return Repuesto::query()
            // El desglose por sucursal de cada fila, en dos consultas para toda
            // la pagina en vez de dos por fila.
            ->with(['stocks.sucursal:id,nombre'])
            ->when((bool) $this->selectedMinStock, function ($query) {
                $query->bajoStock();
            })
            ->when(!empty($this->selectedModelos), function ($query) {
                $query->whereIn('producto_modelo_id', $this->selectedModelos);
            })
            ->when(!empty($this->selectedCategorias), function ($query) {
                $query->whereIn('repuesto_categoria_id', $this->selectedCategorias);
            })
            // Estricto: ver el comentario de RepuestoIndex::calculateTotals().
            ->where('repuestos.tipo', $this->tipo)
            ->orderby('repuestos.created_at', 'desc');
    }

    #[On('refreshRepuestoTable')]
    public function refreshRepuestoTable()
    {
        $this->builder();
    }

    public function openRepuestoEditModal($id)
    {
        $this->dispatch('openRepuestoEditModal', $id);
    }

    public function openRepuestoDestroyModal($id)
    {
        $this->dispatch('openRepuestoDestroyModal', $id);
    }

    public function openRepuestoTransferenciaModal($id)
    {
        $this->dispatch('openRepuestoTransferenciaModal', $id);
    }

    public function openRepuestoHistorial($id)
    {
        return redirect()->route('repuestos.historial', $id);
    }
}
