<?php

namespace App\Http\Requests;

use App\Models\TipoAcceso;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de rubros de cobro (brief §5.2).
 *
 * El precio se captura en PESOS en el formulario y se convierte a centavos
 * antes de guardar (§4.1). Aquí solo se valida; la conversión vive en
 * precioEnCentavos().
 */
class RubroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // la autorización la resuelve el middleware `permission`
    }

    public function rules(): array
    {
        return [
            'tipo'               => ['required', 'string', 'max:80'],
            'descripcion'        => ['nullable', 'string', 'max:2000'],
            'id_nacionalidad'    => ['required', 'exists:nacionalidades,id'],
            'id_subnacionalidad' => ['required', 'exists:subnacionalidades,id'],
            'id_tipo_acceso'     => ['required', 'exists:tipos_acceso,id'],
            'precio'             => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'vigente_desde'      => ['required', 'date'],
            'vigente_hasta'      => ['nullable', 'date', 'after_or_equal:vigente_desde'],
            'activo'             => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tipo'               => 'tipo de rubro',
            'id_nacionalidad'    => 'nacionalidad',
            'id_subnacionalidad' => 'subnacionalidad',
            'id_tipo_acceso'     => 'tipo de acceso',
            'precio'             => 'precio',
            'vigente_desde'      => 'vigencia desde',
            'vigente_hasta'      => 'vigencia hasta',
        ];
    }

    /**
     * Un rubro GRATIS no puede tener precio (brief §5.2).
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['id_tipo_acceso', 'precio'])) {
                    return;
                }

                $tipo = TipoAcceso::find($this->input('id_tipo_acceso'));

                if ($tipo?->esGratis() && $this->precioEnCentavos() !== 0) {
                    $validator->errors()->add(
                        'precio',
                        'Un rubro de tipo GRATIS debe tener precio 0.',
                    );
                }
            },
        ];
    }

    /**
     * Pesos capturados → centavos enteros. El dinero nunca se guarda como
     * float ni decimal (brief §4.1).
     */
    public function precioEnCentavos(): int
    {
        return (int) round(((float) $this->input('precio', 0)) * 100);
    }

    public function datosDelRubro(): array
    {
        return [
            'tipo'               => $this->string('tipo')->trim()->value(),
            'descripcion'        => $this->input('descripcion'),
            'id_nacionalidad'    => $this->integer('id_nacionalidad'),
            'id_subnacionalidad' => $this->integer('id_subnacionalidad'),
            'id_tipo_acceso'     => $this->integer('id_tipo_acceso'),
            'precio_centavos'    => $this->precioEnCentavos(),
            'vigente_desde'      => $this->date('vigente_desde'),
            'vigente_hasta'      => $this->input('vigente_hasta') ? $this->date('vigente_hasta') : null,
            'activo'             => $this->boolean('activo'),
        ];
    }
}
