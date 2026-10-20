<?php

namespace DescargaSat\ClientesApi\Actions;

use DescargaSat\ClientesApi\Models\ClienteApi;

class ActivarClienteApi
{
    public function __invoke(ClienteApi $cliente): void
    {
        $cliente->update(['activo' => true]);
    }
}
