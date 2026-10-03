<?php

namespace App\Livewire\Repuesto\Modals;

use App\Enums\RepuestoTipo;
use App\Models\Repuesto;
use App\Traits\RepuestoAccesorioTrait;
use App\Traits\RepuestoStockSucursalTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Attributes\On;

class RepuestoCreateModal extends Component
{
    use RepuestoAccesorioTrait;
    use RepuestoStockSucursalTrait;

    public $openModal = false;

    /**
     * El tipo que fija la pantalla, o null si lo elige el usuario.
     *
     * Null es el caso de CompraRepuestoCreate, que declara este mismo modal para
     * dar de alta un articulo a mitad de una compra: ahi la compra puede llevar
     * repuestos y accesorios, asi que el tipo sigue siendo una eleccion. En
     * /inventario/repuestos y /inventario/accesorios lo fija la ruta, y antes
     * este modal forzaba 'Repuesto' siempre: crear desde la pantalla de
     * accesorios producia un articulo que desaparecia de la lista al guardar.
     */
    #[Locked]
    public ?string $tipo = null;

    public $repuesto = [];
    protected function rules()
    {
        $reglas = [
        'repuesto.tipo' => ['required', 'in:' . implode(',', RepuestoTipo::values())],
        'repuesto.nombre' => 'required|string|max:255',
        'repuesto.fabricante' => 'nullable|string|max:255',
        'repuesto.costo' => 'required|numeric|min:0',
        'repuesto.precio' => 'required|numeric|min:0',
        'repuesto.tipo_cambio' => 'required|numeric|min:1',
        'repuesto.producto_modelo_id' => [
            'nullable',
            'integer',
            'required_if:repuesto.tipo,' . RepuestoTipo::Repuesto->value,
        ],
        'repuesto.repuesto_categoria_id' => 'nullable|integer',
        // Sintaxis de array obligatoria: una regla regex: no puede ir en un
        // string delimitado por | porque Laravel partiria la propia regex.
        'repuesto.color' => ['nullable', 'string', 'max:50'],
        'repuesto.color_hex' => ['nullable', 'string', 'regex:/^#[A-Fa-f0-9]{6}$/'],
        ];

        // Un accesorio no ensena esos campos, asi que tampoco los valida.
        $reglas = array_merge($reglas, $this->reglasDeStock());

        return $this->esAccesorio() ? $this->sinReglasDeRepuesto($reglas) : $reglas;
    }

    protected $messages = [
        'repuesto.color_hex.regex' => 'El color debe ser un hexadecimal valido (ej: #1F2937).',
        'repuesto.nombre' => 'Debe ingresar un nombre',
        'repuesto.fabricante' => 'Debe ingresar un fabricante',
        'repuesto.costo' => 'Debe ingresar un costo',
        'repuesto.tipo_cambio' => 'Debe ingresar un tipo de cambio',
        'repuesto.precio' => 'Debe ingresar un precio',
        'stockSucursales.*.required' => 'Indique la cantidad de cada sucursal.',
        'stockSucursales.*.min' => 'El stock de una sucursal no puede ser negativo.',
        'repuesto.producto_modelo_id.required_if' => 'Debe seleccionar un modelo para un repuesto.',
        'repuesto.tipo.required' => 'Debe seleccionar si es repuesto o accesorio.',
    ];

    public function mount(?string $tipo = null): void
    {
        $this->tipo = $tipo !== null ? RepuestoTipo::from($tipo)->value : null;
    }

    #[On('openRepuestoCreateModal')]
    public function openModal()
    {
        $this->repuesto['tipo'] = $this->tipo ?? RepuestoTipo::Repuesto->value;
        $this->openModal = true;
    }

    public function store()
    {
        // Red de seguridad antes de validar: el select ya limpia al cambiar de
        // tipo, pero esto cubre cualquier camino que no pase por el.
        $this->limpiarCamposDeAccesorio();

        $this->validate();

        // `cantidad` ya no viaja en $this->repuesto: el total es la suma de la
        // subtabla y lo escribe el servicio. Si llegara, Repuesto lo descarta
        // por $guarded.
        unset($this->repuesto['cantidad']);

        $repuesto = DB::transaction(function () {
            $repuesto = Repuesto::create($this->repuesto);
            $this->guardarStockSucursales($repuesto->id);

            return $repuesto;
        });

        $this->dispatch('refreshRepuestoTable', $repuesto);
        toastr()->success('Repuesto creado exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset(['openModal', 'repuesto']);
    }

    public function render()
    {
        return view('livewire.repuesto.modals.repuesto-create-modal', $this->catalogosDePieza() + [
            'sucursalesDisponibles' => $this->sucursalesDisponibles(),
            'sucursalesPorId' => $this->sucursalesPorId(),
        ]);
    }
}
