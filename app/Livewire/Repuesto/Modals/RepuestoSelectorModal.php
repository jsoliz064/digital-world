<?php

namespace App\Livewire\Repuesto\Modals;

use App\Enums\RepuestoColor;
use App\Enums\RepuestoTipo;
use App\Models\ProductoModelo;
use App\Models\Repuesto;
use App\Models\RepuestoCategoria;
use App\Models\Sucursal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal para elegir articulos del catalogo con buscador, filtros y
 * multi-seleccion. Lo usan las pantallas de venta Y de compra de repuestos,
 * por eso vive en el namespace del catalogo y no en el de ventas.
 *
 * Contrato: despacha SOLO los ids (evento `repuestosSeleccionados`) y cada
 * padre construye su propia linea reutilizando su selectRepuesto(). Create y
 * Edit las arman distinto (Edit marca 'id' => null; Create promedia el tipo de
 * cambio), asi que un modal que enviara filas ya construidas tendria que
 * conocer ambos dialectos.
 */
class RepuestoSelectorModal extends Component
{
    /** Valor centinela del filtro "Sin color" (no puede chocar con un nombre real). */
    public const SIN_COLOR = '__sin_color__';

    public bool $openModal = false;

    public string $search = '';
    public string $filtroTipo = '';
    public string $filtroCategoria = '';
    public string $filtroModelo = '';
    public string $filtroColor = '';

    public array $seleccionados = [];
    public array $excluidos = [];

    /**
     * La sucursal del documento que abrio el selector.
     *
     * #[Locked] porque decide QUE stock se muestra y, con $soloConStock, que
     * articulos se pueden elegir: sin el candado un payload la cambia y mira
     * -- y se lleva -- las unidades de otra tienda.
     *
     * Null significa "sin dimension de sucursal": se pinta el total global,
     * como antes. Ninguna de las cuatro pantallas lo usa asi hoy, pero el modal
     * tiene que seguir siendo abrible sin ella.
     */
    #[Locked]
    public ?int $sucursalId = null;

    /**
     * Si un articulo sin stock en esa sucursal se puede elegir.
     *
     * Es una dimension SEPARADA de $sucursalId y no se deduce de ella: una
     * compra tambien tiene sucursal, y ahi el cero no estorba porque es
     * justamente lo que va a entrar. Las ventas lo pasan en true.
     */
    #[Locked]
    public bool $soloConStock = false;

    public int $pagina = 1;
    public int $porPagina = 10;

    /**
     * Los excluidos llegan frescos en CADA apertura y el modal se cierra al
     * agregar, asi que nunca sobrevive a una mutacion de $detalles del padre y
     * su lista no puede quedar obsoleta. La sucursal viaja igual, por el evento:
     * el modal se declara una sola vez en el blade del indice y no sabe de que
     * documento lo abrieron.
     */
    #[On('openRepuestoSelectorModal')]
    public function openModal(array $excluidos = [], ?int $sucursalId = null, bool $soloConStock = false): void
    {
        $this->resetEstado();
        $this->excluidos = array_map('intval', $excluidos);
        $this->sucursalId = $sucursalId;
        $this->soloConStock = $soloConStock;
        $this->openModal = true;
    }

    public function updatedSearch(): void { $this->pagina = 1; }
    /**
     * Al pasar a Accesorio se limpian los filtros de pieza.
     *
     * Tipo = Accesorio mas una Categoria, un Modelo o un Color es una
     * combinacion IMPOSIBLE por construccion -- un accesorio los tiene en NULL
     * (ver RepuestoAccesorioTrait) -- asi que daba cero resultados sin decir por
     * que. La vista los esconde; esto evita que uno quede activo e invisible y
     * siga vaciando la lista.
     */
    public function updatedFiltroTipo(): void
    {
        $this->pagina = 1;

        if ($this->soloAccesorios()) {
            $this->reset(['filtroCategoria', 'filtroModelo', 'filtroColor']);
        }
    }

    /** Si el filtro de tipo esta restringido a accesorios. */
    public function soloAccesorios(): bool
    {
        return $this->filtroTipo === RepuestoTipo::Accesorio->value;
    }
    public function updatedFiltroCategoria(): void { $this->pagina = 1; }
    public function updatedFiltroModelo(): void { $this->pagina = 1; }
    public function updatedFiltroColor(): void { $this->pagina = 1; }

    public function irAPagina(int $p): void { $this->pagina = max(1, $p); }

    /**
     * Marca o desmarca una fila. Es el UNICO camino: el checkbox de la fila es
     * decorativo (sin wire:model) a proposito, porque con el enlace puesto en la
     * casilla Y el wire:click en el <tr> un clic sobre la casilla disparaba los
     * dos y el cambio se anulaba solo.
     *
     * Guarda ENTEROS, que es lo que agregarSeleccionados() normaliza despues.
     */
    public function alternar(int $id): void
    {
        $actuales = array_map('intval', $this->seleccionados);
        $posicion = array_search($id, $actuales, true);

        if ($posicion !== false) {
            unset($actuales[$posicion]);
            $this->seleccionados = array_values($actuales);
            return;
        }

        // Cortesia, NO garantia: la fila bloqueada ya no lleva wire:click, pero
        // el id llega del cliente. Quien impide de verdad el alta sin stock es la
        // revalidacion contra la base de agregarSeleccionados().
        if ($this->estaBloqueado($id)) {
            toastr()->error('Sin stock en ' . $this->nombreSucursal() . '.');
            return;
        }

        $actuales[] = $id;
        $this->seleccionados = array_values($actuales);
    }

    /** Si el articulo no puede elegirse por no tener stock en la sucursal. */
    public function estaBloqueado(int $id): bool
    {
        if (!$this->soloConStock || $this->sucursalId === null) {
            return false;
        }

        return !Repuesto::whereKey($id)
            ->whereHas('stocks', fn($q) => $q->where('sucursal_id', $this->sucursalId)
                ->where('cantidad', '>', 0))
            ->exists();
    }

    public function limpiarSeleccion(): void { $this->seleccionados = []; }

    public function limpiarFiltros(): void
    {
        $this->reset(['search', 'filtroTipo', 'filtroCategoria', 'filtroModelo', 'filtroColor', 'pagina']);
    }

    public function agregarSeleccionados(): void
    {
        $ids = array_values(array_unique(array_map('intval', $this->seleccionados)));

        if (empty($ids)) {
            toastr()->warning('Seleccione al menos un repuesto.');
            return;
        }

        // El `disabled` del checkbox NO es una guarda: el array de seleccionados
        // llega del cliente y un payload puede traer un id que la pantalla
        // pintaba deshabilitado. Se revalida contra la base.
        if ($this->soloConStock && $this->sucursalId !== null) {
            $sinStock = Repuesto::whereIn('id', $ids)
                ->whereDoesntHave('stocks', fn($q) => $q->where('sucursal_id', $this->sucursalId)
                    ->where('cantidad', '>', 0))
                ->pluck('nombre');

            if ($sinStock->isNotEmpty()) {
                toastr()->error('Sin stock en ' . $this->nombreSucursal() . ': ' . $sinStock->implode(', '));
                return;
            }
        }

        $this->dispatch('repuestosSeleccionados', ids: $ids);
        $this->closeModal();
    }

    /** Para los mensajes y la cabecera de la columna de stock. */
    public function nombreSucursal(): string
    {
        if ($this->sucursalId === null) {
            return '';
        }

        return Sucursal::whereKey($this->sucursalId)->value('nombre') ?? ('#' . $this->sucursalId);
    }

    public function closeModal(): void
    {
        $this->openModal = false;
        $this->resetEstado();
    }

    /** Siempre reset dirigido, nunca $this->reset() a secas. */
    protected function resetEstado(): void
    {
        $this->reset([
            'search', 'filtroTipo', 'filtroCategoria', 'filtroModelo', 'filtroColor',
            'seleccionados', 'excluidos', 'pagina', 'sucursalId', 'soloConStock',
        ]);
    }

    protected function repuestosQuery(): LengthAwarePaginator
    {
        return Repuesto::query()
            // modelo y categoria solo si pueden existir: en accesorios son NULL.
            ->when(!$this->soloAccesorios(), fn($q) => $q->with(['modelo:id,nombre', 'categoria:id,nombre']))
            // El stock de la sucursal, cargado de una vez: stockEn() mira
            // relationLoaded(), asi que sin esto la tabla hace una consulta por
            // cada una de las diez filas de la pagina.
            ->when($this->sucursalId !== null, fn($q) => $q->with([
                'stocks' => fn($s) => $s->where('sucursal_id', $this->sucursalId),
            ]))
            ->when($this->excluidos, fn($q) => $q->whereNotIn('id', $this->excluidos))
            ->when($this->search !== '', function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(fn($sub) => $sub->where('nombre', 'like', $term)
                    ->orWhere('fabricante', 'like', $term));
            })
            ->deTipo($this->filtroTipo ?: null)
            ->when(
                $this->filtroCategoria !== '',
                fn($q) => $q->where('repuesto_categoria_id', $this->filtroCategoria)
            )
            ->when(
                $this->filtroModelo !== '',
                fn($q) => $q->where('producto_modelo_id', $this->filtroModelo)
            )
            ->when(
                $this->filtroColor === self::SIN_COLOR,
                fn($q) => $q->whereNull('color')
            )
            ->when(
                $this->filtroColor !== '' && $this->filtroColor !== self::SIN_COLOR,
                fn($q) => $q->where('color', $this->filtroColor)
            )
            ->orderBy('nombre')
            // Paginacion manual: WithPagination registra `page` en el query
            // string con history:true y reescribiria la URL de la venta.
            ->paginate($this->porPagina, ['*'], 'page', $this->pagina);
    }

    public function render()
    {
        // Los catalogos van en render(), NUNCA en mount(): son variables de
        // vista, no propiedades publicas, asi que reset() no puede vaciarlos ni
        // viajan en el payload de Livewire. Con el modal cerrado, 0 consultas.
        $data = ['categorias' => collect(), 'modelos' => collect(), 'repuestos' => null];

        if ($this->openModal) {
            // Los dos catalogos solo si los selects se van a pintar.
            $dePieza = !$this->soloAccesorios();

            $data = [
                'categorias' => $dePieza ? RepuestoCategoria::orderBy('nombre')->get() : collect(),
                'modelos' => $dePieza ? ProductoModelo::orderBy('created_at')->get() : collect(),
                'repuestos' => $this->repuestosQuery(),
            ];
        }

        return view(
            'livewire.repuesto.modals.repuesto-selector-modal',
            $data + [
                'colores' => $this->soloAccesorios() ? collect() : RepuestoColor::palette(),
                'sinColor' => self::SIN_COLOR,
                'tipos' => RepuestoTipo::toSelectArray(),
                'soloAccesorios' => $this->soloAccesorios(),
                'nombreSucursal' => $this->nombreSucursal(),
                'umbralBajoStock' => Repuesto::UMBRAL_BAJO_STOCK,
            ]
        );
    }
}
