<?php

namespace App\Livewire\Venta;

use App\Enums\LineaTipo;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Services\RepuestosDeReparacionService;
use App\Services\VentaService;
use App\Traits\CarritoBuscadorTrait;
use App\Traits\ClienteBuscadorTrait;
use App\Traits\GuardadoIdempotenteTrait;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Registrar o editar una venta: equipos, repuestos y accesorios en el mismo
 * documento, en Bs. Lo escribe VentaService.
 *
 * Un solo buscador (IMEI, codigo de barras, SKU o nombre) y el catalogo. Los
 * equipos llevan garantia y, si tuvieron reparaciones, se les pueden cobrar las
 * piezas montadas (RepuestosDeReparacionService).
 *
 * Los importes que se ven aqui son una vista previa: el costo y los totales
 * reales los calcula el servicio releyendo la base.
 */
class VentaForm extends Component
{
    use CarritoBuscadorTrait;
    use ClienteBuscadorTrait;
    use GuardadoIdempotenteTrait;

    /** Null al crear. #[Locked]: decide que venta se reescribe. */
    #[Locked]
    public ?int $ventaId = null;

    /** sucursal_id, cliente_id, cliente, descuento, mano_obra */
    public array $venta = [];

    /**
     * [['tipo','id','descripcion','codigo','cantidad','precio','descuento',
     *   'garantia_meses','stock','repuestos_elegibles'], ...]
     */
    public array $lineas = [];

    /** Vendedor o Cliente: que precio de lista se propone para los equipos. */
    public string $tipo_precio = 'Vendedor';

    /** Cobros de taller por equipo: producto_id => [lineas]. */
    public array $repuestosVenta = [];

    /** Cobros de taller ya registrados en la venta (edicion, solo lectura). */
    public array $cobrosExistentes = [];

    public function mount(?int $ventaId = null): void
    {
        if ($ventaId) {
            abort_unless(Auth::user()?->can('venta.edit'), 403);
            $this->cargarVenta($ventaId);

            return;
        }

        abort_unless(Auth::user()?->can('venta.create'), 403);

        // Una clave por apertura: un reintento tras cortarse la red trae la
        // misma y no crea una segunda venta.
        $this->nuevaClaveIdempotencia();
        $this->venta = ['sucursal_id' => null, 'cliente_id' => null, 'cliente' => null, 'descuento' => 0, 'mano_obra' => 0];

        // "Vender" desde la ficha del equipo: llega con ?producto= y la sucursal
        // del equipo ya puesta.
        $productoId = (int) request()->query('producto', 0);
        if ($productoId && $producto = Producto::disponibles()->find($productoId)) {
            $sucursal = Sucursal::activas()->find($producto->sucursal_id);
            if ($sucursal) {
                $this->venta['sucursal_id'] = $sucursal->id;
                $this->agregarLinea(LineaTipo::Producto->value, $producto->id);
            }
        }
    }

    private function cargarVenta(int $ventaId): void
    {
        $venta = Venta::with(['detalles.producto.modelo', 'detalles.repuesto', 'detalles.accesorio'])->findOrFail($ventaId);

        $this->ventaId = $venta->id;
        $this->venta = [
            'sucursal_id' => $venta->sucursal_id,
            'cliente_id' => $venta->cliente_id,
            'cliente' => $venta->cliente,
            'descuento' => (float) $venta->descuento,
            'mano_obra' => (float) $venta->mano_obra,
        ];

        foreach ($venta->detalles as $d) {
            if ($d->stockYaDescontado()) {
                $this->cobrosExistentes[] = [
                    'nombre' => $d->repuesto?->nombre,
                    'cantidad' => (int) $d->cantidad,
                    'subtotal' => (float) $d->subtotal,
                ];
                continue;
            }

            $articulo = $d->articulo();
            $this->lineas[] = [
                'tipo' => $d->tipo,
                'id' => (int) $articulo->id,
                'descripcion' => $d->descripcion(),
                'codigo' => $d->producto_id ? $articulo->imei : $articulo->sku,
                'cantidad' => (int) $d->cantidad,
                'precio' => (float) $d->precio,
                'descuento' => (float) $d->descuento,
                'garantia_meses' => $d->garantia_meses,
                // En edicion, lo que ya tiene la linea tambien esta disponible.
                'stock' => $d->producto_id ? 1 : $articulo->stockEn($venta->sucursal_id) + (int) $d->cantidad,
                'repuestos_elegibles' => $d->producto_id ? app(RepuestosDeReparacionService::class)->contarElegibles($articulo->id) : 0,
            ];
        }
    }

    public function esEdicion(): bool
    {
        return $this->ventaId !== null;
    }

    // ----------------------------------------------------------- cliente

    /** cliente_id es el enlace; `cliente` el nombre CONGELADO en el documento. */
    protected function fijarCliente(?Cliente $cliente): void
    {
        $this->venta['cliente_id'] = $cliente?->id;
        $this->venta['cliente'] = $cliente?->nombre;
    }

    public function clienteIdElegido(): ?int
    {
        return !empty($this->venta['cliente_id']) ? (int) $this->venta['cliente_id'] : null;
    }

    // ----------------------------------------------------------- buscador

    protected function tiposBuscables(): array
    {
        return LineaTipo::cases();
    }

    public function sucursalDelDocumento(): ?int
    {
        return !empty($this->venta['sucursal_id']) ? (int) $this->venta['sucursal_id'] : null;
    }

    public function motivoSinSucursal(): string
    {
        return 'Elige primero la sucursal de la venta.';
    }

    protected function clavesEnCarrito(): array
    {
        return array_map(fn($l) => $l['tipo'] . ':' . $l['id'], $this->lineas);
    }

    protected function agregarLinea(string $tipo, int $id): void
    {
        $lineaTipo = LineaTipo::from($tipo);
        $sucursalId = $this->sucursalDelDocumento();

        if ($lineaTipo === LineaTipo::Producto) {
            $producto = Producto::with('modelo')->disponibles()->find($id);

            if (!$producto) {
                toastr()->warning('Ese equipo ya no está disponible para vender.');

                return;
            }

            array_unshift($this->lineas, [
                'tipo' => $tipo,
                'id' => $producto->id,
                'descripcion' => trim(($producto->modelo?->nombre ?? 'Equipo') . ' ' . $producto->almacenamiento . ' ' . $producto->color),
                'codigo' => $producto->imei,
                'cantidad' => 1,
                'precio' => (float) ($this->tipo_precio === 'Vendedor' ? $producto->precio_vendedor : $producto->precio_cliente),
                'descuento' => 0,
                'garantia_meses' => 3,
                'stock' => 1,
                // Se cuenta una vez al agregar: pintarlo por render seria una
                // consulta por fila en cada peticion.
                'repuestos_elegibles' => app(RepuestosDeReparacionService::class)->contarElegibles($producto->id),
            ]);

            return;
        }

        $articulo = $lineaTipo->articulo()->buscar($id);

        if (!$articulo) {
            return;
        }

        $stock = $articulo->stockEn($sucursalId);

        if ($stock < 1) {
            toastr()->warning("No hay stock de «{$articulo->nombre}» en esta sucursal.");

            return;
        }

        array_unshift($this->lineas, [
            'tipo' => $tipo,
            'id' => $articulo->id,
            'descripcion' => $articulo->nombre,
            'codigo' => $articulo->sku,
            'cantidad' => 1,
            'precio' => (float) $articulo->precio,
            'descuento' => 0,
            'garantia_meses' => null,
            'stock' => $stock,
            'repuestos_elegibles' => 0,
        ]);
    }

    /** Lo que llega del catalogo de equipos (ProductoSelectorModal). */
    #[On('productosSeleccionados')]
    public function agregarProductos(array $ids): void
    {
        foreach ($ids as $id) {
            $this->seleccionarResultado(LineaTipo::Producto->value, (int) $id);
        }
    }

    public function abrirSelectorEquipos(): void
    {
        if ($this->sucursalDelDocumento() === null) {
            toastr()->warning($this->motivoSinSucursal());

            return;
        }

        $this->dispatch(
            'openProductoSelectorModal',
            excluidos: collect($this->lineas)->where('tipo', LineaTipo::Producto->value)->pluck('id')->values()->all(),
            sucursalId: $this->sucursalDelDocumento(),
            tipoPrecio: $this->tipo_precio,
        );
    }

    public function quitarLinea(int $index): void
    {
        if (!isset($this->lineas[$index])) {
            return;
        }

        // Si el equipo sale, sus cobros de taller se van con el.
        if ($this->lineas[$index]['tipo'] === LineaTipo::Producto->value) {
            unset($this->repuestosVenta[$this->lineas[$index]['id']]);
        }

        unset($this->lineas[$index]);
        $this->lineas = array_values($this->lineas);
    }

    /** Re-precia los equipos al cambiar entre precio Vendedor y Cliente. */
    public function updatedTipoPrecio(): void
    {
        $ids = collect($this->lineas)->where('tipo', LineaTipo::Producto->value)->pluck('id');
        $productos = Producto::whereIn('id', $ids)->get()->keyBy('id');

        foreach ($this->lineas as $i => $linea) {
            if ($linea['tipo'] === LineaTipo::Producto->value && $p = $productos->get($linea['id'])) {
                $this->lineas[$i]['precio'] = (float) ($this->tipo_precio === 'Vendedor' ? $p->precio_vendedor : $p->precio_cliente);
            }
        }
    }

    /** La sucursal no cambia con lineas cargadas (ni en edicion): el stock sale de ella. */
    public function updatedVentaSucursalId(): void
    {
        $this->busqueda = '';
        $this->resultados = [];
    }

    // ------------------------------------------------- cobros de taller

    public function abrirRepuestosDe($productoId): void
    {
        $this->dispatch(
            'openProductoRepuestosVentaModal',
            productoId: (int) $productoId,
            yaElegidos: $this->repuestosVenta[$productoId] ?? [],
        );
    }

    #[On('repuestosDeReparacionSeleccionados')]
    public function guardarRepuestosDe($productoId, array $lineas): void
    {
        if (empty($lineas)) {
            unset($this->repuestosVenta[$productoId]);

            return;
        }

        $this->repuestosVenta[$productoId] = $lineas;
    }

    // ------------------------------------------------------- totales

    public function subtotalLinea(array $l): float
    {
        $bruto = (float) $l['precio'] * max(1, (int) $l['cantidad']);

        return round($bruto - min((float) $l['descuento'], $bruto), 2);
    }

    public function totalCobrosNuevos(): float
    {
        $total = 0;
        foreach ($this->repuestosVenta as $lineas) {
            foreach ($lineas as $linea) {
                $total += (float) ($linea['subtotal'] ?? 0);
            }
        }

        return round($total, 2);
    }

    public function totales(): array
    {
        $subtotal = array_sum(array_map(fn($l) => $this->subtotalLinea($l), $this->lineas))
            + $this->totalCobrosNuevos()
            + array_sum(array_column($this->cobrosExistentes, 'subtotal'));

        return [
            'subtotal' => round($subtotal, 2),
            'total' => round($subtotal - (float) ($this->venta['descuento'] ?: 0) + (float) ($this->venta['mano_obra'] ?: 0), 2),
        ];
    }

    // ------------------------------------------------------- guardar

    protected function rules(): array
    {
        return [
            'venta.sucursal_id' => $this->esEdicion() ? 'nullable' : 'required|integer|exists:sucursales,id,activa,1',
            'venta.descuento' => 'nullable|numeric|min:0',
            'venta.mano_obra' => 'nullable|numeric|min:0',
            'lineas' => 'required|array|min:1',
            'lineas.*.cantidad' => 'required|integer|min:1',
            'lineas.*.precio' => 'required|numeric|min:0',
            'lineas.*.descuento' => 'nullable|numeric|min:0',
            'lineas.*.garantia_meses' => 'nullable|integer|min:0|max:60',
        ];
    }

    protected function messages(): array
    {
        return [
            'venta.sucursal_id.required' => 'Selecciona la sucursal de la venta.',
            'venta.sucursal_id.exists' => 'La sucursal elegida no existe o está desactivada.',
            'lineas.required' => 'Agrega al menos un equipo, repuesto o accesorio.',
            'lineas.min' => 'Agrega al menos un equipo, repuesto o accesorio.',
            'lineas.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
        ];
    }

    public function guardar()
    {
        $this->validate();

        $cabecera = [
            'sucursal_id' => $this->sucursalDelDocumento(),
            'cliente_id' => $this->venta['cliente_id'] ?? null,
            'cliente' => $this->venta['cliente'] ?? null,
            'descuento' => (float) ($this->venta['descuento'] ?: 0),
            'mano_obra' => (float) ($this->venta['mano_obra'] ?: 0),
        ];
        $lineas = array_map(fn($l) => [
            'tipo' => $l['tipo'], 'id' => $l['id'], 'cantidad' => (int) $l['cantidad'],
            'precio' => (float) $l['precio'], 'descuento' => (float) ($l['descuento'] ?: 0),
            'garantia_meses' => $l['garantia_meses'] !== '' ? $l['garantia_meses'] : null,
        ], $this->lineas);

        $servicio = app(VentaService::class);

        if ($this->esEdicion()) {
            abort_unless(Auth::user()?->can('venta.edit'), 403);

            DB::transaction(fn() => $servicio->actualizar(Venta::lockForUpdate()->findOrFail($this->ventaId), $cabecera, $lineas, $this->repuestosVenta));
            toastr()->success('Venta actualizada');

            return redirect()->route('ventas.detalles', $this->ventaId);
        }

        abort_unless(Auth::user()?->can('venta.create'), 403);

        // El reintento: la primera peticion ya cerro. Se responde con la venta
        // que existe en vez de crear una segunda.
        if ($ya = $this->yaGuardado(Venta::class)) {
            $this->avisarYaGuardado($ya, 'venta');

            return redirect()->route('ventas.detalles', $ya->id);
        }

        try {
            $venta = DB::transaction(fn() => $servicio->registrar($cabecera, $lineas, $this->repuestosVenta, Auth::user(), $this->claveIdempotencia));
        } catch (QueryException $e) {
            // Dos peticiones a la vez con la misma clave: la otra commiteo y la
            // nuestra ya revirtio entera, asi que releer es seguro.
            if ($this->esClaveDuplicada($e) && $ya = $this->yaGuardado(Venta::class)) {
                $this->avisarYaGuardado($ya, 'venta');

                return redirect()->route('ventas.detalles', $ya->id);
            }

            throw $e;
        }

        toastr()->success('Venta registrada exitosamente');

        return redirect()->route('ventas.detalles', $venta->id);
    }

    public function render()
    {
        return view('livewire.venta.venta-form', [
            'sucursales' => $this->esEdicion()
                ? Sucursal::paraSelect($this->sucursalDelDocumento())
                : Sucursal::activas()->orderBy('nombre')->get(),
        ]);
    }
}
