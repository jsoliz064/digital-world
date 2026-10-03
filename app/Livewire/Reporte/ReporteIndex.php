<?php

namespace App\Livewire\Reporte;

use App\Enums\RepuestoTipo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Reporte por linea de negocio.
 *
 * La pantalla tiene DOS niveles y esa es toda su estructura:
 *
 *  1. Un resumen general FIJO con el total del negocio. No depende de la
 *     pestana, y ahi vive la mano de obra, que no pertenece a ningun articulo.
 *  2. Tres pestanas simetricas -- Celulares, Repuestos, Accesorios -- donde
 *     cada una responde lo mismo sobre su linea: que se vendio, que se compro
 *     y cuanto se gano.
 *
 * Antes las pestanas mezclaban dos criterios distintos (Ventas y Compras
 * miraban TODO el negocio mientras Repuestos y Accesorios miraban una linea),
 * asi que "Ventas" y "Repuestos" no eran comparables entre si aunque
 * estuvieran una al lado de la otra. Ahora las tres cortan por el mismo eje.
 */
class ReporteIndex extends Component
{
    use WithPagination;

    public const TABS = [
        'celulares' => 'Celulares',
        'repuestos' => 'Repuestos',
        'accesorios' => 'Accesorios',
    ];

    /** Umbral a partir del cual agrupar por dia deja de ser legible. */
    private const DIAS_AVISO_AGRUPACION = 90;

    /**
     * Ganancia de celulares.
     *
     * El fallback del 20% NO es un adorno heredado: hoy 10 de las 12 lineas de
     * `ventas_productos` tienen `costo = 0` (hueco de captura). Sin el, esas
     * ventas se reportarian con coste cero y la ganancia de celulares saltaria
     * de ~4.020 a ~19.816, fingiendo un margen del 92%.
     *
     * Los repuestos NO lo necesitan, y NO hay que anadirselo. Ahi el unico
     * caso de `subtotal_costo = 0` son las lineas cobradas junto a un telefono
     * (las que llevan `producto_reparacion_repuesto_id`), y ese cero es
     * deliberado: el costo de esa pieza ya viaja dentro de
     * `ventas_productos.costo` del equipo. Inventarle un costo aqui lo
     * restaria dos veces.
     */
    private const GANANCIA_CELULARES =
        'SUM(CASE WHEN vp.costo = 0 THEN vp.subtotal - vp.subtotal * 0.8 ELSE vp.subtotal - vp.costo END)';

    /** Ingreso neto de repuestos por periodo: bruto menos descuento prorrateado. */
    private const INGRESO_NETO = 'SUM(d.subtotal) - SUM(d.subtotal / NULLIF(v.subtotal, 0) * v.descuento)';

    public $startDate;
    public $endDate;
    public $reportType = 'daily';
    public $chartMetric = 'totales';

    /** Linea de negocio activa. Unico mando de la pantalla. */
    public $tab = 'celulares';

    /** Se construye entero en loadChartData(). */
    public $chartData = ['labels' => [], 'datasets' => []];

    public function mount()
    {
        $this->endDate = Carbon::today()->format('Y-m-d');
        $this->startDate = Carbon::today()->startOfMonth()->format('Y-m-d');
        $this->loadChartData();
    }

    /**
     * Se precalcula TODO aqui y se pasa a la vista en un array. El blade solia
     * llamar $this->getXxx() unas 16 veces por render, cada una con sus
     * consultas; con pestanas y tarjetas nuevas eso se multiplicaba.
     */
    public function render()
    {
        return view('livewire.reporte.reporte-index', [
            'general' => $this->resumenGeneral(),
            'resumen' => $this->resumenPestana(),
            'tablaData' => $this->tablaData(),
            'avisoAgrupacion' => $this->avisoAgrupacion(),
        ]);
    }

    public function seleccionarTab(string $tab): void
    {
        if (!array_key_exists($tab, self::TABS)) {
            return;
        }

        $this->tab = $tab;
        $this->resetPage();
        $this->loadChartData();
    }

    public function updatedStartDate($value)
    {
        $this->resetPage();
        if (strtotime($value) > strtotime($this->endDate)) {
            session()->flash('date_error', 'La fecha de inicio no puede ser mayor que la fecha de fin');
            return;
        }
        $this->validateDates();
        $this->loadChartData();
    }

    public function updatedEndDate()
    {
        $this->resetPage();
        $this->validateDates();
        $this->loadChartData();
    }

    public function updatedReportType()
    {
        $this->resetPage();
        $this->loadChartData();
    }

    public function updatedChartMetric()
    {
        $this->loadChartData();
    }

    public function validateDates()
    {
        try {
            $this->validate([
                'startDate' => [
                    'required',
                    'date_format:Y-m-d',
                    'before_or_equal:endDate',
                    'after_or_equal:2024-01-01',
                    'before_or_equal:today'
                ],
                'endDate' => [
                    'required',
                    'date_format:Y-m-d',
                    'after_or_equal:startDate',
                    'after_or_equal:2024-01-01',
                    'before_or_equal:today'
                ]
            ], [
                // Una sola entrada por regla: antes habia dos `before_or_equal`
                // de startDate y dos `after_or_equal` de endDate, y la segunda
                // pisaba a la primera en silencio.
                'startDate.required' => 'La fecha de inicio es obligatoria',
                'startDate.date_format' => 'El formato de la fecha de inicio es inválido (AAAA-MM-DD)',
                'startDate.before_or_equal' => 'La fecha de inicio no puede ser futura ni posterior a la fecha final',
                'startDate.after_or_equal' => 'La fecha de inicio no puede ser anterior al 01/01/2024',

                'endDate.required' => 'La fecha final es obligatoria',
                'endDate.date_format' => 'El formato de la fecha final es inválido (AAAA-MM-DD)',
                'endDate.after_or_equal' => 'La fecha final debe ser posterior a la de inicio y no anterior al 01/01/2024',
                'endDate.before_or_equal' => 'La fecha final no puede ser futura'
            ], [
                'startDate' => 'fecha de inicio',
                'endDate' => 'fecha final'
            ]);

            if (!checkdate(
                (int)Carbon::parse($this->startDate)->format('m'),
                (int)Carbon::parse($this->startDate)->format('d'),
                (int)Carbon::parse($this->startDate)->format('Y')
            )) {
                throw ValidationException::withMessages([
                    'startDate' => 'La fecha de inicio no es una fecha válida'
                ]);
            }

            if (!checkdate(
                (int)Carbon::parse($this->endDate)->format('m'),
                (int)Carbon::parse($this->endDate)->format('d'),
                (int)Carbon::parse($this->endDate)->format('Y')
            )) {
                throw ValidationException::withMessages([
                    'endDate' => 'La fecha final no es una fecha válida'
                ]);
            }

            session()->forget(['date_error', 'date_info']);

            if (Carbon::parse($this->startDate)->greaterThan(Carbon::parse($this->endDate))) {
                $tempDate = $this->startDate;
                $this->startDate = $this->endDate;
                $this->endDate = $tempDate;
                session()->flash('date_info', 'Las fechas se ajustaron automáticamente para mostrar el rango correcto');
            }
        } catch (ValidationException $e) {
            session()->flash('date_error', 'Por favor ingrese fechas válidas');
            throw $e;
        }
    }

    // ==================================================================
    // Rango y utilidades comunes
    // ==================================================================

    private function rangoActual(): array
    {
        return [
            Carbon::parse($this->startDate)->startOfDay(),
            Carbon::parse($this->endDate)->endOfDay(),
        ];
    }

    private function periodoAnterior(): array
    {
        $largo = $this->getPeriodLength();

        return [
            Carbon::parse($this->startDate)->subDays($largo)->startOfDay(),
            Carbon::parse($this->endDate)->subDays($largo)->endOfDay(),
        ];
    }

    private function getPeriodLength(): int
    {
        return Carbon::parse($this->startDate)->diffInDays(Carbon::parse($this->endDate)) + 1;
    }

    private function variacion(float $actual, float $anterior): float
    {
        return $anterior != 0 ? round(($actual - $anterior) / $anterior * 100, 2) : 0;
    }

    /** Division protegida: un periodo sin ventas no debe reventar el reporte. */
    private function ratio(float $numerador, float $denominador): float
    {
        return $denominador != 0 ? round($numerador / $denominador, 2) : 0;
    }

    private function esCelulares(): bool
    {
        return $this->tab === 'celulares';
    }

    /** Tipo de articulo que impone la pestana; null en Celulares. */
    private function tipoTab(): ?string
    {
        return match ($this->tab) {
            'repuestos' => RepuestoTipo::Repuesto->value,
            'accesorios' => RepuestoTipo::Accesorio->value,
            default => null,
        };
    }

    private function avisoAgrupacion(): ?string
    {
        if ($this->reportType === 'daily' && $this->getPeriodLength() > self::DIAS_AVISO_AGRUPACION) {
            return 'El rango abarca ' . $this->getPeriodLength() . ' días. Agrupa por mes para que el gráfico sea legible.';
        }

        return null;
    }

    // ==================================================================
    // Agregados
    // ==================================================================

    /**
     * Ventas de celulares. Se filtra por `ventas.created_at` (la fecha de la
     * venta); antes unas cifras usaban esa y otras `ventas_productos.created_at`.
     */
    private function agregadoVentasCelulares($desde, $hasta): object
    {
        $fila = DB::table('ventas_productos as vp')
            ->join('ventas as v', 'v.id', '=', 'vp.venta_id')
            ->whereBetween('v.created_at', [$desde, $hasta])
            ->selectRaw('
                COALESCE(SUM(vp.subtotal), 0) AS ingreso,
                COALESCE(' . self::GANANCIA_CELULARES . ', 0) AS ganancia,
                COUNT(vp.id) AS unidades,
                COUNT(DISTINCT v.id) AS operaciones')
            ->first();

        $ingreso = round((float) $fila->ingreso, 2);
        $ganancia = round((float) $fila->ganancia, 2);

        return (object) [
            'ingreso' => $ingreso,
            // El costo se deriva de la ganancia, no al reves: con el fallback
            // del 20% el "costo" de una venta sin costo capturado es estimado.
            'costo' => round($ingreso - $ganancia, 2),
            'unidades' => (int) $fila->unidades,
            'operaciones' => (int) $fila->operaciones,
            'ganancia' => $ganancia,
        ];
    }

    private function agregadoComprasCelulares($desde, $hasta): object
    {
        $fila = DB::table('compras')
            ->whereBetween('fecha_compra', [$desde, $hasta])
            ->selectRaw('
                COALESCE(SUM(costo_total), 0) AS costo,
                COALESCE(SUM(cantidad_total), 0) AS unidades,
                COUNT(*) AS operaciones')
            ->first();

        return (object) [
            'costo' => round((float) $fila->costo, 2),
            'unidades' => (int) $fila->unidades,
            'operaciones' => (int) $fila->operaciones,
        ];
    }

    /**
     * Ventas de repuestos/accesorios por tipo, con el descuento de cabecera
     * prorrateado. Se lee `d.tipo` (congelado en la linea) y no el del catalogo,
     * para que reclasificar un articulo no reescriba el pasado.
     */
    private function agregadoVentasRepuestos($desde, $hasta, ?string $tipo = null): object
    {
        $fila = DB::table('ventas_repuestos_detalles as d')
            ->join('ventas_repuestos as v', 'v.id', '=', 'd.venta_repuesto_id')
            ->whereBetween('v.created_at', [$desde, $hasta])
            ->when($tipo, fn($q) => $q->where('d.tipo', $tipo))
            ->selectRaw('
                COALESCE(SUM(d.subtotal), 0) AS bruto,
                COALESCE(SUM(d.subtotal / NULLIF(v.subtotal, 0) * v.descuento), 0) AS descuento,
                COALESCE(SUM(d.subtotal_costo), 0) AS costo,
                COALESCE(SUM(d.cantidad), 0) AS unidades,
                COUNT(DISTINCT v.id) AS operaciones')
            ->first();

        $ingreso = round((float) $fila->bruto - (float) $fila->descuento, 2);
        $costo = round((float) $fila->costo, 2);

        return (object) [
            'ingreso' => $ingreso,
            'costo' => $costo,
            'unidades' => (int) $fila->unidades,
            'operaciones' => (int) $fila->operaciones,
            'ganancia' => round($ingreso - $costo, 2),
        ];
    }

    private function agregadoComprasRepuestos($desde, $hasta, ?string $tipo = null): object
    {
        $fila = DB::table('compras_repuestos_detalles as d')
            ->join('compras_repuestos as c', 'c.id', '=', 'd.compra_repuesto_id')
            ->whereBetween('c.fecha_compra', [$desde, $hasta])
            ->when($tipo, fn($q) => $q->where('d.tipo', $tipo))
            ->selectRaw('
                COALESCE(SUM(d.subtotal), 0) AS costo,
                COALESCE(SUM(d.cantidad), 0) AS unidades,
                COUNT(DISTINCT c.id) AS operaciones')
            ->first();

        return (object) [
            'costo' => round((float) $fila->costo, 2),
            'unidades' => (int) $fila->unidades,
            'operaciones' => (int) $fila->operaciones,
        ];
    }

    /**
     * Mano de obra del periodo. No pertenece a ningun articulo, asi que no se
     * reparte entre lineas ni cabe en ninguna pestana: es la razon de que el
     * resumen general exista. Suma al ingreso Y al costo, por lo que se cancela
     * en la ganancia y el total sigue cuadrando con las cabeceras.
     */
    private function manoObra($desde, $hasta): float
    {
        return round((float) DB::table('ventas_repuestos')
            ->whereBetween('created_at', [$desde, $hasta])
            ->sum('mano_obra'), 2);
    }

    // ==================================================================
    // Resumen general: todo el negocio, independiente de la pestana
    // ==================================================================

    private function resumenGeneral(): array
    {
        [$desde, $hasta] = $this->rangoActual();
        [$desdeAnt, $hastaAnt] = $this->periodoAnterior();

        $act = $this->totalesNegocio($desde, $hasta);
        $ant = $this->totalesNegocio($desdeAnt, $hastaAnt);

        return array_merge($act, [
            'margen' => $this->ratio($act['ganancia'] * 100, $act['ingreso']),
            'ticket' => $this->ratio($act['ingreso'], $act['operaciones']),
            'tendenciaIngreso' => $this->variacion($act['ingreso'], $ant['ingreso']),
            'tendenciaGanancia' => $this->variacion($act['ganancia'], $ant['ganancia']),
            'tendenciaInversion' => $this->variacion($act['inversion'], $ant['inversion']),
        ]);
    }

    /** @return array{ingreso:float,ganancia:float,inversion:float,manoObra:float,unidades:int,operaciones:int,desglose:array} */
    private function totalesNegocio($desde, $hasta): array
    {
        $cel = $this->agregadoVentasCelulares($desde, $hasta);
        $rep = $this->agregadoVentasRepuestos($desde, $hasta, RepuestoTipo::Repuesto->value);
        $acc = $this->agregadoVentasRepuestos($desde, $hasta, RepuestoTipo::Accesorio->value);
        $mo = $this->manoObra($desde, $hasta);

        $cCel = $this->agregadoComprasCelulares($desde, $hasta);
        $cRep = $this->agregadoComprasRepuestos($desde, $hasta, RepuestoTipo::Repuesto->value);
        $cAcc = $this->agregadoComprasRepuestos($desde, $hasta, RepuestoTipo::Accesorio->value);

        // Operaciones de repuestos: una venta puede llevar repuestos Y
        // accesorios, asi que sumar las de cada tipo la contaria dos veces.
        //
        // Las enlazadas a una venta de telefono se excluyen: no son una
        // operacion aparte, son la misma venta al mismo cliente, ya contada en
        // las de celulares. Sin esto el ticket promedio baja solo.
        $opsRepuestos = (int) DB::table('ventas_repuestos')
            ->whereBetween('created_at', [$desde, $hasta])
            ->whereNull('venta_id')
            ->count();

        return [
            'ingreso' => round($cel->ingreso + $rep->ingreso + $acc->ingreso + $mo, 2),
            // La mano de obra se cancela: entra en el ingreso y en el costo.
            'ganancia' => round($cel->ganancia + $rep->ganancia + $acc->ganancia, 2),
            'inversion' => round($cCel->costo + $cRep->costo + $cAcc->costo, 2),
            'manoObra' => $mo,
            'unidades' => $cel->unidades + $rep->unidades + $acc->unidades,
            'unidadesCompradas' => $cCel->unidades + $cRep->unidades + $cAcc->unidades,
            'operaciones' => $cel->operaciones + $opsRepuestos,
            'desglose' => [
                ['etiqueta' => 'Celulares', 'monto' => $cel->ingreso],
                ['etiqueta' => 'Repuestos', 'monto' => $rep->ingreso],
                ['etiqueta' => 'Accesorios', 'monto' => $acc->ingreso],
                ['etiqueta' => 'Mano de obra', 'monto' => $mo],
            ],
        ];
    }

    // ==================================================================
    // Resumen de la pestana activa: una linea de negocio
    // ==================================================================

    /**
     * Las tres pestanas devuelven EXACTAMENTE las mismas claves. Es lo que
     * permite que el blade tenga un unico bloque de tarjetas en vez de uno por
     * pestana, y lo que hace que las cifras sean comparables entre lineas.
     */
    private function resumenPestana(): array
    {
        [$desde, $hasta] = $this->rangoActual();
        [$desdeAnt, $hastaAnt] = $this->periodoAnterior();

        $etiqueta = self::TABS[$this->tab];

        if ($this->esCelulares()) {
            $ventas = $this->agregadoVentasCelulares($desde, $hasta);
            $ventasAnt = $this->agregadoVentasCelulares($desdeAnt, $hastaAnt);
            $compras = $this->agregadoComprasCelulares($desde, $hasta);
            $comprasAnt = $this->agregadoComprasCelulares($desdeAnt, $hastaAnt);
            $descripcion = 'Solo celulares: lo vendido, lo comprado y la ganancia del período. La mano de obra y las demás líneas están en el resumen de arriba.';
        } else {
            $tipo = $this->tipoTab();
            $ventas = $this->agregadoVentasRepuestos($desde, $hasta, $tipo);
            $ventasAnt = $this->agregadoVentasRepuestos($desdeAnt, $hastaAnt, $tipo);
            $compras = $this->agregadoComprasRepuestos($desde, $hasta, $tipo);
            $comprasAnt = $this->agregadoComprasRepuestos($desdeAnt, $hastaAnt, $tipo);
            $descripcion = 'Solo ' . mb_strtolower($etiqueta) . ': lo vendido, lo comprado y la ganancia del período. El descuento de cada orden se reparte a prorrata entre sus líneas.';
        }

        return [
            'titulo' => $etiqueta,
            'descripcion' => $descripcion,
            'ingreso' => $ventas->ingreso,
            'costo' => $ventas->costo,
            'ganancia' => $ventas->ganancia,
            'unidades' => $ventas->unidades,
            'operaciones' => $ventas->operaciones,
            'inversion' => $compras->costo,
            'unidadesCompradas' => $compras->unidades,
            'costoPromedio' => $this->ratio($compras->costo, $compras->unidades),
            'margen' => $this->ratio($ventas->ganancia * 100, $ventas->ingreso),
            'ticket' => $this->ratio($ventas->ingreso, $ventas->operaciones),
            'tendenciaIngreso' => $this->variacion($ventas->ingreso, $ventasAnt->ingreso),
            'tendenciaGanancia' => $this->variacion($ventas->ganancia, $ventasAnt->ganancia),
            'tendenciaInversion' => $this->variacion($compras->costo, $comprasAnt->costo),
            'mejorVendedor' => $this->mejorPor('user'),
            'mejorSucursal' => $this->mejorPor('sucursal'),
            'topArticulos' => $this->topArticulos(),
        ];
    }

    // ==================================================================
    // Rankings
    // ==================================================================

    /**
     * Mejor vendedor o mejor sucursal de la linea activa, por ingreso.
     *
     * Solo cuentan las ventas: `compras` no guarda ni user_id ni sucursal_id,
     * asi que del lado de la compra ese dato no existe.
     */
    private function mejorPor(string $eje): ?object
    {
        $columna = $eje === 'user' ? 'user_id' : 'sucursal_id';
        $tabla = $eje === 'user' ? 'users' : 'sucursales';
        $campo = $eje === 'user' ? 'name' : 'nombre';

        [$desde, $hasta] = $this->rangoActual();

        if ($this->esCelulares()) {
            $ranking = DB::table('ventas_productos as vp')
                ->join('ventas as v', 'v.id', '=', 'vp.venta_id')
                ->whereBetween('v.created_at', [$desde, $hasta])
                ->whereNotNull("v.$columna")
                ->groupBy("v.$columna")
                ->select("v.$columna as clave", DB::raw('SUM(vp.subtotal) as monto'));
        } else {
            $ranking = DB::table('ventas_repuestos_detalles as d')
                ->join('ventas_repuestos as v', 'v.id', '=', 'd.venta_repuesto_id')
                ->whereBetween('v.created_at', [$desde, $hasta])
                ->whereNotNull("v.$columna")
                ->where('d.tipo', $this->tipoTab())
                ->groupBy("v.$columna")
                ->select("v.$columna as clave", DB::raw('SUM(d.subtotal) as monto'));
        }

        $fila = DB::table(DB::raw("({$ranking->toSql()}) as ranking"))
            ->mergeBindings($ranking)
            ->join("$tabla as t", 't.id', '=', 'ranking.clave')
            ->groupBy('t.id', "t.$campo")
            ->select("t.$campo as nombre", DB::raw('SUM(ranking.monto) as monto'))
            ->orderByDesc('monto')
            ->first();

        return $fila ? (object) ['nombre' => $fila->nombre, 'monto' => round((float) $fila->monto, 2)] : null;
    }

    /**
     * Top de articulos de la linea activa. Un celular es una unidad unica con
     * IMEI, asi que ahi se agrupa por MODELO; los repuestos y accesorios, por
     * articulo.
     */
    private function topArticulos(int $limite = 5): array
    {
        [$desde, $hasta] = $this->rangoActual();

        if ($this->esCelulares()) {
            return DB::table('ventas_productos as vp')
                ->join('ventas as v', 'v.id', '=', 'vp.venta_id')
                ->join('productos as p', 'p.id', '=', 'vp.producto_id')
                ->leftJoin('productos_modelos as m', 'm.id', '=', 'p.producto_modelo_id')
                ->whereBetween('v.created_at', [$desde, $hasta])
                ->groupBy('m.nombre')
                ->select(
                    DB::raw("COALESCE(m.nombre, 'Sin modelo') as nombre"),
                    DB::raw("'Celular' as categoria"),
                    DB::raw('COUNT(vp.id) as unidades'),
                    DB::raw('SUM(vp.subtotal) as monto')
                )
                ->orderByDesc('unidades')
                ->limit($limite)
                ->get()
                ->all();
        }

        return DB::table('ventas_repuestos_detalles as d')
            ->join('ventas_repuestos as v', 'v.id', '=', 'd.venta_repuesto_id')
            ->join('repuestos as r', 'r.id', '=', 'd.repuesto_id')
            ->whereBetween('v.created_at', [$desde, $hasta])
            ->where('d.tipo', $this->tipoTab())
            ->groupBy('r.nombre', 'd.tipo')
            ->select(
                'r.nombre',
                'd.tipo as categoria',
                DB::raw('SUM(d.cantidad) as unidades'),
                DB::raw('SUM(d.subtotal) as monto')
            )
            ->orderByDesc('unidades')
            ->limit($limite)
            ->get()
            ->all();
    }

    // ==================================================================
    // Tabla "Resumen Analitico"
    // ==================================================================

    /**
     * Misma tabla para las tres pestanas: por periodo, lo vendido y lo comprado
     * de esa linea. Las dos ramas del UNION emiten las MISMAS columnas en el
     * MISMO orden -- un UNION empareja por POSICION, no por nombre, y ese
     * descuido ya sumo una vez los accesorios en la columna de repuestos sin
     * dar ningun error.
     */
    private function tablaData()
    {
        [$desde, $hasta] = $this->rangoActual();
        $fmt = $this->reportType === 'monthly' ? '%Y-%m' : '%Y-%m-%d';

        [$ventas, $compras] = $this->esCelulares()
            ? $this->ramasCelulares($desde, $hasta, $fmt)
            : $this->ramasTipo($desde, $hasta, $fmt);

        $combinada = $ventas->unionAll($compras);

        return DB::table(DB::raw("({$combinada->toSql()}) as t"))
            ->mergeBindings($combinada)
            ->groupBy('period_key')
            ->orderBy('period_key', 'desc')
            ->select(
                'period_key',
                DB::raw('SUM(unidades) as unidades'),
                DB::raw('SUM(ingreso) as ingreso'),
                // El costo se deriva: en celulares el coste de una linea sin
                // captura es estimado por GANANCIA_CELULARES, no sumable.
                DB::raw('SUM(ingreso) - SUM(ganancia) as costo'),
                DB::raw('SUM(ganancia) as ganancia'),
                DB::raw('SUM(comp_unidades) as comp_unidades'),
                DB::raw('SUM(inversion) as inversion')
            )
            ->paginate(10);
    }

    /** @return array{0:\Illuminate\Database\Query\Builder,1:\Illuminate\Database\Query\Builder} */
    private function ramasCelulares($desde, $hasta, string $fmt): array
    {
        $ventas = DB::table('ventas_productos as vp')
            ->join('ventas as v', 'v.id', '=', 'vp.venta_id')
            ->whereBetween('v.created_at', [$desde, $hasta])
            ->groupBy('period_key')
            ->select(
                DB::raw("DATE_FORMAT(v.created_at, '$fmt') as period_key"),
                DB::raw('COUNT(vp.id) as unidades'),
                DB::raw('SUM(vp.subtotal) as ingreso'),
                DB::raw(self::GANANCIA_CELULARES . ' as ganancia'),
                DB::raw('0 as comp_unidades'),
                DB::raw('0 as inversion')
            );

        $compras = DB::table('compras as c')
            ->whereBetween('c.fecha_compra', [$desde, $hasta])
            ->groupBy('period_key')
            ->select(
                DB::raw("DATE_FORMAT(c.fecha_compra, '$fmt') as period_key"),
                DB::raw('0 as unidades'),
                DB::raw('0 as ingreso'),
                DB::raw('0 as ganancia'),
                DB::raw('SUM(c.cantidad_total) as comp_unidades'),
                DB::raw('SUM(c.costo_total) as inversion')
            );

        return [$ventas, $compras];
    }

    /** @return array{0:\Illuminate\Database\Query\Builder,1:\Illuminate\Database\Query\Builder} */
    private function ramasTipo($desde, $hasta, string $fmt): array
    {
        $tipo = $this->tipoTab();

        $ventas = DB::table('ventas_repuestos_detalles as d')
            ->join('ventas_repuestos as v', 'v.id', '=', 'd.venta_repuesto_id')
            ->whereBetween('v.created_at', [$desde, $hasta])
            ->where('d.tipo', $tipo)
            ->groupBy('period_key')
            ->select(
                DB::raw("DATE_FORMAT(v.created_at, '$fmt') as period_key"),
                DB::raw('SUM(d.cantidad) as unidades'),
                DB::raw(self::INGRESO_NETO . ' as ingreso'),
                DB::raw(self::INGRESO_NETO . ' - SUM(d.subtotal_costo) as ganancia'),
                DB::raw('0 as comp_unidades'),
                DB::raw('0 as inversion')
            );

        $compras = DB::table('compras_repuestos_detalles as d')
            ->join('compras_repuestos as c', 'c.id', '=', 'd.compra_repuesto_id')
            ->whereBetween('c.fecha_compra', [$desde, $hasta])
            ->where('d.tipo', $tipo)
            ->groupBy('period_key')
            ->select(
                DB::raw("DATE_FORMAT(c.fecha_compra, '$fmt') as period_key"),
                DB::raw('0 as unidades'),
                DB::raw('0 as ingreso'),
                DB::raw('0 as ganancia'),
                DB::raw('SUM(d.cantidad) as comp_unidades'),
                DB::raw('SUM(d.subtotal) as inversion')
            );

        return [$ventas, $compras];
    }

    // ==================================================================
    // Grafico
    // ==================================================================

    /**
     * Las tres pestanas dibujan las mismas series -- Ventas, Compras, Ganancia
     * -- de su linea. La tarjeta del grafico vive FUERA de los bloques por
     * pestana (un canvas por pestana romperia el JS, que captura
     * getElementById una sola vez), asi que aqui solo cambian los datos.
     */
    public function loadChartData()
    {
        [$desde, $hasta] = $this->rangoActual();

        $fmt = $this->reportType === 'monthly' ? '%Y-%m' : '%Y-%m-%d';
        $fmtEtiqueta = $this->reportType === 'monthly' ? 'M Y' : 'M d, Y';
        $fmtClave = $this->reportType === 'monthly' ? 'Y-m' : 'Y-m-d';

        if ($this->esCelulares()) {
            $ventas = $this->serieVentasCelulares($desde, $hasta, $fmt);
            $compras = $this->serieComprasCelulares($desde, $hasta, $fmt);
        } else {
            $ventas = $this->serieVentasTipo($this->tipoTab(), $desde, $hasta, $fmt);
            $compras = $this->serieComprasTipo($this->tipoTab(), $desde, $hasta, $fmt);
        }

        $series = [
            ['label' => 'Ventas', 'rows' => $ventas],
            ['label' => 'Compras', 'rows' => $compras],
            [
                'label' => 'Ganancia',
                'rows' => $ventas,
                'campo' => 'ganancia',
                // El JS oculta en modo "cantidades" los datasets marcados asi.
                // Antes lo hacia por indice fijo (datasets[2]), que con otras
                // series habria escondido la que no era.
                'hideOnCantidades' => true,
            ],
        ];

        $paleta = [
            ['rgba(95, 131, 82, 1)', 'rgba(95, 131, 82, 0.2)'],
            ['rgba(255, 99, 132, 1)', 'rgba(255, 99, 132, 0.2)'],
            ['rgba(54, 162, 235, 1)', 'rgba(54, 162, 235, 0.2)'],
        ];

        $labels = [];
        $periodos = [];
        $cursor = Carbon::parse($this->startDate)->startOfDay();
        $fin = Carbon::parse($this->endDate)->endOfDay();

        while ($cursor <= $fin) {
            $labels[] = $cursor->format($fmtEtiqueta);
            $periodos[] = $cursor->format($fmtClave);
            $this->reportType === 'monthly' ? $cursor->addMonth()->startOfMonth() : $cursor->addDay();
        }

        $datasets = [];
        foreach ($series as $i => $serie) {
            $campo = $serie['campo'] ?? ($this->chartMetric === 'totales' ? 'monto' : 'unidades');
            $datos = [];

            foreach ($periodos as $clave) {
                $fila = $serie['rows']->get($clave);
                $datos[] = round((float) ($fila->$campo ?? 0), 2);
            }

            [$borde, $fondo] = $paleta[$i % count($paleta)];

            $datasets[] = [
                'label' => $serie['label'],
                'data' => $datos,
                'borderColor' => $borde,
                'backgroundColor' => $fondo,
                'tension' => 0.1,
                'fill' => !isset($serie['campo']),
                'hideOnCantidades' => $serie['hideOnCantidades'] ?? false,
            ];
        }

        $this->chartData = ['labels' => $labels, 'datasets' => $datasets];

        $this->dispatch('updateChart', data: [
            'labels' => $labels,
            'datasets' => $datasets,
            'metric' => $this->chartMetric,
        ]);
    }

    private function serieVentasCelulares($desde, $hasta, string $fmt)
    {
        return DB::table('ventas_productos as vp')
            ->join('ventas as v', 'v.id', '=', 'vp.venta_id')
            ->whereBetween('v.created_at', [$desde, $hasta])
            ->groupBy('period_key')
            ->select(
                DB::raw("DATE_FORMAT(v.created_at, '$fmt') as period_key"),
                DB::raw('SUM(vp.subtotal) as monto'),
                DB::raw('COUNT(vp.id) as unidades'),
                DB::raw(self::GANANCIA_CELULARES . ' as ganancia')
            )
            ->get()
            ->keyBy('period_key');
    }

    private function serieComprasCelulares($desde, $hasta, string $fmt)
    {
        return DB::table('compras as c')
            ->whereBetween('c.fecha_compra', [$desde, $hasta])
            ->groupBy('period_key')
            ->select(
                DB::raw("DATE_FORMAT(c.fecha_compra, '$fmt') as period_key"),
                DB::raw('SUM(c.costo_total) as monto'),
                DB::raw('SUM(c.cantidad_total) as unidades'),
                DB::raw('0 as ganancia')
            )
            ->get()
            ->keyBy('period_key');
    }

    private function serieVentasTipo(?string $tipo, $desde, $hasta, string $fmt)
    {
        return DB::table('ventas_repuestos_detalles as d')
            ->join('ventas_repuestos as v', 'v.id', '=', 'd.venta_repuesto_id')
            ->whereBetween('v.created_at', [$desde, $hasta])
            ->when($tipo, fn($q) => $q->where('d.tipo', $tipo))
            ->groupBy('period_key')
            ->select(
                DB::raw("DATE_FORMAT(v.created_at, '$fmt') as period_key"),
                DB::raw(self::INGRESO_NETO . ' as monto'),
                DB::raw('SUM(d.cantidad) as unidades'),
                DB::raw(self::INGRESO_NETO . ' - SUM(d.subtotal_costo) as ganancia')
            )
            ->get()
            ->keyBy('period_key');
    }

    private function serieComprasTipo(?string $tipo, $desde, $hasta, string $fmt)
    {
        return DB::table('compras_repuestos_detalles as d')
            ->join('compras_repuestos as c', 'c.id', '=', 'd.compra_repuesto_id')
            ->whereBetween('c.fecha_compra', [$desde, $hasta])
            ->when($tipo, fn($q) => $q->where('d.tipo', $tipo))
            ->groupBy('period_key')
            ->select(
                DB::raw("DATE_FORMAT(c.fecha_compra, '$fmt') as period_key"),
                DB::raw('SUM(d.subtotal) as monto'),
                DB::raw('SUM(d.cantidad) as unidades'),
                DB::raw('0 as ganancia')
            )
            ->get()
            ->keyBy('period_key');
    }
}
