<?php

namespace App\Traits;

use App\Models\Bitacora;
use App\Models\Repuesto;
use App\Models\Sucursal;
use App\Services\StockRepuestoService;
use Illuminate\Support\Facades\DB;

/**
 * El stock por sucursal dentro del formulario de crear y editar un articulo.
 *
 * Vive en un trait porque RepuestoCreateModal y RepuestoEditModal son gemelos
 * literales -- el mismo motivo por el que ya existe RepuestoAccesorioTrait -- y
 * porque el total es una invariante: si las dos pantallas lo calcularan por su
 * cuenta, una de las dos se quedaria atras.
 *
 * El formulario trabaja con `$stockSucursales`, un array [sucursal_id => cantidad]
 * con una entrada por sucursal elegida. Al guardar se traduce a movimientos del
 * servicio, nunca a una escritura directa de `repuestos.cantidad`.
 *
 * Aplica igual a repuestos y a accesorios: `cantidad` no esta en
 * camposSoloRepuesto(), y un accesorio tambien tiene existencias.
 */
trait RepuestoStockSucursalTrait
{
    /** [sucursal_id => cantidad]. Una fila por sucursal cargada. */
    public array $stockSucursales = [];

    /** La sucursal que el usuario esta por agregar. */
    public $sucursalNueva = '';

    /** Las sucursales que todavia no estan en el formulario. */
    public function sucursalesDisponibles()
    {
        // Solo activas: agregar stock a una sucursal desactivada no tiene
        // sentido. sucursalesPorId() sigue con todas para pintar las filas viejas.
        return Sucursal::activas()->orderBy('nombre')
            ->get()
            ->reject(fn($s) => array_key_exists($s->id, $this->stockSucursales));
    }

    /** Catalogo para pintar los nombres de las filas ya cargadas. */
    public function sucursalesPorId()
    {
        return Sucursal::orderBy('nombre')->get()->keyBy('id');
    }

    /**
     * El total. Es la SUMA y no un campo editable: la subtabla es la unica
     * verdad, y un total escrito a mano no tendria filas que lo respalden.
     */
    public function totalStock(): int
    {
        return array_sum(array_map('intval', $this->stockSucursales));
    }

    public function agregarSucursalStock(): void
    {
        if ($this->sucursalNueva === '' || $this->sucursalNueva === null) {
            return;
        }

        $id = (int) $this->sucursalNueva;

        // Sin duplicados: la unique de (repuesto_id, sucursal_id) lo impediria
        // igual, pero mejor no llegar ahi.
        if (!array_key_exists($id, $this->stockSucursales)) {
            $this->stockSucursales[$id] = 0;
        }

        $this->sucursalNueva = '';
    }

    public function quitarSucursalStock($sucursalId): void
    {
        unset($this->stockSucursales[(int) $sucursalId]);
    }

    /** Carga el reparto actual de un articulo ya guardado. */
    protected function cargarStockSucursales(int $repuestoId): void
    {
        $this->stockSucursales = DB::table('repuestos_sucursales')
            ->where('repuesto_id', $repuestoId)
            ->pluck('cantidad', 'sucursal_id')
            ->map(fn($c) => (int) $c)
            ->all();
    }

    /**
     * Lleva el stock de cada sucursal al valor del formulario, por diferencia.
     *
     * Se usa ajustarEntrada y no una escritura directa para que el camino sea el
     * mismo que el de compras, ventas y reparaciones: un solo sitio escribe
     * stock. Una sucursal que se quito del formulario se lleva a cero, lo que
     * puede fallar si el valor guardado era negativo -- y eso es correcto, no
     * hay nada que retirar.
     *
     * Debe llamarse DENTRO de una transaccion del llamador.
     */
    protected function guardarStockSucursales(int $repuestoId): void
    {
        $stock = new StockRepuestoService();

        $actual = DB::table('repuestos_sucursales')
            ->where('repuesto_id', $repuestoId)
            ->pluck('cantidad', 'sucursal_id')
            ->map(fn($c) => (int) $c)
            ->all();

        // Las sucursales del formulario, mas las que estaban y se quitaron (que
        // van a cero). Ordenadas para no bloquear en InnoDB.
        $ids = array_unique(array_merge(array_keys($actual), array_keys($this->stockSucursales)));
        sort($ids);

        $movidas = [];

        foreach ($ids as $sucursalId) {
            $antes = $actual[$sucursalId] ?? 0;
            $ahora = (int) ($this->stockSucursales[$sucursalId] ?? 0);

            $stock->ajustarEntrada($repuestoId, (int) $sucursalId, $antes, $ahora);

            if ($antes !== $ahora) {
                $movidas[(int) $sucursalId] = [$antes, $ahora];
            }
        }

        $stock->recalcularTotales();

        $this->registrarAjusteDeStock($repuestoId, $movidas, esAlta: $actual === []);
    }

    /**
     * El ajuste a mano, en la bitacora del articulo.
     *
     * Tiene que escribirse aqui, a mano: StockRepuestoService mueve el stock con
     * SQL crudo (DB::statement / DB::update) y ningun observer se entera. Era el
     * hueco que confesaba el banner del historial de repuestos -- "el stock
     * tambien cambia al editarlo a mano desde la ficha" -- y por el que el
     * balance calculado no cuadraba sin que hubiera forma de saber por que.
     *
     * Una fila por guardado, con TODAS las sucursales que cambiaron como pares
     * [antes, despues] por nombre: la misma forma que un diff del observer, asi
     * que la pinta el mismo componente.
     */
    protected function registrarAjusteDeStock(int $repuestoId, array $movidas, bool $esAlta): void
    {
        if ($movidas === []) {
            return;
        }

        $nombres = Sucursal::whereIn('id', array_keys($movidas))->pluck('nombre', 'id');

        $cambios = [];
        foreach ($movidas as $sucursalId => $par) {
            $cambios[$nombres[$sucursalId] ?? "Sucursal #{$sucursalId}"] = $par;
        }

        $neto = array_sum(array_map(fn($par) => $par[1] - $par[0], $movidas));

        Bitacora::registrar(
            Repuesto::findOrFail($repuestoId),
            'stock',
            $esAlta
                ? 'Stock inicial cargado desde la ficha'
                : 'Stock ajustado a mano desde la ficha (neto ' . ($neto >= 0 ? '+' : '') . $neto . ')',
            // El enlace a la sucursal solo cuando es una: con varias, el detalle
            // esta en `cambios` y elegir una de ellas mentiria.
            count($movidas) === 1 ? ['sucursal_id' => array_key_first($movidas)] : [],
            $cambios,
        );
    }

    /** Reglas del bloque de stock, para mezclar con las del formulario. */
    protected function reglasDeStock(): array
    {
        return [
            'stockSucursales' => 'array',
            'stockSucursales.*' => 'required|integer|min:0',
        ];
    }
}
