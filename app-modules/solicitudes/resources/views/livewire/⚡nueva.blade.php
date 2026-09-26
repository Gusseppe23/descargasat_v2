<?php

use Carbon\CarbonImmutable;
use Flux\Flux;
use DescargaSat\Fiel\Contracts\DatosFiel;
use DescargaSat\Fiel\Contracts\Fieles;
use DescargaSat\SatAutenticacion\Contracts\Servicio;
use DescargaSat\Solicitudes\Actions\PresentarSolicitud;
use DescargaSat\Solicitudes\Enums\EstadoComprobante;
use DescargaSat\Solicitudes\Enums\EstadoSolicitud;
use DescargaSat\Solicitudes\Enums\TipoComprobante;
use DescargaSat\Solicitudes\Enums\TipoDescarga;
use DescargaSat\Solicitudes\Enums\TipoSolicitud;
use DescargaSat\Solicitudes\Support\ParametrosSolicitud;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Nueva solicitud')] class extends Component {
    public string $servicio = 'cfdi';

    public string $fechaInicio = '';

    public string $fechaFin = '';

    public string $tipoDescarga = 'emitidos';

    public string $tipoSolicitud = 'metadata';

    public string $tipoComprobante = '';

    public string $estadoComprobante = 'todos';

    public ?int $duplicadaId = null;

    public ?string $duplicadaFecha = null;

    /**
     * Presenta la Solicitud al SAT con la Fiel activa.
     */
    public function presentar(PresentarSolicitud $presentarSolicitud, bool $confirmarDuplicada = false): void
    {
        if ($this->fielActiva === null) {
            $this->addError('fiel', __('Activa una Fiel para presentar solicitudes.'));

            return;
        }

        $this->validate([
            'servicio' => ['required', Rule::enum(Servicio::class)],
            'fechaInicio' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now()->subYears(6)->toDateString()],
            'fechaFin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fechaInicio', 'before_or_equal:today'],
            'tipoDescarga' => ['required', Rule::enum(TipoDescarga::class)],
            'tipoSolicitud' => ['required', Rule::enum(TipoSolicitud::class)],
            'tipoComprobante' => ['nullable', Rule::enum(TipoComprobante::class)],
            'estadoComprobante' => ['required', Rule::enum(EstadoComprobante::class)],
        ], [
            'fechaInicio.after_or_equal' => __('El SAT solo permite pedir comprobantes de los últimos 6 años.'),
            'fechaFin.after_or_equal' => __('La fecha final no puede ser anterior a la inicial.'),
            'fechaFin.before_or_equal' => __('La fecha final no puede ser futura.'),
        ]);

        $parametros = $this->parametros();

        if (! $confirmarDuplicada && ($duplicada = $presentarSolicitud->duplicadaDe($parametros)) !== null) {
            $this->duplicadaId = $duplicada->id;
            $this->duplicadaFecha = $duplicada->created_at?->format('d/m/Y');

            return;
        }

        $solicitud = $presentarSolicitud($parametros, $this->fielActiva->id, Auth::user());

        if ($solicitud->estado === EstadoSolicitud::Aceptada) {
            Flux::toast(variant: 'success', text: __('Solicitud aceptada.'));
        } else {
            Flux::toast(variant: 'danger', text: __('Solicitud rechazada: :motivo', ['motivo' => $solicitud->mensaje_sat]));
        }

        $this->redirectRoute('solicitudes.index', navigate: true);
    }

    /**
     * Con Recibidos + XML el SAT solo entrega vigentes; el formulario lo refleja en cuanto cambia.
     */
    #[Computed]
    public function soloVigentes(): bool
    {
        return ParametrosSolicitud::soloVigentes(
            TipoDescarga::tryFrom($this->tipoDescarga) ?? TipoDescarga::Emitidos,
            TipoSolicitud::tryFrom($this->tipoSolicitud) ?? TipoSolicitud::Metadata,
        );
    }

    #[Computed]
    public function fielActiva(): ?DatosFiel
    {
        return app(Fieles::class)->activa();
    }

    private function parametros(): ParametrosSolicitud
    {
        return new ParametrosSolicitud(
            servicio: Servicio::from($this->servicio),
            fechaInicio: CarbonImmutable::createFromFormat('Y-m-d', $this->fechaInicio)->startOfDay(),
            fechaFin: CarbonImmutable::createFromFormat('Y-m-d', $this->fechaFin)->startOfDay(),
            tipoDescarga: TipoDescarga::from($this->tipoDescarga),
            tipoSolicitud: TipoSolicitud::from($this->tipoSolicitud),
            tipoComprobante: TipoComprobante::tryFrom($this->tipoComprobante),
            estadoComprobante: EstadoComprobante::from($this->estadoComprobante),
        );
    }
}; ?>

<section class="w-full space-y-6">
    <flux:heading size="xl" level="1">{{ __('Nueva solicitud') }}</flux:heading>

    @if ($this->fielActiva === null)
        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ __('Activa una Fiel para presentar solicitudes.') }}
            <x-slot name="actions">
                <flux:button size="sm" :href="route('fiel.index')" wire:navigate>{{ __('Ir a Fiel') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <form wire:submit="presentar" class="max-w-lg space-y-6">
        <flux:radio.group wire:model.live="servicio" :label="__('Servicio')" variant="segmented">
            @foreach (Servicio::cases() as $opcion)
                <flux:radio :value="$opcion->value" :label="$opcion === Servicio::Cfdi ? 'CFDI' : 'Retenciones'" />
            @endforeach
        </flux:radio.group>

        <div class="grid grid-cols-2 gap-4">
            <flux:input wire:model="fechaInicio" type="date" :label="__('Desde')" />
            <flux:input wire:model="fechaFin" type="date" :label="__('Hasta')" />
        </div>

        <flux:radio.group wire:model.live="tipoDescarga" :label="__('Comprobantes')" variant="segmented">
            @foreach (TipoDescarga::cases() as $opcion)
                <flux:radio :value="$opcion->value" :label="$opcion->etiqueta()" />
            @endforeach
        </flux:radio.group>

        <flux:radio.group wire:model.live="tipoSolicitud" :label="__('Qué descargar')" variant="segmented">
            @foreach (TipoSolicitud::cases() as $opcion)
                <flux:radio :value="$opcion->value" :label="$opcion->etiqueta()" />
            @endforeach
        </flux:radio.group>

        @if ($servicio === Servicio::Cfdi->value)
            <flux:select wire:model="tipoComprobante" :label="__('Tipo de comprobante')">
                <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                @foreach (TipoComprobante::cases() as $opcion)
                    <flux:select.option :value="$opcion->value">{{ $opcion->etiqueta() }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        @if ($this->soloVigentes)
            <flux:field>
                <flux:label>{{ __('Estado del comprobante') }}</flux:label>
                <flux:input value="{{ __('Vigentes') }}" disabled />
                <flux:description>{{ __('El SAT solo entrega XML recibidos vigentes.') }}</flux:description>
            </flux:field>
        @else
            <flux:select wire:model="estadoComprobante" :label="__('Estado del comprobante')">
                @foreach (EstadoComprobante::cases() as $opcion)
                    <flux:select.option :value="$opcion->value">{{ $opcion->etiqueta() }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        @if ($duplicadaId !== null)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>{{ __('Ya presentaste esta solicitud el :fecha.', ['fecha' => $duplicadaFecha]) }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('El SAT solo acepta la misma solicitud dos veces en toda la vida del RFC.') }}
                    <flux:link :href="route('solicitudes.index').'#solicitud-'.$duplicadaId" wire:navigate>{{ __('Ver la anterior') }}</flux:link>
                </flux:callout.text>
                <x-slot name="actions">
                    <flux:button size="sm" wire:click="presentar(true)">{{ __('Presentar de todos modos') }}</flux:button>
                </x-slot>
            </flux:callout>
        @endif

        <flux:error name="fiel" />
        <flux:error name="sat" />

        <flux:button type="submit" variant="primary" :disabled="$this->fielActiva === null">{{ __('Presentar solicitud') }}</flux:button>
    </form>
</section>
