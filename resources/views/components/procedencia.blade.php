{{-- País, estado y municipio en cascada (2026-10-05).

     Refleja en pantalla la regla de App\Services\Cliente\Procedencia: el
     estado solo se pide si el país es México, y el municipio solo si el estado
     tiene municipios en el catálogo. Lo que no aplica se deshabilita, así que
     ni se valida ni viaja en el formulario. El servidor valida de todos modos.

     `nombre` es el prefijo del name: sin él los campos son id_pais, id_estado
     e id_municipio; con 'renglones[0]' son renglones[0][id_pais], etc. --}}
@props([
    'paises',
    'estados',
    'municipios',
    'nombre'    => null,
    'valores'   => [],      // valores iniciales cuando no hay old()
    'requerido' => false,   // en la compra lo decide el script: solo si hay personas
    'compacto'  => false,   // sin etiquetas, para los renglones de la compra
])

@php
    $campo = fn (string $c) => $nombre ? "{$nombre}[{$c}]" : $c;
    $valor = fn (string $c) => old(str_replace(['[', ']'], ['.', ''], $campo($c)), $valores[$c] ?? null);
    $clase = 'input' . ($compacto ? ' text-xs' : '');
    $vacio = fn (string $texto) => $compacto ? $texto : '— Seleccionar —';
@endphp

<div {{ $attributes->class('grid gap-3 sm:grid-cols-3') }} data-procedencia>
    <div>
        @unless ($compacto) <label class="label">País</label> @endunless
        <select name="{{ $campo('id_pais') }}" class="{{ $clase }}" aria-label="País" data-pais @required($requerido)>
            <option value="">{{ $vacio('País') }}</option>
            @foreach ($paises as $p)
                <option value="{{ $p->id }}" @if ($p->esMexico()) data-mexico @endif
                        @selected($valor('id_pais') == $p->id)>{{ $p->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div>
        @unless ($compacto) <label class="label">Estado</label> @endunless
        <select name="{{ $campo('id_estado') }}" class="{{ $clase }} disabled:opacity-50" aria-label="Estado"
                data-estado @required($requerido)>
            <option value="">{{ $vacio('Estado') }}</option>
            @foreach ($estados as $e)
                <option value="{{ $e->id }}" @selected($valor('id_estado') == $e->id)>{{ $e->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div>
        @unless ($compacto) <label class="label">Municipio</label> @endunless
        <select name="{{ $campo('id_municipio') }}" class="{{ $clase }} disabled:opacity-50" aria-label="Municipio"
                data-municipio @required($requerido)>
            <option value="">{{ $vacio('Municipio') }}</option>
            @foreach ($municipios as $m)
                <option value="{{ $m->id }}" data-estado="{{ $m->id_estado }}"
                        @selected($valor('id_municipio') == $m->id)>{{ $m->nombre }}</option>
            @endforeach
        </select>
    </div>
</div>

@once
    <script>
        // Al cargar el documento y no aquí mismo: este bloque se pinta con el
        // primer selector, y en la compra hay uno por tarifa.
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-procedencia]').forEach((grupo) => {
                const pais      = grupo.querySelector('[data-pais]');
                const estado    = grupo.querySelector('[data-estado]');
                const municipio = grupo.querySelector('[data-municipio]');
                const todos     = Array.from(municipio.options);

                const ajustar = () => {
                    const esMexico = pais.selectedOptions[0]?.hasAttribute('data-mexico') ?? false;
                    estado.disabled = !esMexico;
                    if (!esMexico) estado.value = '';

                    // Solo los municipios del estado elegido; si no tiene
                    // ninguno, el campo se apaga.
                    const elegido = municipio.value;
                    const delEstado = todos.filter((o) => o.value === ''
                        || (estado.value !== '' && o.dataset.estado === estado.value));

                    municipio.replaceChildren(...delEstado);
                    municipio.value = delEstado.some((o) => o.value === elegido) ? elegido : '';
                    municipio.disabled = delEstado.length <= 1;
                };

                pais.addEventListener('change', ajustar);
                estado.addEventListener('change', ajustar);
                ajustar();
            });
        });
    </script>
@endonce
