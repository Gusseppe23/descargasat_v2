<?php

use DescargaSat\ClientesApi\Actions\ActivarClienteApi;
use DescargaSat\ClientesApi\Actions\BorrarClienteApi;
use DescargaSat\ClientesApi\Actions\CrearClienteApi;
use DescargaSat\ClientesApi\Actions\DesactivarClienteApi;
use DescargaSat\ClientesApi\Actions\RegenerarSecretoClienteApi;
use DescargaSat\ClientesApi\Models\ClienteApi;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Clientes API')] class extends Component {
    public string $nombre = '';

    public ?string $secretoGenerado = null;

    public ?string $clienteConSecreto = null;

    /**
     * Da de alta un Cliente API y muestra su secreto una sola vez.
     */
    public function crear(CrearClienteApi $crearClienteApi): void
    {
        $this->validate(['nombre' => ['required', 'string', 'max:255']]);

        [$cliente, $secreto] = $crearClienteApi($this->nombre, Auth::user());

        $this->reset('nombre');
        $this->secretoGenerado = $secreto;
        $this->clienteConSecreto = $cliente->client_id;
        unset($this->clientes);
    }

    /**
     * Invalida el secreto del Cliente API y muestra el nuevo una sola vez.
     */
    public function regenerarSecreto(int $clienteId, RegenerarSecretoClienteApi $regenerarSecreto): void
    {
        $cliente = ClienteApi::findOrFail($clienteId);

        $this->secretoGenerado = $regenerarSecreto($cliente);
        $this->clienteConSecreto = $cliente->client_id;
    }

    public function activar(int $clienteId, ActivarClienteApi $activarClienteApi): void
    {
        $activarClienteApi(ClienteApi::findOrFail($clienteId));
        unset($this->clientes);
    }

    public function desactivar(int $clienteId, DesactivarClienteApi $desactivarClienteApi): void
    {
        $desactivarClienteApi(ClienteApi::findOrFail($clienteId));
        unset($this->clientes);
    }

    public function borrar(int $clienteId, BorrarClienteApi $borrarClienteApi): void
    {
        $borrarClienteApi(ClienteApi::findOrFail($clienteId));
        unset($this->clientes);
    }

    public function cerrarSecreto(): void
    {
        $this->reset('secretoGenerado', 'clienteConSecreto');
    }

    /**
     * @return Collection<int, ClienteApi>
     */
    #[Computed]
    public function clientes(): Collection
    {
        return ClienteApi::with('creadoPor')->latest()->get();
    }
}; ?>

<section class="w-full space-y-8">
    <flux:heading size="xl" level="1">{{ __('Clientes API') }}</flux:heading>

    <flux:text>{{ __('Sistemas que pueden consumir la API REST de este proyecto mediante un token JWT.') }}</flux:text>

    @if ($secretoGenerado)
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950 space-y-3">
            <flux:heading size="sm">{{ __('Secreto de :cliente', ['cliente' => $clienteConSecreto]) }}</flux:heading>
            <flux:text size="sm">{{ __('Cópialo ahora: no se volverá a mostrar.') }}</flux:text>
            <flux:input readonly value="{{ $secretoGenerado }}" copyable />
            <flux:button size="sm" wire:click="cerrarSecreto">{{ __('Entendido') }}</flux:button>
        </div>
    @endif

    <form wire:submit="crear" class="max-w-lg space-y-6">
        <flux:input wire:model="nombre" :label="__('Nombre del cliente')" placeholder="{{ __('p. ej. Sistema de Facturación') }}" />
        <flux:button type="submit" variant="primary">{{ __('Generar cliente') }}</flux:button>
    </form>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Client ID') }}</flux:table.column>
            <flux:table.column>{{ __('Nombre') }}</flux:table.column>
            <flux:table.column>{{ __('Estado') }}</flux:table.column>
            <flux:table.column>{{ __('Último uso') }}</flux:table.column>
            <flux:table.column>{{ __('Creado por') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->clientes as $cliente)
                <flux:table.row :key="$cliente->id">
                    <flux:table.cell><code>{{ $cliente->client_id }}</code></flux:table.cell>
                    <flux:table.cell>{{ $cliente->nombre }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($cliente->activo)
                            <flux:badge color="green" size="sm">{{ __('Activo') }}</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">{{ __('Inactivo') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $cliente->ultimo_uso_el?->format('d/m/Y H:i') ?? __('Nunca') }}</flux:table.cell>
                    <flux:table.cell>{{ $cliente->creadoPor?->name }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" wire:click="regenerarSecreto({{ $cliente->id }})" wire:confirm="{{ __('¿Regenerar el secreto? El anterior dejará de funcionar de inmediato.') }}">{{ __('Regenerar secreto') }}</flux:button>
                        @if ($cliente->activo)
                            <flux:button size="sm" wire:click="desactivar({{ $cliente->id }})">{{ __('Desactivar') }}</flux:button>
                        @else
                            <flux:button size="sm" wire:click="activar({{ $cliente->id }})">{{ __('Activar') }}</flux:button>
                        @endif
                        <flux:button size="sm" variant="danger" wire:click="borrar({{ $cliente->id }})" wire:confirm="{{ __('¿Borrar este cliente API? No se puede deshacer.') }}">{{ __('Borrar') }}</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('Todavía no hay ningún cliente API.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
