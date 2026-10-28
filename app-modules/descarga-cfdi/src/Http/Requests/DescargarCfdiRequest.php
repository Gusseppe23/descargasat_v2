<?php

namespace DescargaSat\DescargaCfdi\Http\Requests;

use DescargaSat\SatAutenticacion\Contracts\TipoCfdi;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El CFDI a descargar: su UUID y si es emitido o recibido por el contribuyente de la Fiel activa.
 */
class DescargarCfdiRequest extends FormRequest
{
    /**
     * Cualquier Cliente API autenticado puede descargar; el middleware auth:api ya lo exige.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'uuid' => ['required', 'string', 'uuid'],
            'tipo' => ['required', 'string', Rule::enum(TipoCfdi::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.required' => 'Falta :attribute.',
            '*.string' => ':attribute debe ser texto.',
            'uuid.uuid' => 'El UUID no tiene un formato válido.',
            'tipo.enum' => 'El tipo debe ser emitido o recibido.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'uuid' => 'el UUID',
            'tipo' => 'el tipo',
        ];
    }
}
