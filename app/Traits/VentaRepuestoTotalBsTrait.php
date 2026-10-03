<?php

namespace App\Traits;

/**
 * El total en Bs de una venta de repuestos: conversion + ajuste.
 *
 *     base_bs  = round(total * tipo_cambio, 2)
 *     total_bs = base_bs + ajuste_bs
 *
 * `ajuste_bs` es el estado y `total_bs` siempre se deriva. Teclear el Bs no
 * despeja la tasa -- eso es lo que hacia antes, y dejaba ordenes con tasas
 * inventadas como el 10.94 de la venta #15, que solo existia para absorber un
 * recargo de 300 Bs. La tasa es un dato del dia; lo que se cobra de mas es un
 * dato de la orden.
 *
 * Vive en un trait porque VentaRepuestoCreate y VentaRepuestoEdit tenian esta
 * aritmetica copiada palabra por palabra.
 */
trait VentaRepuestoTotalBsTrait
{
    /** La conversion limpia, sin el ajuste. */
    public function baseBs(): float
    {
        $total = $this->numero($this->ventaRepuesto['total'] ?? 0);
        $tipoCambio = $this->numero($this->ventaRepuesto['tipo_cambio'] ?? 0);

        return round($total * $tipoCambio, 2);
    }

    /** El ajuste tal cual esta hoy en el formulario. */
    public function ajusteBs(): float
    {
        return round($this->numero($this->ventaRepuesto['ajuste_bs'] ?? 0), 2);
    }

    /** El bloque del ajuste solo se dibuja si hay algo que contar. */
    public function hayAjuste(): bool
    {
        return abs($this->ajusteBs()) >= 0.01;
    }

    /**
     * Teclear el total en Bs define el ajuste, no la tasa.
     */
    public function aplicarTotalBs($value): void
    {
        // Campo vaciado para reteclear: con wire:model.live el borrado llega
        // como '' y no hay nada que despejar. No se toca el ajuste; el
        // siguiente recalculo devuelve la coherencia.
        if (!is_numeric($value)) {
            $this->ventaRepuesto['total_bs'] = 0;
            return;
        }

        $this->ventaRepuesto['ajuste_bs'] = round((float) $value - $this->baseBs(), 2);
        $this->sincronizarTotalBs();
    }

    /**
     * total_bs = base + ajuste. Lo llama el final de recalcularTotales(), asi
     * que agregar un repuesto o mover la tasa rehace la base y conserva el
     * ajuste que ya se habia pactado con el cliente.
     */
    public function sincronizarTotalBs(): void
    {
        $ajuste = $this->ajusteBs();

        $this->ventaRepuesto['ajuste_bs'] = $ajuste;
        $this->ventaRepuesto['total_bs'] = round($this->baseBs() + $ajuste, 2);
    }

    /** Volver a la conversion limpia sin calcular la base a mano. */
    public function limpiarAjuste(): void
    {
        $this->ventaRepuesto['ajuste_bs'] = 0;
        $this->sincronizarTotalBs();
    }

    /** Un campo a medio teclear llega como '' o null: vale 0, no rompe. */
    private function numero($valor): float
    {
        return is_numeric($valor) ? (float) $valor : 0.0;
    }
}
