<?php

namespace App\Models;

use App\Enums\RepuestoTipo;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Repuesto extends Model
{
    use Auditable;

    protected $table = 'repuestos';

    /**
     * `cantidad` va en $guarded: es un total CACHEADO, espejo de la suma de
     * repuestos_sucursales, y lo escribe solo StockRepuestoService.
     *
     * Hace falta blindarlo aqui y no solo quitar el input del blade, porque
     * RepuestoEditModal hace `$repuesto->toArray()` al abrir y luego
     * `update($this->repuesto)`: la clave seguiria en el array aunque el campo
     * no este en el formulario, y se reescribiria igual. Los dos modales la
     * quitan del array ademas, pero esto es la red de abajo.
     *
     * Se descarta EN SILENCIO, como la trampa de $fillable: si algun dia un
     * stock no se guarda, este es el primer sitio donde mirar.
     *
     * No cubre los update() del query builder -- RepuestoTipoCambioMasivoModal
     * hace uno -- pero ese no toca cantidad.
     */
    protected $guarded = ['id', 'cantidad'];

    /**
     * `cantidad` tampoco entra en la bitacora: es el total cacheado que
     * recalcularTotales() reescribe DESPUES de cada compra, venta o reparacion,
     * asi que registrarlo llenaria el historial de la ficha con el eco de
     * movimientos que el UNION de RepuestoMovimiento ya cuenta, y mejor.
     *
     * Hoy no haria ruido igualmente -- el servicio escribe con SQL crudo y
     * ningun observer lo ve -- pero eso es una propiedad del servicio, no una
     * garantia, y el dia que alguien lo pase a Eloquent esto evita la avalancha.
     */
    protected $auditarExcluye = ['cantidad'];

    /** Umbral de "bajo stock", sobre el TOTAL. Estaba escrito tres veces. */
    public const UMBRAL_BAJO_STOCK = 9;

    public function modelo()
    {
        return $this->belongsTo(ProductoModelo::class, 'producto_modelo_id');
    }

    /** El stock repartido por sucursal: la unica verdad del inventario. */
    public function stocks()
    {
        return $this->hasMany(RepuestoSucursal::class, 'repuesto_id');
    }

    public function transferencias()
    {
        return $this->hasMany(RepuestoTransferencia::class, 'repuesto_id');
    }

    public function categoria()
    {
        return $this->belongsTo(RepuestoCategoria::class, 'repuesto_categoria_id');
    }

    /**
     * Solo piezas de reparacion. Existe como scope y no como where suelto
     * porque hay tres buscadores de reparacion copiados literalmente
     * (ProductoEstadoModal, ProductoReparacionClienteModal, ReparacionEditModal) y el
     * cuarto que alguien escriba debe poder acertar sin recordar el filtro.
     */
    public function scopeSoloRepuestos(Builder $query): Builder
    {
        return $query->where('tipo', RepuestoTipo::Repuesto->value);
    }

    /** Filtro opcional: un $tipo vacio o null no restringe nada. */
    public function scopeDeTipo(Builder $query, ?string $tipo): Builder
    {
        return $query->when($tipo, fn(Builder $q) => $q->where('tipo', $tipo));
    }

    /**
     * Articulos por debajo del umbral. Mira el TOTAL, no cada sucursal: es el
     * criterio que ya tenia el sistema y no cambia al repartir el stock.
     *
     * La columna va CUALIFICADA. Este scope se anida como subconsulta dentro de
     * los withSum/withCount del resumen por sucursal, y ahi el contexto exterior
     * es repuestos_sucursales, que TAMBIEN tiene una columna `cantidad`. MySQL
     * resuelve al FROM mas interno y acierta, pero un `cantidad` a secas en ese
     * sitio es la clase de ambiguedad que un dia se resuelve al revés y nadie
     * entiende por que el filtro de bajo stock empezo a contar otra cosa.
     */
    public function scopeBajoStock(Builder $query): Builder
    {
        return $query->where('repuestos.cantidad', '<=', self::UMBRAL_BAJO_STOCK);
    }

    /** Las unidades que hay en una sucursal concreta. */
    public function stockEn(?int $sucursalId): int
    {
        if ($sucursalId === null) {
            return 0;
        }

        // Si la relacion ya vino cargada no se vuelve a consultar: las tablas y
        // los selectores pintan esto por fila.
        if ($this->relationLoaded('stocks')) {
            return (int) ($this->stocks->firstWhere('sucursal_id', $sucursalId)?->cantidad ?? 0);
        }

        return (int) ($this->stocks()->where('sucursal_id', $sucursalId)->value('cantidad') ?? 0);
    }

    /**
     * El reparto del stock por sucursal, para la columna del catalogo.
     *
     * Clases literales y nada compuesto: no hay safelist en tailwind.config.js
     * (mismo motivo que getDivColor()). Las sucursales en cero no se pintan --
     * la celda interesa por donde SI hay unidades -- pero los NEGATIVOS si, en
     * rojo: hay dos articulos que nacieron en negativo del backfill y esconderlos
     * seria esconder justo lo que hay que corregir.
     *
     * Espera `stocks.sucursal` ya cargado (lo hace RepuestoTable::builder()); si
     * no lo esta, la relacion se consulta sola y son dos consultas por fila.
     */
    public function desgloseStock(): string
    {
        $partes = $this->stocks
            ->filter(fn($s) => (int) $s->cantidad !== 0)
            ->sortByDesc('cantidad')
            ->map(function ($s) {
                $cantidad = (int) $s->cantidad;
                $clase = $cantidad < 0 ? 'text-red-600 font-semibold' : 'font-semibold';

                return '<span class="whitespace-nowrap">' . e($s->sucursal?->nombre ?? 'Sin sucursal')
                    . ' <span class="' . $clase . '">' . $cantidad . '</span></span>';
            });

        if ($partes->isEmpty()) {
            return '<span class="text-gray-400">Sin stock</span>';
        }

        return '<div class="flex flex-col gap-0.5 text-xs">' . $partes->implode('') . '</div>';
    }

    /**
     * Si este articulo tiene los atributos de una pieza de reparacion.
     *
     * Atajo para las vistas que reciben el modelo y no el enum, para que no
     * repitan la resolucion del tipo. La decision vive en
     * RepuestoTipo::tieneCamposDeRepuesto(); aqui solo se resuelve el valor, con
     * el mismo fallback a Repuesto que usa getBadgeTipo().
     */
    public function tieneCamposDeRepuesto(): bool
    {
        return (RepuestoTipo::tryFrom($this->tipo ?? '') ?? RepuestoTipo::Repuesto)
            ->tieneCamposDeRepuesto();
    }

    /** Badge de tipo para las datatables. */
    public function getBadgeTipo(): string
    {
        $tipo = RepuestoTipo::tryFrom($this->tipo ?? '') ?? RepuestoTipo::Repuesto;

        return '<span class="px-2 py-1 text-xs font-medium rounded-full ' . $tipo->badgeClasses() . '">'
            . e($tipo->label()) . '</span>';
    }

    /**
     * Circulo de color para la datatable. Estilo inline obligatorio: no hay
     * safelist en tailwind.config.js. Mismo patron que Tecnicos::getDivColor().
     */
    public function getDivColor(): string
    {
        $style = $this->color_hex
            ? "background-color: {$this->color_hex};"
            : 'background-color: transparent;';

        return '<div class="w-6 h-6 rounded-full border border-gray-300 mx-auto" style="' . $style . '" title="' . e($this->color ?? 'Sin color') . '"></div>';
    }

    /**
     * Nombre y color en una sola celda: punto pintado + nombre del color entre
     * parentesis. Si el repuesto no tiene color se devuelve solo el nombre,
     * sin punto vacio ni parentesis huerfanos.
     */
    public function getNombreConColor(): string
    {
        $nombre = e($this->nombre);

        if (!$this->color && !$this->color_hex) {
            return $nombre;
        }

        $hex = $this->color_hex ?: 'transparent';
        $etiqueta = $this->color ?: $this->color_hex;

        return '<span class="inline-flex items-center gap-1.5">'
            . '<span class="inline-block w-3 h-3 rounded-full border border-gray-300 shrink-0" style="background-color: ' . e($hex) . ';"></span>'
            . '<span>' . $nombre . ' <span class="text-gray-500 dark:text-gray-400">(' . e($etiqueta) . ')</span></span>'
            . '</span>';
    }
}
