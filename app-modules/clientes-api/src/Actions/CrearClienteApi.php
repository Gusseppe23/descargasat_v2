<?php

namespace DescargaSat\ClientesApi\Actions;

use App\Models\User;
use DescargaSat\ClientesApi\Models\ClienteApi;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CrearClienteApi
{
    /**
     * Da de alta un Cliente API. El secreto devuelto en claro no se vuelve a mostrar.
     *
     * @return array{0: ClienteApi, 1: string}
     */
    public function __invoke(string $nombre, User $usuario): array
    {
        $secreto = Str::random(40);

        $cliente = ClienteApi::create([
            'client_id' => 'ca_'.Str::random(24),
            'nombre' => $nombre,
            'client_secret' => Hash::make($secreto),
            'activo' => true,
            'creado_por_id' => $usuario->id,
        ]);

        return [$cliente, $secreto];
    }
}
