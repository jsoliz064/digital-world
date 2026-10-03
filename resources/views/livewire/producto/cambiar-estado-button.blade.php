<div>
    {{-- Ahora usamos la variable $canChangeState que pasamos desde la tabla --}}
    @if($canChangeState)
        {{-- Si el usuario puede cambiar el estado, el botón es funcional --}}
        <button wire:click="cambiarEstado({{ $id }})" type="button" 
            class="flex items-center justify-center cursor-pointer hover:scale-105 transition-transform"
            style="border: 1px solid {{ $color }}; border-radius: 5px; padding: 5px">
            <span style="color: {{ $color }}">{{ $estado }}</span>
        </button>
    @else
        {{-- Si no, se muestra un span no interactivo con el mismo estilo --}}
        <span class="flex items-center justify-center"
            style="border: 1px solid {{ $color }}; border-radius: 5px; padding: 5px">
            <span style="color: {{ $color }}">{{ $estado }}</span>
        </span>
    @endcan
</div>
