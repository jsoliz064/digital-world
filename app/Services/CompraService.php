<?php

namespace App\Services;

use App\Enums\LineaTipo;
use App\Enums\ProductoEstado;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * El UNICO escritor de compras_detalles.
 *
 * Una compra trae equipos (cada uno es una linea de cantidad 1, y el equipo se
 * CREA con ella) y repuestos o accesorios (con cantidad, que entran al stock de
 * la sucursal de la compra). Los equipos se dan de alta de a uno desde el
 * detalle de la compra (con fotos y camara); los articulos van en el
 * formulario de crear/editar la compra.
 *
 * El costo de una linea de equipo es su `costo_unidad`: los dos solo los
 * escribe este servicio, y productos:auditar vigila que no diverjan.
 *
 * Ningun metodo abre transaccion: la abre el componente.
 *
 * Formato de una linea de articulo que llega de la pantalla:
 *   ['tipo' => 'Repuesto'|'Accesorio', 'id' => int, 'cantidad' => int, 'costo' => float]
 */
class CompraService
{
    public function __construct(
        private StockService $stock,
    ) {}

    /**
     * Crea la compra con sus articulos (los equipos se agregan despues, desde
     * el detalle).
     *
     * @param  array  $cabecera  proveedor_id, fecha, sucursal_id
     */
    public function crear(array $cabecera, array $articulos, User $user, ?string $clave = null): Compra
    {
        $articulos = $this->normalizar($articulos);

        $compra = Compra::create([
            'clave_idempotencia' => $clave,
            'proveedor_id' => $cabecera['proveedor_id'],
            'fecha' => $cabecera['fecha'],
            'sucursal_id' => $cabecera['sucursal_id'],
            'user_id' => $user->id,
            'total' => round($articulos->sum(fn($l) => $l['cantidad'] * $l['costo']), 2),
        ]);

        // Ordenado por (tipo, id): dos guardados simultaneos con las mismas
        // lineas en distinto orden se bloquearian mutuamente en InnoDB.
        foreach ($articulos->sortKeys() as $linea) {
            $this->agregarArticulo($compra, $linea);
        }

        $this->stock->recalcularTotales();
        $compra->recalcularTotal();

        return $compra;
    }

    /**
     * Edita proveedor, fecha y los articulos. La sucursal NO cambia: el stock ya
     * entro en ella. Compara por articulo y mueve solo la diferencia:
     * ajustarEntrada() para lo que cambio, retirar() para lo que se quito (y
     * falla con mensaje si esas unidades ya se vendieron).
     */
    public function actualizar(Compra $compra, array $cabecera, array $articulos): Compra
    {
        $articulos = $this->normalizar($articulos);
        $actuales = $compra->detalles()->whereNull('producto_id')->get()
            ->keyBy(fn($d) => $d->tipo . ':' . $d->{LineaTipo::from($d->tipo)->columna()});

        foreach ($actuales->diffKeys($articulos) as $detalle) {
            $tipo = $detalle->tipoLinea()->articulo();
            $this->stock->retirar($tipo, $detalle->{$tipo->columna()}, $detalle->sucursal_id, (int) $detalle->cantidad);
            $detalle->delete();
        }

        foreach ($actuales->intersectByKeys($articulos) as $clave => $detalle) {
            $linea = $articulos[$clave];
            $tipo = $detalle->tipoLinea()->articulo();
            $this->stock->ajustarEntrada($tipo, $detalle->{$tipo->columna()}, $detalle->sucursal_id, (int) $detalle->cantidad, $linea['cantidad']);
            $detalle->update([
                'cantidad' => $linea['cantidad'],
                'costo' => $linea['costo'],
                'subtotal' => round($linea['cantidad'] * $linea['costo'], 2),
            ]);
        }

        foreach ($articulos->diffKeys($actuales)->sortKeys() as $linea) {
            $this->agregarArticulo($compra, $linea);
        }

        $compra->fill([
            'proveedor_id' => $cabecera['proveedor_id'],
            'fecha' => $cabecera['fecha'],
        ]);

        $this->stock->recalcularTotales();
        $compra->recalcularTotal();

        return $compra;
    }

    /**
     * Da de alta un equipo dentro de la compra: crea el producto, su linea y
     * sus fotos. El IMEI es la clave natural (UNIQUE productos_imei_unico): un
     * reintento choca ahi en vez de crear el equipo dos veces.
     *
     * @param  array  $datos  columnas del producto (sin compra: no existe la columna)
     * @param  string[]  $fotos  imagenes en base64
     */
    public function agregarProducto(Compra $compra, array $datos, array $fotos = []): Producto
    {
        $datos['sucursal_id'] = $datos['sucursal_id'] ?? $compra->sucursal_id;
        $datos['estado'] = $datos['estado'] ?? ProductoEstado::Inventario->value;

        // make() + anotar() + save() y no create(): asi el Observer escribe UNA
        // fila con el estado con el que nace el equipo, la frase y su retrato.
        // Sin esta fila, un equipo creado en Fuera o Roto nacia sin traza y el
        // auditor lo veia como "ultimo historial distinto del estado real".
        $producto = new Producto($datos);
        $producto->anotar($datos['estado'], "Producto registrado en la compra #{$compra->id}");
        $producto->save();

        CompraDetalle::create([
            'compra_id' => $compra->id,
            'producto_id' => $producto->id,
            'sucursal_id' => $producto->sucursal_id,
            'cantidad' => 1,
            'costo' => (float) $producto->costo_unidad,
            'subtotal' => (float) $producto->costo_unidad,
        ]);

        foreach ($fotos as $foto) {
            ProductoImagen::create(['base64' => $foto, 'producto_id' => $producto->id]);
        }

        $producto->recalcularCosto();
        $compra->recalcularTotal();

        return $producto;
    }

    /**
     * Sincroniza la linea de compra con el costo_unidad del equipo, despues de
     * editarlo. Llamarlo siempre que cambie costo_unidad.
     */
    public function actualizarCostoProducto(Producto $producto): void
    {
        $detalle = $producto->compraDetalle;

        if (!$detalle) {
            return;
        }

        $detalle->update([
            'costo' => (float) $producto->costo_unidad,
            'subtotal' => (float) $producto->costo_unidad,
        ]);

        $detalle->compra->recalcularTotal();
    }

    /**
     * Quita un equipo de su compra y lo borra. Solo si nunca se movio: con
     * venta, reparaciones o regalos tiene historia, y las FK en RESTRICT lo
     * impedirian igual; aqui se avisa antes con un mensaje claro.
     */
    public function quitarProducto(Producto $producto): void
    {
        // Recibido en permuta: es el pago de una venta. Se va anulando esa venta.
        if ($producto->permuta()->exists()) {
            throw ValidationException::withMessages([
                'detalles' => "El equipo {$producto->imei} se recibió en permuta: para quitarlo, anula la venta #{$producto->permuta->venta_id}.",
            ]);
        }

        if ($producto->ventaDetalle()->exists() || $producto->reparaciones()->exists() || $producto->regalos()->exists()) {
            throw ValidationException::withMessages([
                'detalles' => "El equipo {$producto->imei} ya tiene ventas, reparaciones o regalos: no se puede quitar de la compra. Dalo de baja si ya no existe.",
            ]);
        }

        $compra = $producto->compra;

        $producto->compraDetalle()->delete();
        $producto->imagenes()->delete();
        $producto->delete();

        $compra?->recalcularTotal();
    }

    /**
     * Elimina una compra. Solo sin equipos (cada equipo se quita antes, con sus
     * propias reglas). Los articulos salen del stock de la sucursal de su
     * linea; si ya se vendieron, retirar() falla con mensaje y no se borra nada.
     */
    public function eliminar(Compra $compra): void
    {
        if ($compra->detalles()->whereNotNull('producto_id')->exists()) {
            throw ValidationException::withMessages([
                'detalles' => 'La compra tiene equipos: quítalos primero desde el detalle de la compra.',
            ]);
        }

        foreach ($compra->detalles as $detalle) {
            $tipo = $detalle->tipoLinea()->articulo();
            $this->stock->retirar($tipo, $detalle->{$tipo->columna()}, $detalle->sucursal_id, (int) $detalle->cantidad);
            $detalle->delete();
        }

        $this->stock->recalcularTotales();
        $compra->delete();
    }

    // ------------------------------------------------------------------ apoyo

    private function agregarArticulo(Compra $compra, array $linea): void
    {
        $tipo = LineaTipo::from($linea['tipo'])->articulo();

        $this->stock->ingresar($tipo, $linea['id'], $compra->sucursal_id, $linea['cantidad']);

        CompraDetalle::create([
            'compra_id' => $compra->id,
            $tipo->columna() => $linea['id'],
            'sucursal_id' => $compra->sucursal_id,
            'cantidad' => $linea['cantidad'],
            'costo' => $linea['costo'],
            'subtotal' => round($linea['cantidad'] * $linea['costo'], 2),
        ]);
    }

    /** @return Collection<string,array> keyed "Tipo:id" */
    private function normalizar(array $articulos): Collection
    {
        $lineas = collect($articulos)->map(function ($l) {
            $tipo = LineaTipo::tryFrom((string) ($l['tipo'] ?? ''));
            $id = (int) ($l['id'] ?? 0);
            $cantidad = (int) ($l['cantidad'] ?? 0);
            $costo = round((float) ($l['costo'] ?? 0), 2);

            if (!$tipo || $tipo === LineaTipo::Producto || $id <= 0) {
                throw ValidationException::withMessages(['detalles' => 'Hay una línea de compra inválida. Recarga la pantalla.']);
            }
            if ($cantidad < 1 || $costo < 0) {
                throw ValidationException::withMessages(['detalles' => 'Cada línea necesita al menos una unidad y un costo que no sea negativo.']);
            }

            return ['tipo' => $tipo->value, 'id' => $id, 'cantidad' => $cantidad, 'costo' => $costo];
        });

        $porClave = $lineas->keyBy(fn($l) => $l['tipo'] . ':' . $l['id']);

        if ($porClave->count() !== $lineas->count()) {
            throw ValidationException::withMessages(['detalles' => 'Hay un artículo repetido en la compra: suma la cantidad en una sola línea.']);
        }

        return $porClave;
    }
}
