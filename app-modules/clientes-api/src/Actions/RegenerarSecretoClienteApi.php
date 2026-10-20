<?php

namespace DescargaSat\ClientesApi\Actions;

use DescargaSat\ClientesApi\Models\ClienteApi;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegenerarSecretoClienteApi
{
    /**
     * Invalida el secreto anterior y devuelve el nuevo en claro.
     */
    public function __invoke(ClienteApi $cliente): string
    {
        $secreto = Str::random(40);

        $cliente->update(['client_secret' => Hash::make($secreto)]);

        return $secreto;
    }
}
