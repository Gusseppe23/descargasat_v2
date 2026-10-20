<?php

namespace DescargaSat\ClientesApi\Http\Controllers;

use DescargaSat\ClientesApi\Actions\EmitirTokenClienteApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class TokenController
{
    public function __invoke(Request $request, EmitirTokenClienteApi $emitirToken): JsonResponse
    {
        $validado = $request->validate([
            'client_id' => ['required', 'string'],
            'client_secret' => ['required', 'string'],
        ]);

        $token = $emitirToken($validado['client_id'], $validado['client_secret']);

        /** @var JWTGuard $guard */
        $guard = auth('api');

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'expires_in' => $guard->factory()->getTTL() * 60,
        ]);
    }
}
