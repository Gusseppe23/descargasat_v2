@props(['solicitud'])

@php
    use DescargaSat\Solicitudes\Contracts\EstadoSolicitud;
@endphp

<div {{ $attributes->class('space-y-1') }}>
    <flux:badge size="sm" :color="match ($solicitud->estado) {
        EstadoSolicitud::Rechazada, EstadoSolicitud::Error, EstadoSolicitud::Vencida, EstadoSolicitud::Abandonada => 'red',
        EstadoSolicitud::Descargada => 'green',
        EstadoSolicitud::SinResultados => 'zinc',
        default => 'blue',
    }">{{ $solicitud->estado->etiqueta() }}</flux:badge>

    @if ($solicitud->verificada_el !== null)
        <flux:text size="sm">
            {{ __('Último intento :fecha', ['fecha' => $solicitud->verificada_el->format('d/m/Y H:i')]) }}
            @if ($solicitud->ultimo_problema !== null)
                · {{ $solicitud->ultimo_problema }}
            @endif
        </flux:text>
    @endif
</div>
