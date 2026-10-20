<?php

namespace DescargaSat\ClientesApi\Actions;

use DescargaSat\ClientesApi\Models\ClienteApi;

class DesactivarClienteApi
{
    public function __invoke(ClienteApi $cliente): void
    {
        $cliente->update(['activo' => false]);
    }
}
