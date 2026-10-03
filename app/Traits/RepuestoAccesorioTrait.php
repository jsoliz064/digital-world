<?php

namespace App\Traits;

use App\Enums\RepuestoTipo;
use App\Models\ProductoModelo;
use App\Models\RepuestoCategoria;

/**
 * Lo que un accesorio NO tiene.
 *
 * Fabricante, modelo, categoria y color describen una pieza de reparacion: son
 * atributos del telefono al que encaja. Un accesorio no es pieza de nada, asi
 * que esas columnas se esconden del formulario y se guardan en NULL.
 *
 * Vive en un trait porque RepuestoCreateModal y RepuestoEditModal son gemelos
 * literales y la lista de campos tiene que ser una sola: el dia que se agregue
 * un atributo de repuesto, se anade aqui y los dos modales lo esconden solos.
 */
trait RepuestoAccesorioTrait
{
    /** @return string[] Columnas que solo tienen sentido en una pieza de reparacion. */
    private function camposSoloRepuesto(): array
    {
        return ['fabricante', 'producto_modelo_id', 'repuesto_categoria_id', 'color', 'color_hex'];
    }

    /** Lo pregunta la vista para esconder esos campos. */
    public function esAccesorio(): bool
    {
        return ($this->repuesto['tipo'] ?? null) === RepuestoTipo::Accesorio->value;
    }

    /**
     * Cambiar el tipo en el formulario vacia lo que deja de verse. Livewire
     * resuelve este nombre desde la ruta `repuesto.tipo` del select: el punto
     * pasa a guion bajo y el conjunto a studly.
     */
    public function updatedRepuestoTipo(): void
    {
        $this->limpiarCamposDeAccesorio();
    }

    /**
     * Se limpia aqui y no solo en la vista: un repuesto convertido en accesorio
     * se quedaba con un modelo y un color que ya nadie podia ver ni corregir, y
     * la tabla del listado los seguia pintando.
     */
    protected function limpiarCamposDeAccesorio(): void
    {
        if (!$this->esAccesorio()) {
            return;
        }

        foreach ($this->camposSoloRepuesto() as $campo) {
            $this->repuesto[$campo] = null;
        }
    }

    /**
     * Los catalogos que alimentan los selects de modelo y categoria.
     *
     * Van en render() como variables de vista, y no en propiedades publicas:
     * antes se cargaban en __construct(), que en Livewire corre en CADA
     * hidratacion, asi que las dos colecciones completas viajaban en el payload
     * del modal en cada request -- tambien cuando el articulo es un accesorio y
     * los selects ni se pintan. Es lo que pide el CLAUDE.md: los catalogos en
     * render(), y con el guard del modal cerrado, 0 consultas.
     *
     * @return array{modelos: \Illuminate\Support\Collection, categorias: \Illuminate\Support\Collection}
     */
    protected function catalogosDePieza(): array
    {
        if (!$this->openModal || $this->esAccesorio()) {
            return ['modelos' => collect(), 'categorias' => collect()];
        }

        return [
            'modelos' => ProductoModelo::orderBy('nombre')->get(),
            'categorias' => RepuestoCategoria::orderBy('nombre')->get(),
        ];
    }

    /** Un formulario no valida lo que ni siquiera muestra. */
    protected function sinReglasDeRepuesto(array $reglas): array
    {
        foreach ($this->camposSoloRepuesto() as $campo) {
            unset($reglas["repuesto.{$campo}"]);
        }

        return $reglas;
    }
}
