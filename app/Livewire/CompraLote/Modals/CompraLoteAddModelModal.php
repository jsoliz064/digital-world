<?php

namespace App\Livewire\CompraLote\Modals;

use App\Enums\ProductoColor;
use App\Enums\ProductoEstado;
use App\Enums\ProductoVersion;
use App\Models\Producto;
use App\Models\Bitacora;
use App\Models\ProductoModelo;
use App\Models\ProductoModeloAlmacenamiento;
use App\Models\ProductoReparacion;
use App\Models\Sucursal;
use App\Models\Tecnicos;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CompraLoteAddModelModal extends Component
{

    public $openModal = false;
    public $selectedModel;
    public $compra;

    // Form fields
    public $almacenamiento;
    public $color;
    public $version;
    public $imei;
    public $bateria_porcentaje = 0;
    public $disponible_catalogo = false;
    public $sin_reparacion = false;
    public $sucursal_id = 1;

    public $costo_unidad = 0;
    public $costo_envio = 40;
    public $costo_total = 0;

    public $precio_cliente = 0;
    public $precio_vendedor = 0;
    public $descripcion;
    public $status = 'Inventario';
    public $tecnico_selected;
    public $tecnicos = [];
    public $sucursales = [];
    public $colores = [];
    public $estadosProducto = [];

    public $photos = [];

    public $detalles = [
        'Batería cambiada',
        'Borde un poco desgastado',
        'Con Bandeja Chip',
        'Face ID',
        'Mensaje de pantalla pieza desconocida',
        'Mensaje de batería desconocida',
        'Mensaje de pantalla pieza original',
        'Mensaje de batería original',
        'Mensaje de tarjeta madre original',
        'Mensaje cámara true deep',
        'Pantalla genérica',
        'Pantalla cambiada (original)',
        'Pequeña grieta adelante',
        'Pequeña grieta en la tapa',
        'Punto innotable en la pantalla',
        'Punto en la pantalla',
        'Rayones en la pantalla',
        'R sim',
        'Sin Face ID',
        'MDM',
    ];

    public $detallesSeleccionados = [];
    public $detallesText;
    public $estado_grado = 10;

    protected $listeners = ['photoCaptured'];
    public $currentPhotoIndex = 0;

    protected function rules()
    {
        return [
            'almacenamiento' => 'required',
            'color' => 'required',
            'version' => 'nullable',
            'imei' => 'required|string|min:1|max:20|unique:productos,imei',
            'bateria_porcentaje' => 'required|numeric|min:1|max:100',
            'costo_unidad' => 'required|numeric|min:0|decimal:0,2',
            'costo_envio' => 'required|numeric|min:0|decimal:0,2',
            'costo_total' => 'required|numeric|min:0|decimal:0,2',
            'precio_cliente' => 'required|numeric|min:0|decimal:0,2|gte:costo_unidad',
            'precio_vendedor' => 'required|numeric|min:0|decimal:0,2|gte:costo_unidad',
            'descripcion' => 'nullable|string',
            'disponible_catalogo' => 'required',
            'sin_reparacion' => 'required',
            'estado_grado' => 'required',
            'sucursal_id' => 'required|exists:sucursales,id,activa,1',
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'numeric' => 'El campo :attribute debe ser numérico.',
            'digits_between' => 'El IMEI debe tener entre 15 y 17 dígitos.',
            'unique' => 'Este IMEI ya existe en el sistema.',
            'min' => 'El valor mínimo para :attribute es :min.',
            'max' => 'El valor máximo para :attribute es :max.',
            'decimal' => 'El campo :attribute debe tener máximo 2 decimales.',
            'gte' => 'El precio al cliente debe ser mayor o igual al costo.',
            'almacenamiento.required' => 'Debe seleccionar un almacenamiento.',
            'color.required' => 'Debe seleccionar un color.',
            'estado_grado.required' => 'Debe seleccionar una condición.',
            'sucursal_id.required' => 'Debe seleccionar una sucursal',
            'sucursal_id.exists' => 'La sucursal elegida no existe o está desactivada.',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'almacenamiento' => 'almacenamiento',
            'color' => 'color',
            'imei' => 'IMEI',
            'bateria_porcentaje' => 'porcentaje de batería',
            'costo_unidad' => 'costo unitario',
            'costo_envio' => 'costo de envío',
            'costo_total' => 'costo total',
            'precio_cliente' => 'precio al cliente',
            'precio_vendedor' => 'precio al vendedor',
            'estado_grado' => 'condición del producto',
        ];
    }

    public function mount($compra_lote)
    {
        $this->compra = $compra_lote;
        $this->colores = ProductoColor::cases();
        $this->estadosProducto = ProductoEstado::cases();
        $this->tecnicos = Tecnicos::all();
        $this->sucursales = Sucursal::activas()->orderBy('nombre')->get();
    }

    public function render()
    {
        return view('livewire.compra-lote.modals.compra-lote-add-model-modal');
    }

    public function photoCapturedCreate($photoData)
    {
        $this->photos[] = $photoData;
        $this->dispatch('notify', 'Foto agregada correctamente');
    }

    public function removePhoto()
    {
        if (!isset($this->photos[$this->currentPhotoIndex])) {
            return;
        }

        unset($this->photos[$this->currentPhotoIndex]);
        $this->photos = array_values($this->photos);

        if (empty($this->photos)) {
            $this->currentPhotoIndex = 0;
            return;
        }

        $this->currentPhotoIndex = max(0, $this->currentPhotoIndex - 1);

        $this->currentPhotoIndex = min($this->currentPhotoIndex, count($this->photos) - 1);
    }

    #[On('openModalSelector')]
    public function open($modelId)
    {
        $this->selectedModel = ProductoModelo::with('almacenamientos')->find($modelId);
        $this->resetForm();
        $this->openModal = true;

        $defaultStorage = collect($this->selectedModel->almacenamientos)
            ->firstWhere('almacenamiento', '128GB');

        $productoModeloAlmacenamiento = ProductoModeloAlmacenamiento::where('producto_modelo_id', $this->selectedModel->id)
            ->where('almacenamiento', $defaultStorage->almacenamiento)
            ->first();

        if ($defaultStorage) {
            $this->almacenamiento = '128GB';
            $this->costo_unidad = $productoModeloAlmacenamiento->costo;
            $this->precio_vendedor = $productoModeloAlmacenamiento->precio;
            $this->precio_cliente = $productoModeloAlmacenamiento->precio_cliente;
            $this->costo_total = $this->costo_unidad + $this->costo_envio;
        }

        $this->generateDescription();
    }

    public function updatedAlmacenamiento($value)
    {
        $selectedStorage = collect($this->selectedModel->almacenamientos)
            ->firstWhere('almacenamiento', $value);

        $productoModeloAlmacenamiento = ProductoModeloAlmacenamiento::where('producto_modelo_id', $this->selectedModel->id)
            ->where('almacenamiento', $selectedStorage->almacenamiento)
            ->first();

        if ($selectedStorage) {
            $this->costo_unidad = $productoModeloAlmacenamiento->costo;
            $this->precio_vendedor = $productoModeloAlmacenamiento->precio;
            $this->precio_cliente = $productoModeloAlmacenamiento->precio_cliente;
            $this->costo_total = $this->costo_unidad + $this->costo_envio;
        }

        $this->generateDescription();
    }
    public function resetForm()
    {
        $this->resetErrorBag();

        $latestProduct = Producto::where('compra_id', $this->compra->id)->orderby('id', 'desc')->first();
        $this->costo_envio = $latestProduct ? $latestProduct->costo_envio : 40;

        $this->costo_unidad = $latestProduct ? $latestProduct->costo_unidad : 0;
        $this->precio_cliente = $latestProduct ? $latestProduct->precio_cliente : 0;
        $this->precio_vendedor = $latestProduct ? $latestProduct->precio_vendedor : 0;


        $this->detallesSeleccionados = [];
        $this->detallesText = '';
        $this->estado_grado = $latestProduct ? $latestProduct->estado_grado : 'A';
        $this->color = $latestProduct ? $latestProduct->color : ProductoColor::Negro->value;
        // $this->color = ProductoColor::Negro->value;
        $this->version = $latestProduct ? $latestProduct->version : '';
        $this->imei = '';
        $this->bateria_porcentaje = $latestProduct ? $latestProduct->bateria_porcentaje : 0;
        $this->descripcion = '';
        $this->status = $latestProduct ? $latestProduct->estado : ProductoEstado::Inventario->value;
        $this->tecnico_selected = null;
        $this->photos = [];
        $this->sucursal_id = $latestProduct ? $latestProduct->sucursal_id : 1;
    }

    public function closeModal()
    {
        $this->openModal = false;
    }

    public function updated($propertyName)
    {
        if (in_array($propertyName, [
            'almacenamiento',
            'color',
            'imei',
            'bateria_porcentaje',
        ])) {
            $this->generateDescription();
        }
    }

    protected function generateDescription()
    {
        $parts = [
            $this->selectedModel->nombre ?? '',
            $this->color ? "color {$this->color}" : '',
            $this->almacenamiento ? "de {$this->almacenamiento}" : '',
            isset($this->bateria_porcentaje) ? "con {$this->bateria_porcentaje}% de batería" : '',
            $this->imei ? "IMEI: {$this->imei}" : '',
        ];

        $this->descripcion = trim(implode(' ', array_filter($parts)));
    }

    protected function saveProduct()
    {
        $this->validate();

        // Un alta de telefono no necesita clave de idempotencia sintetica: su
        // clave natural es el IMEI, y ahora lo respalda el indice unico
        // productos_imei_unico. Un reintento -- doble clic, o la red cortada
        // justo al guardar-- choca contra el indice en lugar de dejar dos filas
        // compitiendo por ser el mismo aparato. Antes solo lo frenaba la regla
        // `unique:` de validate(), que valida con un SELECT previo: dos
        // pestanas a la vez la pasaban las dos.
        try {
            $producto = DB::transaction(function () {
                // make() + anotar() + save() y no create(): para anotarle el
                // alta hace falta la instancia ANTES de guardarla, y asi el
                // observer escribe una sola fila con el estado con el que nace,
                // la frase y el retrato completo del telefono.
                $producto = Producto::make([
                    'producto_modelo_id' => $this->selectedModel->id,
                    'almacenamiento' => $this->almacenamiento,
                    'color' => $this->color,
                    'version' => $this->version,
                    'imei' => $this->imei,
                    'bateria_porcentaje' => $this->bateria_porcentaje,
                    'costo_unidad' => $this->costo_unidad,
                    'costo_envio' => $this->costo_envio,
                    'costo_total' => $this->costo_total,
                    'precio_cliente' => $this->precio_cliente,
                    'precio_cliente_ant' => 0,
                    'precio_vendedor' => $this->precio_vendedor,
                    'precio_vendedor_ant' => 0,
                    'estado' => $this->status,
                    'descripcion' => $this->descripcion,
                    'detalles' => $this->detallesText,
                    'estado_grado' => $this->estado_grado,
                    'compra_id' => $this->compra->id,
                    'disponible_catalogo' => $this->disponible_catalogo,
                    'sin_reparacion' => $this->sin_reparacion,
                    'sucursal_id' => $this->sucursal_id,
                ]);

                // Toda alta deja su fila, con el estado con el que nace. Antes
                // solo la escribia la rama de Reparacion, asi que un telefono
                // creado en Fuera, Transito o Roto nacia sin traza y el auditor
                // lo veia como "ultimo historial distinto del estado real".
                $producto->anotar($producto->estado, "Producto registrado en el lote #{$this->compra->id}");
                $producto->save();

                if ($this->status == ProductoEstado::Reparacion->value) {

                    $tecnico = Tecnicos::find($this->tecnico_selected);

                    $productoReparacion = ProductoReparacion::create([
                        'tecnico_id' => $tecnico->id,
                        'producto_id' => $producto->id,
                        'costo' => 0,
                        'fecha_entrega' => now()->format('Y-m-d'),
                    ]);

                    // Hecho suelto: la reparacion no es un cambio del producto,
                    // que ya nacio en Reparacion en la fila de arriba.
                    Bitacora::registrar(
                        $producto,
                        $this->status,
                        "Producto en reparacion con el tecnico {$tecnico->nombre}",
                        ['producto_reparacion_id' => $productoReparacion->id],
                    );
                }

                foreach ($this->photos as $photo) {
                    $producto->imagenes()->create([
                        'base64' => $photo
                    ]);
                }

                // Los totales del lote, DENTRO de la transaccion: fuera, un fallo
                // al recalcular dejaba el producto commiteado y la cabecera con los
                // totales viejos.
                $this->compra->recalculate();

                // El return que faltaba. Sin el, la closure devolvia null y
                // $producto era SIEMPRE null: hoy nadie usa el retorno, asi que era
                // una bomba de relojeria en lugar de un fallo visible.
                return $producto;
            });
        } catch (QueryException $e) {
            // 1062 sobre el IMEI: o se reintento este guardado, o el IMEI ya es
            // de otro telefono. Las dos se arreglan igual -- comprobar el
            // aparato-- y el mensaje lo dice en lugar de soltar el error crudo.
            if (($e->errorInfo[1] ?? null) === 1062 && str_contains($e->getMessage(), 'imei')) {
                $this->addError('imei', "El IMEI {$this->imei} ya esta registrado. Si acabas de guardarlo, revisa la lista antes de repetir.");

                return null;
            }

            throw $e;
        }

        $this->dispatch('loadModelCounts');

        return $producto;
    }

    public function saveAndClose()
    {
        // Si el alta no entro (IMEI repetido), el modal se queda abierto con el
        // error a la vista en lugar de cerrarse como si hubiera funcionado.
        if (!$this->saveProduct()) {
            return;
        }

        $this->closeModal();
        $this->dispatch('refreshProductoTable');
    }

    public function saveAndContinue()
    {
        if (!$this->saveProduct()) {
            return;
        }

        $this->dispatch('refreshProductoTable');
        toastr()->success('Se ha guardado el producto exitosamente');
        $this->resetForm();
    }

    public function updatedCostoUnidad()
    {
        $this->calculateCostoTotal();
    }

    public function updatedCostoEnvio()
    {
        $this->calculateCostoTotal();
    }

    private function calculateCostoTotal()
    {
        $this->costo_total = $this->costo_unidad + $this->costo_envio;
    }
}
