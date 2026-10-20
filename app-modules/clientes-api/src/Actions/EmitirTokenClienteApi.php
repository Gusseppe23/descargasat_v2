<?php

namespace DescargaSat\ClientesApi\Actions;

use DescargaSat\ClientesApi\Models\ClienteApi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class EmitirTokenClienteApi
{
    /**
     * Autentica un Cliente API y devuelve su token de acceso (JWT).
     *
     * @throws ValidationException
     */
    public function __invoke(string $clientId, string $clientSecret): string
    {
        $credenciales = [
            'client_id' => $clientId,
            'password' => $clientSecret,
            'activo' => true,
        ];

        /** @var JWTGuard $guard */
        $guard = Auth::guard('api');
        $token = $guard->attempt($credenciales);

        if (! is_string($token)) {
            throw ValidationException::withMessages(['client_id' => 'Credenciales inválidas.']);
        }

        /** @var ClienteApi $cliente */
        $cliente = $guard->user();
        $cliente->forceFill(['ultimo_uso_el' => now()])->saveQuietly();

        return $token;
    }
}
