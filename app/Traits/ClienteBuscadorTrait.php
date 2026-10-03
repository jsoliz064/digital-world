<?php

namespace App\Traits;

use App\Models\Cliente;
use Livewire\Attributes\On;

/**
 * Elegir el cliente de una venta: buscar, mirar el catalogo, o crearlo al vuelo.
 *
 * Lo comparten las CINCO puertas de venta -- VentaCreate, VentaEdit,
 * VentaRepuestoCreate, VentaRepuestoEdit y la rama Vendido de
 * ProductoEstadoModal -- porque antes cada una pedia el cliente con un input de
 * texto suelto y cinco reglas de validacion distintas entre si. Un trait y no
 * cinco copias: el fallo recurrente de este repo es la copia que divergio.
 *
 * TRES CAMINOS DE ENTRADA, uno al lado del otro, como ya hace la pantalla de
 * compra de repuestos con el catalogo:
 *   1. teclear  -> updatedSearchCliente() y el desplegable
 *   2. mirar    -> openClienteSelector() y ClienteSelectorModal
 *   3. crear    -> openClienteCreateModal(), que devuelve el id y autoselecciona
 *
 * EL COMPONENTE QUE LO USE debe implementar fijarCliente() y clienteIdElegido():
 * cada pantalla guarda la cabecera en una propiedad con otro nombre ($venta,
 * $ventaRepuesto, $vendido), asi que el trait no puede saber donde escribir. Es
 * el mismo reparto que RepuestoBuscadorTrait hace con sucursalDelDocumento().
 */
trait ClienteBuscadorTrait
{
    public $searchCliente = '';
    public $filteredClientes = [];

    /**
     * Guarda el cliente elegido en la cabecera del documento.
     *
     * La implementacion escribe DOS claves: `cliente_id`, que es el enlace, y
     * `cliente`, el nombre que queda congelado en el documento como archivo de lo
     * que se vendio y a quien. Ver el docblock de la migracion.
     */
    abstract protected function fijarCliente(?Cliente $cliente): void;

    /** El id del cliente ya elegido, para excluirlo y para pintar el estado. */
    abstract public function clienteIdElegido(): ?int;

    /**
     * El desplegable de sugerencias.
     *
     * La guarda de termino vacio NO es opcional: sin ella, borrar la caja dispara
     * un `like '%%'` y lista los diez primeros clientes como si fueran
     * resultados de la busqueda. Es el bug que RepuestoBuscadorTrait documenta
     * haber heredado de sus cuatro copias.
     */
    public function updatedSearchCliente($value)
    {
        $termino = trim((string) $value);

        if ($termino === '') {
            $this->filteredClientes = [];

            return;
        }

        // Los comodines se neutralizan para que un '%' tecleado se busque literal.
        $like = '%' . addcslashes($termino, '%_\\') . '%';

        $this->filteredClientes = Cliente::query()
            // Un solo cuadro para nombre, CI y telefono: en el mostrador no se
            // sabe de antemano por cual de los tres se esta buscando.
            ->where(fn($q) => $q->where('nombre', 'like', $like)
                ->orWhere('ci', 'like', $like)
                ->orWhere('telefono', 'like', $like))
            ->orderBy('nombre')
            ->take(10)
            ->get();
    }

    /** Camino del desplegable y del selector: llega un id. */
    public function selectCliente($id): void
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return;
        }

        $this->fijarCliente($cliente);

        // Limpiar el termino es lo que cierra el desplegable: el blade lo
        // condiciona a @if (!empty($searchCliente)).
        $this->searchCliente = '';
        $this->filteredClientes = [];
    }

    /** El cliente es opcional, asi que se puede quitar. */
    public function quitarCliente(): void
    {
        $this->fijarCliente(null);
        $this->searchCliente = '';
        $this->filteredClientes = [];
    }

    public function openClienteSelector(): void
    {
        $this->dispatch('openClienteSelectorModal', elegido: $this->clienteIdElegido());
    }

    /**
     * Abre el modal de alta con lo que ya se habia tecleado, para no escribirlo
     * dos veces: el caso real es teclear un nombre, no encontrarlo, y crearlo.
     */
    public function openClienteCreateModal(): void
    {
        $this->dispatch('openClienteCreateModal', nombre: trim((string) $this->searchCliente));
    }

    #[On('clienteSeleccionado')]
    public function recibirClienteSeleccionado(int $id): void
    {
        $this->selectCliente($id);
    }

    /**
     * El cliente recien creado queda elegido sin pasar por el buscador.
     *
     * Escucha `clienteCreado` y NO `refreshClienteTable`, aunque el repo use ese
     * truco en CompraRepuestoCreate: refreshClienteTable lo despacharian tambien
     * los modales de editar y eliminar SIN argumento, y este metodo reventaria
     * por falta de parametro en cuanto coincidieran en pantalla.
     */
    #[On('clienteCreado')]
    public function recibirClienteCreado(int $id): void
    {
        $this->selectCliente($id);
    }
}
