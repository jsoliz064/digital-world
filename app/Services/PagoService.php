<?php

namespace App\Services;

use App\Enums\BitacoraEvento;
use App\Enums\PagoMomento;
use App\Enums\ProductoEstado;
use App\Models\Bitacora;
use App\Models\MetodoPago;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaPago;
use Illuminate\Validation\ValidationException;

/**
 * El UNICO escritor de ventas_pagos, de ventas.pagado y de ventas.pagada_at.
 *
 * Una venta con saldo esta A CREDITO: tiene que tener cliente con ficha y sus
 * equipos estan en Credito. Cuando el saldo llega a cero queda PAGADA:
 * pagada_at guarda cuando (la comision del vendedor se gana ahi, etapa 7) y los
 * equipos pasan a Vendido. sincronizar() es el unico sitio que decide eso, y lo
 * llaman todos los flujos que mueven el total o lo cobrado: registrar y anular
 * un pago, editar la venta y anular una linea.
 *
 * Se llama "pago" y no "cobro" porque "cobro" ya es, en el codigo, el cobro de
 * las piezas de una reparacion (RepuestosDeReparacionService).
 *
 * ORDEN DE BLOQUEO: la venta primero, despues sus equipos (por id). Es el mismo
 * orden en todos los flujos; en otro, dos cobros simultaneos se bloquearian.
 *
 * Ningun metodo abre transaccion: la abre el componente.
 */
class PagoService
{
    public function __construct(private EstadoProductoService $estados) {}

    /**
     * Registra uno o varios pagos de una venta.
     *
     * @param  array  $pagos  [['metodo_pago_id' => int, 'monto' => float, 'nota' => ?string], ...]
     *
     * @throws ValidationException si un metodo no esta activo, un monto no es
     *         positivo o la suma supera el saldo (leido despues del bloqueo).
     */
    public function registrar(Venta $venta, array $pagos, PagoMomento $momento, User $user, ?string $clave = null): Venta
    {
        $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
        $pagos = $this->normalizar($pagos);

        if ($pagos === []) {
            return $this->sincronizar($venta);
        }

        $suma = round(array_sum(array_column($pagos, 'monto')), 2);
        $saldo = $venta->saldoPendiente();

        if ($suma > $saldo) {
            throw ValidationException::withMessages([
                'pagos' => 'El cobro de Bs ' . number_format($suma, 2) . ' supera el saldo de la venta #' . $venta->id
                    . ' (Bs ' . number_format($saldo, 2) . ').',
            ]);
        }

        $metodos = MetodoPago::whereKey(array_column($pagos, 'metodo_pago_id'))->get()->keyBy('id');
        $frases = [];

        foreach ($pagos as $pago) {
            $metodo = $metodos->get($pago['metodo_pago_id']);

            if (!$metodo || !$metodo->activo) {
                throw ValidationException::withMessages(['pagos' => 'Uno de los métodos de pago no existe o está desactivado.']);
            }

            VentaPago::create([
                'venta_id' => $venta->id,
                'metodo_pago_id' => $metodo->id,
                'monto' => $pago['monto'],
                'momento' => $momento->value,
                'fecha' => now(),
                'nota' => $pago['nota'],
                'user_id' => $user->id,
                'clave_idempotencia' => $clave,
            ]);

            $frases[] = 'Bs ' . number_format($pago['monto'], 2) . ' en ' . $metodo->nombre;
        }

        // anotar() antes del save de sincronizar(): una sola fila con la frase
        // y el cambio de `pagado`.
        $restante = round($saldo - $suma, 2);
        $venta->anotar(
            BitacoraEvento::Pago->value,
            ($momento === PagoMomento::Venta ? 'Cobrado al vender: ' : 'Cobro: ') . implode(' + ', $frases)
                . '. ' . ($restante > 0 ? 'Saldo Bs ' . number_format($restante, 2) . '.' : 'Venta pagada.'),
            ['venta_id' => $venta->id],
        );

        return $this->sincronizar($venta);
    }

    /** Anula un pago: la venta vuelve a tener ese saldo. */
    public function anular(VentaPago $pago, User $user): Venta
    {
        $venta = Venta::whereKey($pago->venta_id)->lockForUpdate()->firstOrFail();
        $pago = VentaPago::with('metodo')->whereKey($pago->id)->where('venta_id', $venta->id)->first();

        if (!$pago) {
            throw ValidationException::withMessages(['pagos' => 'Ese pago ya no existe: alguien lo anuló. Recarga la pantalla.']);
        }

        $venta->anotar(
            BitacoraEvento::PagoAnulado->value,
            'Pago anulado por ' . $user->name . ': Bs ' . number_format((float) $pago->monto, 2)
                . ' en ' . ($pago->metodo?->nombre ?? 'método') . ' del ' . $pago->fecha->format('d/m/Y H:i') . '.',
            ['venta_id' => $venta->id],
        );
        $pago->delete();

        return $this->sincronizar($venta);
    }

    /**
     * Borra todos los pagos de una venta que se va a anular entera. Uno por uno
     * en la bitacora: se entiende que el dinero se devolvio.
     */
    public function anularTodos(Venta $venta, string $motivo): void
    {
        foreach ($venta->pagos()->with('metodo')->orderBy('id')->get() as $pago) {
            // registrar() y no anotar(): la venta se borra a continuacion y no
            // habra un save() que lleve la nota.
            Bitacora::registrar(
                $venta,
                BitacoraEvento::PagoAnulado->value,
                'Pago de Bs ' . number_format((float) $pago->monto, 2) . ' en ' . ($pago->metodo?->nombre ?? 'método') . " anulado: {$motivo}",
                ['venta_id' => $venta->id],
            );
            $pago->delete();
        }
    }

    /**
     * Recalcula lo cobrado desde la base y deja la venta y sus equipos en el
     * estado que les toca. Es idempotente: si nada cambio, no escribe nada.
     */
    public function sincronizar(Venta $venta): Venta
    {
        $pagado = round((float) $venta->pagos()->sum('monto'), 2);
        $total = round((float) $venta->total, 2);

        if ($pagado > $total) {
            throw ValidationException::withMessages([
                'pagos' => "La venta #{$venta->id} quedaría con Bs " . number_format($pagado, 2)
                    . ' cobrados sobre un total de Bs ' . number_format($total, 2) . '. Anula un pago antes.',
            ]);
        }

        $aCredito = $pagado < $total;

        if ($aCredito && !$venta->cliente_id) {
            throw ValidationException::withMessages([
                'cliente' => 'Quedan Bs ' . number_format($total - $pagado, 2)
                    . ' por cobrar: una venta a crédito necesita un cliente con ficha.',
            ]);
        }

        $venta->pagado = $pagado;
        $venta->pagada_at = $aCredito ? null : ($venta->pagada_at ?? now());
        $venta->save();

        $this->moverEquipos($venta, $aCredito ? ProductoEstado::Credito : ProductoEstado::Vendido);

        return $venta;
    }

    /** Los equipos de la venta a Credito o a Vendido, solo los que no lo esten ya. */
    private function moverEquipos(Venta $venta, ProductoEstado $destino): void
    {
        $origen = $destino === ProductoEstado::Credito ? ProductoEstado::Vendido : ProductoEstado::Credito;

        $ids = $venta->detalles()->whereNotNull('producto_id')
            ->whereHas('producto', fn($q) => $q->where('estado', $origen->value))
            ->orderBy('producto_id')
            ->pluck('producto_id');

        foreach ($ids as $productoId) {
            $this->estados->cambiar(
                $productoId,
                $origen,
                $destino,
                $destino === ProductoEstado::Vendido
                    ? "Venta #{$venta->id} pagada por completo."
                    : "Venta #{$venta->id} a crédito: saldo Bs " . number_format($venta->saldoPendiente(), 2) . '.',
                ['venta_id' => $venta->id],
            );
        }
    }

    /** @return array<int, array{metodo_pago_id:int, monto:float, nota:?string}> */
    private function normalizar(array $pagos): array
    {
        $limpios = [];

        foreach ($pagos as $pago) {
            $monto = round((float) ($pago['monto'] ?? 0), 2);

            // Fila vacia del formulario.
            if ($monto == 0.0 && empty($pago['metodo_pago_id'])) {
                continue;
            }

            if ($monto <= 0) {
                throw ValidationException::withMessages(['pagos' => 'Cada pago tiene que ser mayor a cero.']);
            }

            if (empty($pago['metodo_pago_id'])) {
                throw ValidationException::withMessages(['pagos' => 'Elige el método de cada pago.']);
            }

            // Dos filas del mismo metodo son un solo pago: lo exige tambien el
            // indice vp_clave_idem_venta_metodo_unico.
            $metodo = (int) $pago['metodo_pago_id'];
            $nota = trim((string) ($pago['nota'] ?? ''));

            if (isset($limpios[$metodo])) {
                $limpios[$metodo]['monto'] = round($limpios[$metodo]['monto'] + $monto, 2);
                continue;
            }

            $limpios[$metodo] = [
                'metodo_pago_id' => $metodo,
                'monto' => $monto,
                'nota' => $nota === '' ? null : mb_substr($nota, 0, 255),
            ];
        }

        return array_values($limpios);
    }
}
