<?php

use DescargaSat\SatAutenticacion\Contracts\Servicio;
use DescargaSat\Solicitudes\Models\Solicitud;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Solicitud')] class extends Component {
    public Solicitud $solicitud;
}; ?>

<section class="w-full space-y-8" @if ($solicitud->estado->enCurso()) wire:poll.15s @endif>
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1">{{ __('Solicitud') }}</flux:heading>
        <flux:button :href="route('solicitudes.index')" wire:navigate icon="arrow-left">{{ __('Solicitudes') }}</flux:button>
    </div>

    <dl class="grid max-w-2xl grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
        <dt class="text-zinc-500">{{ __('Servicio') }}</dt>
        <dd>{{ $solicitud->servicio === Servicio::Cfdi ? 'CFDI' : 'Retenciones' }}</dd>

        <dt class="text-zinc-500">{{ __('Periodo') }}</dt>
        <dd>{{ $solicitud->fecha_inicio->format('d/m/Y') }} – {{ $solicitud->fecha_fin->format('d/m/Y') }}</dd>

        <dt class="text-zinc-500">{{ __('Comprobantes') }}</dt>
        <dd>{{ $solicitud->tipo_descarga->etiqueta() }} · {{ $solicitud->tipo_solicitud->etiqueta() }}</dd>

        <dt class="text-zinc-500">{{ __('Filtros') }}</dt>
        <dd>{{ $solicitud->tipo_comprobante?->etiqueta() ?? __('Todos los tipos') }} · {{ $solicitud->estado_comprobante->etiqueta() }}</dd>

        <dt class="text-zinc-500">{{ __('Estado') }}</dt>
        <dd>
            <x-solicitudes::estado :solicitud="$solicitud" />
        </dd>

        <dt class="text-zinc-500">{{ __('Identificador del SAT') }}</dt>
        <dd class="font-mono">{{ $solicitud->id_solicitud_sat ?? '—' }}</dd>

        <dt class="text-zinc-500">{{ __('Mensaje del SAT') }}</dt>
        <dd>{{ $solicitud->mensaje_sat ?? '—' }}</dd>
    </dl>

    <livewire:paquetes::de-solicitud :solicitud-id="$solicitud->id" />
</section>
