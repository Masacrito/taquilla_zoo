{{--
    Selector de cantidad: [−] número [+].

    El campo sigue siendo un <input type="number"> —teclado numérico en el
    celular, flechas ↑↓ nativas— y los botones son solo otra forma de moverlo.
    El comportamiento (seleccionar al enfocar, rueda, normalizar) vive en el
    script de la pantalla que lo usa, enganchado por `data-cantidad` y
    `data-paso`.
--}}
@props(['nombre', 'etiqueta', 'valor' => 0, 'max' => config('taquilla.compra.max_por_campo')])

@php $id = 'cantidad-' . trim(preg_replace('/\W+/', '-', $nombre), '-'); @endphp

<div class="w-32" data-selector-cantidad>
    <label class="label" for="{{ $id }}">{{ $etiqueta }}</label>

    <div class="flex">
        <button type="button" data-paso="-1" tabindex="-1" aria-label="Quitar una persona en {{ $etiqueta }}"
                class="w-9 shrink-0 rounded-l-input border-[1.5px] border-r-0 border-borde bg-superficie
                       text-lg leading-none text-jade transition-colors hover:bg-jade/10">&minus;</button>

        <input type="number" inputmode="numeric" min="0" max="{{ $max }}" step="1"
               id="{{ $id }}" name="{{ $nombre }}" value="{{ (int) $valor }}" data-cantidad
               class="input rounded-none px-1 text-center tabular-nums [appearance:textfield]
                      [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">

        <button type="button" data-paso="1" tabindex="-1" aria-label="Agregar una persona en {{ $etiqueta }}"
                class="w-9 shrink-0 rounded-r-input border-[1.5px] border-l-0 border-borde bg-superficie
                       text-lg leading-none text-jade transition-colors hover:bg-jade/10">+</button>
    </div>
</div>
