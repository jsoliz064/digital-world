<?php

namespace App\Livewire\VentaRepuesto\Modals;

use App\Models\VentaRepuesto;
use App\Services\StockRepuestoService;
use Illuminate\Validation\ValidationException;
use DB;
use Livewire\Attributes\On;
use Livewire\Component;

class VentaRepuestoDestroyModal extends Component
{
    public $openModal = false;
    public ?VentaRepuesto $ventaRepuesto = null;
    public function render()
    {
        return view('livewire.venta-repuesto.modals.venta-repuesto-destroy-modal');
    }

    #[On('openVentaRepuestoDestroyModal')]
    public function openModal($id)
    {
        $this->ventaRepuesto = VentaRepuesto::find($id);
        if ($this->ventaRepuesto) {
            $this->openModal = true;
        } else {
            toastr()->error('No se pudo encontrar el detalle de la venta para eliminar.');
        }
    }

    public function destroy(): void
    {
        if (!$this->ventaRepuesto) {
            toastr()->error('Error: No se ha seleccionado ningún producto para eliminar.');
            return;
        }

        try {

            $stock = new StockRepuestoService();

            DB::transaction(function () use ($stock) {
                // Un repuesto cobrado desde una reparacion nunca descontó stock
                // en esta venta: la pieza salio del almacen cuando el tecnico la
                // monto, y sigue montada en el telefono aunque se borre el
                // cobro. Devolverlo inventaria una unidad.
                //
                // Ordenado por repuesto_id para no bloquear en InnoDB.
                $devolubles = $this->ventaRepuesto->detalles
                    ->reject->stockYaDescontado()
                    ->sortBy('repuesto_id');

                foreach ($devolubles as $detalle) {
                    // Por el servicio y a la sucursal DE LA LINEA. Antes era el
                    // unico de los dieciseis sitios que hacia un
                    // read-modify-write -- update(['cantidad' => $repuesto->cantidad + ...]) --
                    // en vez de una operacion atomica.
                    $stock->ingresar($detalle->repuesto_id, $detalle->sucursal_id, (int) $detalle->cantidad);
                }

                $stock->recalcularTotales();

                $this->ventaRepuesto->delete();
                $this->ventaRepuesto = null;

            });
            $this->dispatch('refreshVentaRepuestoTable');
            toastr()->success('Venta de repuestos eliminada exitosamente.');
            $this->closeModal();
        } catch (ValidationException $e) {
            // Los errores de stock pasan de largo: este catch se comia la
            // excepcion y mostraba un toastr genérico, asi que el usuario no
            // sabia por qué habia fallado.
            throw $e;
        } catch (\Exception $e) {
            toastr()->error('Ocurrió un error al intentar eliminar la venta de repuestos. Por favor, inténtelo de nuevo.');
        }
    }
    public function closeModal()
    {
        $this->reset();
    }
}
