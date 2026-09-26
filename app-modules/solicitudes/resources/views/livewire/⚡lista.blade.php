<?php

use DescargaSat\SatAutenticacion\Contracts\Servicio;
use DescargaSat\Solicitudes\Contracts\EstadoSolicitud;
use DescargaSat\Solicitudes\Models\Solicitud;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Solicitudes')] class extends Component {
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, Solicitud>
     */
    #[Computed]
    public function solicitudes(): LengthAwarePaginator
    {
        return Solicitud::with('presentadaPor')->latest()->latest('id')->paginate(20);
    }
}; ?>

<section class="w-full space-y-6" @if ($this->solicitudes->contains(fn (Solicitud $solicitud): bool => $solicitud->estado->enCurso())) wire:poll.15s @endif>
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1">{{ __('Solicitudes') }}</flux:heading>
        <flux:button variant="primary" :href="route('solicitudes.create')" wire:navigate>{{ __('Nueva solicitud') }}</flux:button>
    </div>

    <flux:table :paginate="$this->solicitudes">
        <flux:table.columns>
            <flux:table.column>{{ __('Presentada') }}</flux:table.column>
            <flux:table.column>{{ __('Servicio') }}</flux:table.column>
            <flux:table.column>{{ __('Periodo') }}</flux:table.column>
            <flux:table.column>{{ __('Comprobantes') }}</flux:table.column>
            <flux:table.column>{{ __('Filtros') }}</flux:table.column>
            <flux:table.column>{{ __('Estado') }}</flux:table.column>
            <flux:table.column>{{ __('Presentada por') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->solicitudes as $solicitud)
                <flux:table.row :key="$solicitud->id" id="solicitud-{{ $solicitud->id }}">
                    <flux:table.cell>{{ $solicitud->created_at?->format('d/m/Y H:i') }}</flux:table.cell>
                    <flux:table.cell>{{ $solicitud->servicio === Servicio::Cfdi ? 'CFDI' : 'Retenciones' }}</flux:table.cell>
                    <flux:table.cell>{{ $solicitud->fecha_inicio->format('d/m/Y') }} – {{ $solicitud->fecha_fin->format('d/m/Y') }}</flux:table.cell>
                    <flux:table.cell>{{ $solicitud->tipo_descarga->etiqueta() }} · {{ $solicitud->tipo_solicitud->etiqueta() }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $solicitud->tipo_comprobante?->etiqueta() ?? __('Todos los tipos') }} · {{ $solicitud->estado_comprobante->etiqueta() }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <x-solicitudes::estado :solicitud="$solicitud" />
                        @if ($solicitud->estado === EstadoSolicitud::Rechazada)
                            <flux:text size="sm">{{ $solicitud->mensaje_sat }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $solicitud->presentadaPor?->name }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" :href="route('solicitudes.show', $solicitud)" wire:navigate>{{ __('Ver') }}</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8">{{ __('Todavía no has presentado ninguna solicitud.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
