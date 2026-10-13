<?php

use Carbon\CarbonImmutable;
use DescargaSat\Cancelaciones\Contracts\EstadoCancelacion;
use DescargaSat\Cancelaciones\Jobs\RevisarCancelacionesAhora;
use DescargaSat\Cancelaciones\Models\CancelacionPendiente;
use DescargaSat\Cancelaciones\Models\RevisionDeCancelaciones;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Cancelaciones')] class extends Component {
    #[Url]
    public string $estado = '';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    /**
     * Encola una Revisión de cancelaciones sin esperar a que termine.
     */
    public function revisarAhora(): void
    {
        RevisarCancelacionesAhora::solicitar();
    }

    /**
     * Vuelve a leer la lista y la última Revisión mientras hay una en curso.
     */
    public function refrescar(): void
    {
        unset($this->cancelaciones, $this->ultimaRevision);
    }

    public function limpiarFiltros(): void
    {
        $this->reset('estado', 'desde', 'hasta');
    }

    #[Computed]
    public function revisionEnCurso(): bool
    {
        return RevisarCancelacionesAhora::enCurso();
    }

    /**
     * Las Cancelaciones pendientes con el estado y el rango de "Detectada por primera vez" elegidos.
     *
     * @return Collection<int, CancelacionPendiente>
     */
    #[Computed]
    public function cancelaciones(): Collection
    {
        $estado = EstadoCancelacion::tryFrom($this->estado);
        $desde = $this->fecha($this->desde);
        $hasta = $this->fecha($this->hasta);

        return CancelacionPendiente::query()
            ->when($estado, fn ($consulta) => $consulta->where('estado', $estado))
            ->when($desde, fn ($consulta) => $consulta->where('primera_deteccion_el', '>=', $desde?->startOfDay()))
            ->when($hasta, fn ($consulta) => $consulta->where('primera_deteccion_el', '<=', $hasta?->endOfDay()))
            ->orderByDesc('ultima_deteccion_el')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function ultimaRevision(): ?RevisionDeCancelaciones
    {
        return RevisionDeCancelaciones::latest('id')->first();
    }

    private function fecha(string $valor): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $valor) ?: null;
    }
}; ?>

<section class="w-full space-y-8" @if ($this->revisionEnCurso) wire:poll.5s="refrescar" @endif>
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1">{{ __('Cancelaciones') }}</flux:heading>

        @if ($this->revisionEnCurso)
            <flux:button icon="arrow-path" disabled>{{ __('Revisión en curso…') }}</flux:button>
        @else
            <flux:button icon="arrow-path" wire:click="revisarAhora">{{ __('Revisar ahora') }}</flux:button>
        @endif
    </div>

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

    <div class="flex flex-wrap items-end gap-4">
        <flux:select wire:model.live="estado" :label="__('Estado')" class="max-w-48">
            <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
            @foreach (EstadoCancelacion::cases() as $opcion)
                <flux:select.option :value="$opcion->value">{{ $opcion->etiqueta() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live="desde" type="date" :label="__('Detectada desde')" class="max-w-44" />
        <flux:input wire:model.live="hasta" type="date" :label="__('Detectada hasta')" class="max-w-44" />
        @if ($estado !== '' || $desde !== '' || $hasta !== '')
            <flux:button variant="ghost" wire:click="limpiarFiltros">{{ __('Quitar filtros') }}</flux:button>
        @endif
    </div>

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
                    <flux:table.cell class="font-mono">
                        <flux:link :href="route('cancelaciones.detalle', $cancelacion)" wire:navigate>{{ $cancelacion->uuid }}</flux:link>
                    </flux:table.cell>
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
