<?php

namespace DescargaSat\ConsultaCfdi\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos de un CFDI a consultar: la expresión impresa completa, o RFC emisor, RFC receptor, total y UUID por separado.
 */
class ConsultarCfdiRequest extends FormRequest
{
    public const RFC = '/^[A-ZÑ&]{3,4}\d{6}[A-Z\d]{3}$/iu';

    public const TOTAL = '/^\d{1,18}(\.\d{1,6})?$/';

    /**
     * Cualquier Cliente API autenticado puede consultar; el middleware auth:api ya lo exige.
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
            'expresion_impresa' => ['required_without_all:rfc_emisor,rfc_receptor,total,uuid', 'prohibits:rfc_emisor,rfc_receptor,total,uuid', 'string', 'max:500'],
            'rfc_emisor' => ['required_without:expresion_impresa', 'string', 'regex:'.self::RFC],
            'rfc_receptor' => ['required_without:expresion_impresa', 'string', 'regex:'.self::RFC],
            'total' => ['required_without:expresion_impresa', 'regex:'.self::TOTAL],
            'uuid' => ['required_without:expresion_impresa', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expresion_impresa.required_without_all' => 'Envía la expresión impresa, o el RFC emisor, el RFC receptor, el total y el UUID.',
            'expresion_impresa.prohibits' => 'Envía la expresión impresa o los datos por separado, no ambos.',
            'expresion_impresa.max' => 'La expresión impresa no puede tener más de :max caracteres.',
            '*.required_without' => 'Falta :attribute.',
            '*.string' => ':attribute debe ser texto.',
            'rfc_emisor.regex' => 'El RFC emisor no tiene un formato válido.',
            'rfc_receptor.regex' => 'El RFC receptor no tiene un formato válido.',
            'total.regex' => 'El total debe ser un número con hasta 6 decimales.',
            'uuid.uuid' => 'El UUID no tiene un formato válido.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'expresion_impresa' => 'la expresión impresa',
            'rfc_emisor' => 'el RFC emisor',
            'rfc_receptor' => 'el RFC receptor',
            'total' => 'el total',
            'uuid' => 'el UUID',
        ];
    }
}
