{{-- Formulario de rubro, compartido por «Nuevo rubro» y «Editar».
     $rubro = null → alta. Con rubro → edición, precargada con sus datos.

     La página puede traer muchos formularios de estos a la vez, así que los
     valores de old() solo se aplican al que se envió: `_rubro` dice cuál fue. --}}
@php
    $conOld = $errors->any() && (string) old('_rubro') === (string) $rubro?->id;
    $valor  = fn (string $campo, $propio) => $conOld ? old($campo) : $propio;
@endphp

<form method="POST"
      action="{{ $rubro ? route('admin.rubros.update', $rubro) : route('admin.rubros.store') }}"
      class="grid max-w-3xl gap-4 sm:grid-cols-2" data-form-rubro>
    @csrf
    @if ($rubro) @method('PUT') @endif
    <input type="hidden" name="_rubro" value="{{ $rubro?->id }}">

    <div class="sm:col-span-2">
        <label class="label">Tipo <span class="text-cinabrio">*</span></label>
        <input type="text" name="tipo" class="input" value="{{ $valor('tipo', $rubro?->tipo) }}"
               placeholder="Ej. Adulto nacional" required>
    </div>

    <div class="sm:col-span-2">
        <label class="label">Descripción a detalle</label>
        <textarea name="descripcion" rows="2" class="input">{{ $valor('descripcion', $rubro?->descripcion) }}</textarea>
    </div>

    <div class="sm:col-span-2">
        <label class="label">Requisito para el acceso</label>
        <input type="text" name="requisito" class="input" maxlength="160"
               value="{{ $valor('requisito', $rubro?->requisito) }}"
               placeholder="Ej. Presenta credencial INAPAM">
        <p class="mt-1 text-xs text-texto-suave">
            Opcional. Aparece en «Ten esto en cuenta», en el comprobante y en el PDF mientras el rubro esté vigente.
        </p>
    </div>

    @foreach ([
        ['id_nacionalidad', 'Nacionalidad', $nacionalidades],
        ['id_subnacionalidad', 'Subnacionalidad', $subnacionalidades],
    ] as [$campo, $etiqueta, $opciones])
        <div>
            <label class="label">{{ $etiqueta }} <span class="text-cinabrio">*</span></label>
            <select name="{{ $campo }}" class="input" required>
                <option value="">— Seleccionar —</option>
                @foreach ($opciones as $opcion)
                    <option value="{{ $opcion->id }}" @selected($valor($campo, $rubro?->$campo) == $opcion->id)>{{ $opcion->nombre }}</option>
                @endforeach
            </select>
        </div>
    @endforeach

    <div>
        <label class="label">Tipo de acceso <span class="text-cinabrio">*</span></label>
        <select name="id_tipo_acceso" class="input" required
                data-tipo-acceso data-gratis="{{ $idTipoGratis }}">
            <option value="">— Seleccionar —</option>
            @foreach ($tiposAcceso as $t)
                <option value="{{ $t->id }}" @selected($valor('id_tipo_acceso', $rubro?->id_tipo_acceso) == $t->id)>{{ $t->nombre }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="label">Precio en pesos <span class="text-cinabrio">*</span></label>
        {{-- Se guarda en centavos; aquí se captura y se muestra en pesos. --}}
        <input type="number" name="precio" step="0.01" min="0" class="input" required data-precio
               value="{{ $valor('precio', number_format(($rubro?->precio_centavos ?? 0) / 100, 2, '.', '')) }}">
        <p class="mt-1 text-[11px] text-texto-suave" data-aviso-gratis hidden>
            Un rubro GRATIS siempre cuesta 0.
        </p>
    </div>

    <div>
        <label class="label">Vigente desde <span class="text-cinabrio">*</span></label>
        <input type="date" name="vigente_desde" class="input" required
               value="{{ $valor('vigente_desde', ($rubro?->vigente_desde ?? now())->toDateString()) }}">
    </div>

    <div>
        <label class="label">Vigente hasta</label>
        <input type="date" name="vigente_hasta" class="input"
               value="{{ $valor('vigente_hasta', $rubro?->vigente_hasta?->toDateString()) }}">
        <p class="mt-1 text-[11px] text-texto-suave">Vacío = sin fecha de término.</p>
    </div>

    <div class="sm:col-span-2 flex items-center gap-3">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="activo" value="1" class="accent-jade"
                   @checked($valor('activo', $rubro?->activo ?? true))>
            Activo
        </label>
        <button type="submit" class="btn-primary ml-auto">{{ $rubro ? 'Guardar cambios' : 'Crear rubro' }}</button>
    </div>
</form>
