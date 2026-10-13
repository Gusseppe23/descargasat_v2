<?php

use DescargaSat\Cancelaciones\Models\CancelacionPendiente;
use DescargaSat\Cancelaciones\Models\RevisionDeCancelaciones;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Cancelaciones')] class extends Component {
    /**
     * @return Collection<int, CancelacionPendiente>
     */
    #[Computed]
    public function cancelaciones(): Collection
    {
        return CancelacionPendiente::orderByDesc('ultima_deteccion_el')->orderByDesc('id')->get();
    }

    #[Computed]
    public function ultimaRevision(): ?RevisionDeCancelaciones
    {
        return RevisionDeCancelaciones::latest('id')->first();
    }
}; ?>

<section class="w-full space-y-8">
    <flux:heading size="xl" level="1">{{ __('Cancelaciones') }}</flux:heading>

    <flux:text>{{ __('CFDI recibidos cuyo emisor pidió cancelarlos y que esperan la respuesta del RFC como ente público.') }}</flux:text>

    @if ($this->ultimaRevision === null)
        <flux:text>{{ __('Todavía no se ha hecho ninguna Revisión de cancelaciones.') }}</flux:text>
    @else
        <flux:text>{{ __('Última Revisión de cancelaciones: :fecha', ['fecha' => $this->ultimaRevision->created_at?->format('d/m/Y H:i')]) }}</flux:text>

        @if ($this->ultimaRevision->problema !== null)
            <flux:callout variant="danger" icon="x-circle">
                {{ $this->ultimaRevision->problema }}
            </flux:callout>
        @endif
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('UUID') }}</flux:table.column>
            <flux:table.column>{{ __('Estado') }}</flux:table.column>
            <flux:table.column>{{ __('Detectada por primera vez') }}</flux:table.column>
            <flux:table.column>{{ __('Última detección') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->cancelaciones as $cancelacion)
                <flux:table.row :key="$cancelacion->id">
                    <flux:table.cell class="font-mono">{{ $cancelacion->uuid }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$cancelacion->estado->color()" size="sm">{{ $cancelacion->estado->etiqueta() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $cancelacion->primera_deteccion_el->format('d/m/Y H:i') }}</flux:table.cell>
                    <flux:table.cell>{{ $cancelacion->ultima_deteccion_el->format('d/m/Y H:i') }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">{{ __('No hay Cancelaciones pendientes.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
