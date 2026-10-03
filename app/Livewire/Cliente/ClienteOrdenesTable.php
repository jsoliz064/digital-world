<?php

namespace App\Livewire\Cliente;

use App\Models\Cliente;
use App\Models\ClienteOrden;
use App\Models\Sucursal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\DateFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectDropdownFilter;

class ClienteOrdenesTable extends DataTableComponent
{
    protected $model = ClienteOrden::class;

    public $cliente;

    /** Memo por request de la fila de totales. */
    protected ?object $totales = null;

    public function mount($cliente_id): void
    {
        $this->cliente = Cliente::findOrFail($cliente_id);
    }

    public function configure(): void
    {
        // Nombre propio: el default del paquete es 'table' para TODAS las tablas
        // (query string, id del DOM y evento Alpine de filas colapsadas).
        $this->setTableName('ordenes');

        $this->setPrimaryKey('id')
            ->setDefaultSort('fecha', 'desc')
            ->setSearchPlaceholder('Buscar por nº de documento, vendedor o sucursal...')
            ->setEmptyMessage('Este cliente todavía no tiene ninguna orden registrada.');

        // El paquete solo SELECTea los campos de las columnas declaradas, pero
        // executeQuery() hace pluck('id') para los wire:key, y los format() leen
        // tipo, referencia_id y total_repuestos.
        $this->setAdditionalSelects([
            'cliente_ordenes.id',
            'cliente_ordenes.tipo',
            'cliente_ordenes.referencia_id',
            'cliente_ordenes.total_repuestos',
        ]);

        $this->setFooterTrAttributes(fn($rows) => [
            'default' => false,
            'class' => 'bg-gray-100 dark:bg-gray-900 font-bold text-gray-900 dark:text-white',
        ]);
    }

    /**
     * Query base compartida por builder() y getTotales().
     *
     * TODO filtro y TODA busqueda se aplican AQUI, nunca con ->filter() en
     * filters() ni con ->searchable() en columns(): applyFilters() y applySearch()
     * tocan solo el builder de las filas, no la query agregada del footer, y los
     * totales quedarian descuadrados respecto a lo que se ve.
     */
    protected function scopedQuery(): Builder
    {
        $query = ClienteOrden::paraCliente($this->cliente->id);

        // getAppliedFilterWithValue() devuelve el valor CRUDO del query string, asi
        // que la whitelist se hace a mano.
        $tipos = array_values(array_intersect(
            (array) ($this->getAppliedFilterWithValue('tipo') ?? []),
            ['Productos', 'Repuestos'],
        ));

        if ($tipos !== []) {
            $query->whereIn('cliente_ordenes.tipo', $tipos);
        }

        // Por NOMBRE y no por id: es lo que expone el UNION.
        $sucursales = array_values(array_intersect(
            (array) ($this->getAppliedFilterWithValue('sucursal') ?? []),
            Sucursal::pluck('nombre')->all(),
        ));

        if ($sucursales !== []) {
            $query->whereIn('cliente_ordenes.sucursal', $sucursales);
        }

        if ($desde = $this->fechaFiltrada('fecha_desde')) {
            $query->whereDate('cliente_ordenes.fecha', '>=', $desde);
        }

        if ($hasta = $this->fechaFiltrada('fecha_hasta')) {
            $query->whereDate('cliente_ordenes.fecha', '<=', $hasta);
        }

        // Ninguna columna lleva ->searchable() a proposito: con 0 columnas
        // searchable applySearch() es un no-op, y la caja se sigue mostrando.
        $search = trim((string) $this->search);

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('cliente_ordenes.referencia_id', 'like', $like)
                    ->orWhere('cliente_ordenes.tipo', 'like', $like)
                    ->orWhere('cliente_ordenes.usuario', 'like', $like)
                    ->orWhere('cliente_ordenes.sucursal', 'like', $like);
            });
        }

        return $query;
    }

    protected function fechaFiltrada(string $key): ?string
    {
        $valor = $this->getAppliedFilterWithValue($key);

        if (!is_string($valor) || $valor === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $valor)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function builder(): Builder
    {
        return $this->scopedQuery();
    }

    /**
     * Totales de TODAS las filas filtradas, no solo de la pagina visible: el
     * footer nativo recibe $this->getRows, que es el paginador de la pagina actual.
     */
    protected function getTotales(): object
    {
        return $this->totales ??= $this->scopedQuery()
            ->toBase()
            ->selectRaw('
                COUNT(*) as ordenes,
                COALESCE(SUM(unidades), 0) as unidades,
                COALESCE(SUM(total), 0) as total,
                COALESCE(SUM(total_repuestos), 0) as total_repuestos,
                COALESCE(SUM(total_bs), 0) as total_bs
            ')
            ->first();
    }

    public function filters(): array
    {
        // Sin ->filter(): applyFilters() ignora los filtros sin callback y los
        // aplicamos en scopedQuery() para que el footer cuadre.
        return [
            MultiSelectDropdownFilter::make('Tipo', 'tipo')
                ->options(['Productos' => 'Teléfonos', 'Repuestos' => 'Repuestos y accesorios']),

            MultiSelectDropdownFilter::make('Sucursal', 'sucursal')
                ->options(Sucursal::orderBy('nombre')->pluck('nombre', 'nombre')->toArray()),

            // DateFilter y no DateRangeFilter: este ultimo necesita flatpickr y el
            // layout no carga los assets del paquete.
            DateFilter::make('Desde', 'fecha_desde'),
            DateFilter::make('Hasta', 'fecha_hasta'),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('Fecha', 'fecha')
                // Callback y no sortable() a secas: sin desempate por id, dos
                // ordenes con la misma fecha pueden saltar de pagina.
                ->sortable(fn(Builder $query, string $direction) => $query
                    ->orderBy('cliente_ordenes.fecha', $direction)
                    ->orderBy('cliente_ordenes.id', $direction))
                ->format(fn($value) => Carbon::parse($value)->format('d/m/Y H:i'))
                ->footer(fn($rows) => 'TOTALES'),

            Column::make('Documento', 'referencia_id')
                ->sortable()
                ->format(function ($value, $row) {
                    $etiqueta = $row->tipo === 'Productos' ? 'Venta' : 'Repuestos';

                    return '<a href="' . $row->rutaDetalle() . '" '
                        . 'class="font-semibold text-brand-600 hover:underline dark:text-brand-400">'
                        . e($etiqueta) . ' #' . (int) $value . '</a>';
                })
                ->html()
                ->footer(fn($rows) => (int) $this->getTotales()->ordenes . ' orden(es)'),

            Column::make('Tipo', 'tipo')
                ->sortable()
                ->format(function ($value) {
                    // Clases literales en las dos ramas: no hay safelist.
                    $clases = $value === 'Productos'
                        ? 'bg-blue-100 text-blue-800'
                        : 'bg-purple-100 text-purple-800';

                    $texto = $value === 'Productos' ? 'Teléfonos' : 'Repuestos';

                    return '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold '
                        . $clases . '">' . e($texto) . '</span>';
                })
                ->html(),

            Column::make('Unidades', 'unidades')
                ->sortable()
                ->footer(fn($rows) => (int) $this->getTotales()->unidades),

            Column::make('Total ($)', 'total')
                ->sortable()
                ->format(fn($value) => '$ ' . number_format((float) $value, 2))
                ->footer(fn($rows) => '$ ' . number_format((float) $this->getTotales()->total, 2)),

            // Lo cobrado en piezas montadas en el equipo. En blanco cuando es
            // cero, que es lo normal: asi la columna solo canta las ordenes que
            // llevaron repuestos encima del telefono.
            Column::make('Repuestos ($)', 'total_repuestos')
                ->sortable()
                ->format(function ($value) {
                    if ((float) $value < 0.01) {
                        return '<span class="text-gray-300">—</span>';
                    }

                    return '<span class="font-semibold text-green-700 dark:text-green-400">+ $ '
                        . number_format((float) $value, 2) . '</span>';
                })
                ->html()
                ->footer(fn($rows) => '$ ' . number_format((float) $this->getTotales()->total_repuestos, 2)),

            Column::make('Total (Bs)', 'total_bs')
                ->sortable()
                ->format(fn($value) => 'Bs. ' . number_format((float) $value, 2))
                ->footer(fn($rows) => 'Bs. ' . number_format((float) $this->getTotales()->total_bs, 2))
                ->collapseOnTablet(),

            Column::make('Vendedor', 'usuario')
                ->sortable()
                ->format(fn($value) => $value ?: '—')
                ->collapseOnTablet(),

            Column::make('Sucursal', 'sucursal')
                ->sortable()
                ->format(fn($value) => $value ?: '—')
                ->collapseOnTablet(),
        ];
    }
}
