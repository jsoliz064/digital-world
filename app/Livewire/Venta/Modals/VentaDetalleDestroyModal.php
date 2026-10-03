<?php

namespace App\Livewire\Venta\Modals;

use App\Models\VentaProducto;
use App\Services\AnulacionVentaService;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class VentaDetalleDestroyModal extends Component
{
    
    public $openModal = false;
    public ?VentaProducto $ventaProducto = null;

    public function render()
    {
        return view('livewire.venta.modals.venta-detalle-destroy-modal');
    }

    #[On('openVentaDetalleDestroyModal')]
    public function openModal($id)
    {
        $this->ventaProducto = VentaProducto::with('producto', 'venta')->find($id);
        if ($this->ventaProducto) {
            $this->openModal = true;
        } else {
            toastr()->error('No se pudo encontrar el detalle de la venta para eliminar.');
        }
    }

    public function eliminarDetalle(): void
    {
        if (!$this->ventaProducto) {
            toastr()->error('Error: No se ha seleccionado ningún producto para eliminar.');
            return;
        }


        // Se relee de la base en vez de confiar en el modelo hidratado: entre
        // abrir el modal y confirmar, otro pudo anular este mismo detalle, y
        // Livewire rehidrata por id sin volver a comprobar que siga ahi.
        $detalle = VentaProducto::with('producto', 'venta')->find($this->ventaProducto->id);

        if (!$detalle) {
            toastr()->info('Ese detalle ya se habia anulado. No se hizo nada.');
            $this->dispatch('refreshVentaDetalleTable');
            $this->dispatch('refreshVentaDetalle');
            $this->closeModal();

            return;
        }

        try {
            DB::transaction(fn() => app(AnulacionVentaService::class)->anularDetalle($detalle));
        } catch (ValidationException $e) {
            // El mensaje de la precondicion ("el producto ya esta en X") tiene
            // que llegar al usuario: antes lo tragaba el catch generico y solo
            // se veia "ocurrio un error", sin saber si se habia aplicado algo.
            toastr()->error(implode(' ', $e->validator->errors()->all()));

            return;
        } catch (\Throwable $e) {
            // Se registra: el catch silencioso de antes borraba la causa, y
            // este metodo toca tres tablas.
            Log::error('Fallo al anular el detalle de venta', [
                'venta_producto_id' => $detalle->id,
                'excepcion' => $e,
            ]);

            toastr()->error('Ocurrió un error al intentar eliminar el detalle. Por favor, inténtelo de nuevo.');

            return;
        }

        $this->dispatch('refreshVentaDetalleTable');
        $this->dispatch('refreshVentaDetalle');
        toastr()->success('Detalle de venta eliminado exitosamente.');
        $this->closeModal();
    }
    public function closeModal()
    {
        $this->reset();
    }
}
