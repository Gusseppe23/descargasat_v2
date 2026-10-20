<?php

namespace DescargaSat\ClientesApi\Actions;

use DescargaSat\ClientesApi\Models\ClienteApi;

class BorrarClienteApi
{
    public function __invoke(ClienteApi $cliente): void
    {
        $cliente->delete();
    }
}
