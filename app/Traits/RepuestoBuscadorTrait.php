<?php

namespace App\Traits;

use App\Models\Repuesto;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

/**
 * El buscador de articulos de las cuatro pantallas de compra y venta de
 * repuestos.
 *
 * updatedSearchRepuesto(), openRepuestoSelector() y agregarRepuestos() estaban
 * copiados CUATRO veces cada uno -- doce metodos identicos -- entre
 * CompraRepuestoCreate, CompraRepuestoEdit, VentaRepuestoCreate y
 * VentaRepuestoEdit, y las copias ya habian empezado a divergir: los dos Create
 * NO tenian la guarda de termino vacio, asi que borrar la caja disparaba un
 * `like '%%'` y listaba los diez primeros articulos del catalogo como si fueran
 * resultados de la busqueda. Al unificar se queda la version con guarda.
 *
 * selectRepuesto() NO sube aqui a proposito: Create promedia el tipo de cambio
 * y Edit marca 'id' => null, y el docblock de RepuestoSelectorModal ya explica
 * que son dos dialectos distintos de la misma linea.
 */
trait RepuestoBuscadorTrait
{
    public $searchRepuesto = '';
    public $filteredRepuestos = [];

    /**
     * La sucursal del documento que se esta cargando.
     *
     * La implementa cada componente porque la cabecera se llama distinto en
     * cada uno ($compraRepuesto / $ventaRepuesto). Es la que decide QUE stock se
     * muestra: el total global no dice nada de la tienda donde se vende, y
     * mostrarlo era peor que no mostrar nada -- el vendedor armaba la venta
     * entera leyendo 12 y la validacion la rechazaba al confirmar.
     */
    abstract public function sucursalDelDocumento(): ?int;

    /**
     * Si un articulo sin stock en esa sucursal se puede elegir o no.
     *
     * En una venta no, porque no hay nada que vender. En una compra si, porque
     * es justamente lo que va a entrar ahi. Por defecto no exige, y las dos
     * pantallas de venta lo sobreescriben.
     */
    public function exigeStockParaElegir(): bool
    {
        return false;
    }

    /**
     * Si ya se puede elegir articulos. Sin sucursal no hay stock del que hablar:
     * ni que mostrar en el desplegable, ni de donde sacar o a donde meter las
     * unidades.
     */
    public function puedeElegirArticulos(): bool
    {
        return $this->sucursalDelDocumento() !== null;
    }

    /**
     * Por que todavia no se puede elegir, en las palabras de cada pantalla
     * (de donde sale la venta / a donde entra la compra). Las cuatro lo
     * sobreescriben; este texto generico es solo el respaldo.
     *
     * Lo lee el aviso del blade Y el toast del servidor, para que el usuario no
     * lea dos redacciones distintas de la misma regla.
     */
    public function motivoSinSucursal(): string
    {
        return 'Elige primero la sucursal del documento.';
    }

    public function updatedSearchRepuesto($value)
    {
        // Antes que nada: sin sucursal el desplegable listaba articulos con su
        // stock en blanco, y se podia armar media venta antes de que
        // selectRepuesto() avisara. El input ya sale deshabilitado, pero esto es
        // lo que lo sostiene -- un disabled solo esconde el control.
        if (!$this->puedeElegirArticulos()) {
            $this->filteredRepuestos = [];
            return;
        }

        if (empty($value)) {
            $this->filteredRepuestos = [];
            return;
        }

        $idsExistentes = collect($this->detalles)->pluck('repuesto_id')->toArray();
        $sucursalId = $this->sucursalDelDocumento();

        $this->filteredRepuestos = Repuesto::where('nombre', 'like', '%' . $value . '%')
            ->whereNotIn('id', $idsExistentes)
            // El stock de la sucursal cargado de una vez: stockEn() mira
            // relationLoaded(), asi que sin esto el desplegable hace una
            // consulta por cada uno de los diez resultados.
            ->when($sucursalId, fn($q) => $q->with([
                'stocks' => fn($s) => $s->where('sucursal_id', $sucursalId),
            ]))
            ->orderBy('nombre')
            ->take(10)
            ->get();
    }

    /**
     * [repuesto_id => unidades en la sucursal del documento], para la columna
     * "Stock aqui" de la tabla de lineas.
     *
     * Una consulta para todas las lineas, y NO una clave dentro de $detalles:
     * ahi viajaria en el payload de Livewire en cada request y acabaria en los
     * arrays que se insertan. Antes habia justo eso -- 'cantidad_actual' y
     * 'stock_sucursal' -- calculado y que ningun blade leia.
     */
    public function stocksDeLasLineas(): array
    {
        $sucursalId = $this->sucursalDelDocumento();
        $ids = collect($this->detalles)->pluck('repuesto_id')->filter()->unique()->values()->all();

        if ($sucursalId === null || $ids === []) {
            return [];
        }

        return DB::table('repuestos_sucursales')
            ->where('sucursal_id', $sucursalId)
            ->whereIn('repuesto_id', $ids)
            ->pluck('cantidad', 'repuesto_id')
            ->map(fn($c) => (int) $c)
            ->all();
    }

    public function openRepuestoSelector(): void
    {
        // Sin esto el catalogo se abria con sucursalId: null, y el modal solo
        // aplica soloConStock CUANDO HAY sucursal: abrirlo sin ella desactivaba
        // en silencio el filtro de stock, que es el peor caso de todos.
        if (!$this->puedeElegirArticulos()) {
            toastr()->warning($this->motivoSinSucursal());
            return;
        }

        $this->dispatch(
            'openRepuestoSelectorModal',
            excluidos: collect($this->detalles)->pluck('repuesto_id')->map(fn($id) => (int) $id)->values()->all(),
            sucursalId: $this->sucursalDelDocumento(),
            soloConStock: $this->exigeStockParaElegir(),
        );
    }

    #[On('repuestosSeleccionados')]
    public function agregarRepuestos(array $ids): void
    {
        foreach ($ids as $id) {
            $this->selectRepuesto($id);
        }
    }
}
