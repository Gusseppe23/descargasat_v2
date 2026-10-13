<?php

use DescargaSat\Cancelaciones\Actions\ResponderCancelacion;
use DescargaSat\Cancelaciones\Contracts\EstadoCancelacion;
use DescargaSat\Cancelaciones\Models\CancelacionPendiente;
use DescargaSat\Cancelaciones\Models\EnvioDeRespuesta;
use DescargaSat\SatAutenticacion\Contracts\Respuesta;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Cancelación')] class extends Component {
    public CancelacionPendiente $cancelacion;

    public ?string $respuesta = null;

    public string $uuidEscrito = '';

    /**
     * Abre la confirmación de la Respuesta de cancelación elegida.
     */
    public function elegir(string $respuesta): void
    {
        $this->respuesta = Respuesta::from($respuesta)->value;
        $this->uuidEscrito = '';
        $this->resetErrorBag();

        Flux::modal('confirmar-respuesta')->show();
    }

    /**
     * Encola el Envío de respuesta una vez que el usuario escribió el UUID.
     */
    public function responder(ResponderCancelacion $responderCancelacion): void
    {
        $this->validate([
            'respuesta' => ['required', Rule::enum(Respuesta::class)],
            'uuidEscrito' => ['required', 'string'],
        ]);

        $responderCancelacion($this->cancelacion, Respuesta::from((string) $this->respuesta), $this->uuidEscrito, Auth::user());

        Flux::modal('confirmar-respuesta')->close();
        $this->reset('respuesta', 'uuidEscrito');
        $this->cancelacion->refresh();
        unset($this->envios, $this->puedeResponderse);
    }

    #[Computed]
    public function puedeResponderse(): bool
    {
        return $this->cancelacion->puedeResponderse();
    }

    /**
     * @return Collection<int, EnvioDeRespuesta>
     */
    #[Computed]
    public function envios(): Collection
    {
        return $this->cancelacion->envios()->with('usuario')->latest('id')->get();
    }

    /**
     * Vuelve a leer la cancelación mientras su envío está en curso.
     */
    public function refrescar(): void
    {
        $this->cancelacion->refresh();
        unset($this->envios, $this->puedeResponderse);
    }
}; ?>

<section class="w-full space-y-8" @if ($cancelacion->estado === EstadoCancelacion::Enviando) wire:poll.10s="refrescar" @endif>
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1">{{ __('Cancelación') }}</flux:heading>
        <flux:button :href="route('cancelaciones.index')" wire:navigate icon="arrow-left">{{ __('Cancelaciones') }}</flux:button>
    </div>

    <dl class="grid max-w-2xl grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
        <dt class="text-zinc-500">{{ __('UUID') }}</dt>
        <dd class="font-mono">{{ $cancelacion->uuid }}</dd>

        <dt class="text-zinc-500">{{ __('Estado') }}</dt>
        <dd><flux:badge :color="$cancelacion->estado->color()" size="sm">{{ $cancelacion->estado->etiqueta() }}</flux:badge></dd>

        <dt class="text-zinc-500">{{ __('Detectada por primera vez') }}</dt>
        <dd>{{ $cancelacion->primera_deteccion_el->format('d/m/Y H:i') }}</dd>

        <dt class="text-zinc-500">{{ __('Última detección') }}</dt>
        <dd>{{ $cancelacion->ultima_deteccion_el->format('d/m/Y H:i') }}</dd>
    </dl>

    @if ($this->puedeResponderse)
        <div class="flex gap-2">
            <flux:button wire:click="elegir('{{ Respuesta::Aceptacion->value }}')" variant="primary">{{ __('Aceptar la cancelación') }}</flux:button>
            <flux:button wire:click="elegir('{{ Respuesta::Rechazo->value }}')" variant="danger">{{ __('Rechazar la cancelación') }}</flux:button>
        </div>
    @elseif ($cancelacion->estado === EstadoCancelacion::EnvioIncierto)
        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ __('No se sabe si el SAT recibió la Respuesta de cancelación. Se podrá volver a enviar cuando una Revisión de cancelaciones posterior al envío la siga mostrando pendiente.') }}
        </flux:callout>
    @endif

    <flux:error name="respuesta" />

    <flux:modal name="confirmar-respuesta" class="md:w-[32rem]">
        <form wire:submit="responder" class="space-y-6">
            <flux:heading size="lg">
                {{ $respuesta === Respuesta::Aceptacion->value ? __('Aceptar la cancelación') : __('Rechazar la cancelación') }}
            </flux:heading>

            <flux:text>{{ __('UUID') }}: <span class="font-mono">{{ $cancelacion->uuid }}</span></flux:text>

            <flux:callout variant="danger" icon="exclamation-triangle">
                {{ __('La Respuesta de cancelación se envía al SAT y no se puede deshacer.') }}
            </flux:callout>

            <flux:input wire:model="uuidEscrito" :label="__('Escribe el UUID para confirmar')" autocomplete="off" />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Enviar al SAT') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <div class="space-y-4">
        <flux:heading size="lg" level="2">{{ __('Envíos de respuesta') }}</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Fecha') }}</flux:table.column>
                <flux:table.column>{{ __('Usuario') }}</flux:table.column>
                <flux:table.column>{{ __('Respuesta') }}</flux:table.column>
                <flux:table.column>{{ __('Resultado') }}</flux:table.column>
                <flux:table.column>{{ __('Código del SAT') }}</flux:table.column>
                <flux:table.column>{{ __('Evidencia') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->envios as $envio)
                    <flux:table.row :key="$envio->id">
                        <flux:table.cell>{{ $envio->created_at?->format('d/m/Y H:i') }}</flux:table.cell>
                        <flux:table.cell>{{ $envio->usuario?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $envio->respuesta === Respuesta::Aceptacion ? __('Aceptación') : __('Rechazo') }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $envio->resultado?->etiqueta() ?? __('En curso') }}
                            @if ($envio->problema !== null)
                                <flux:text size="sm">{{ $envio->problema }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $envio->codigo_sat ?? '—' }}
                            @if ($envio->estatus_uuid !== null)
                                <flux:text size="sm">{{ __('EstatusUUID :estatus', ['estatus' => $envio->estatus_uuid]) }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($envio->ruta_enviado !== null)
                                <flux:link :href="route('cancelaciones.evidencia', [$envio, 'enviado'])">{{ __('Enviado') }}</flux:link>
                            @endif
                            @if ($envio->ruta_recibido !== null)
                                <flux:link :href="route('cancelaciones.evidencia', [$envio, 'recibido'])">{{ __('Recibido') }}</flux:link>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">{{ __('Todavía no se ha enviado ninguna Respuesta de cancelación.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
