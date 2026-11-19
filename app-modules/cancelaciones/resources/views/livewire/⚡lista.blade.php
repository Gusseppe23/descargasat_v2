<?php

use DescargaSat\Cancelaciones\Contracts\EstadoCancelacion;
use DescargaSat\Cancelaciones\Jobs\RevisarCancelacionesAhora;
use DescargaSat\Cancelaciones\Models\CancelacionPendiente;
use DescargaSat\Cancelaciones\Models\RevisionDeCancelaciones;
use DescargaSat\Cancelaciones\Support\ListadoDeCancelaciones;
use DescargaSat\Metadata\Contracts\DatosMetadata;
use DescargaSat\Metadata\Contracts\MetadataCfdi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Cancelaciones')] class extends Component {
    use WithPagination;

    #[Url]
    public string $estado = '';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    #[Url]
    public string $uuid = '';

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
        unset($this->cancelaciones, $this->metadata, $this->ultimaRevision);
    }

    /**
     * Regresa a la primera página cuando cambia un filtro.
     */
    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['estado', 'desde', 'hasta', 'uuid'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('estado', 'desde', 'hasta', 'uuid');
        $this->resetPage();
    }

    /**
     * Descarga en CSV todas las Cancelaciones pendientes con los filtros aplicados, no solo la página visible.
     */
    public function exportar(): StreamedResponse
    {
        $consulta = (new ListadoDeCancelaciones($this->estado, $this->desde, $this->hasta, $this->uuid))->consulta();
        $metadataCfdi = app(MetadataCfdi::class);

        return response()->streamDownload(function () use ($consulta, $metadataCfdi): void {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\u{FEFF}");
            fputcsv($salida, ['UUID', 'Estado', 'RFC emisor', 'Nombre emisor', 'Importe', 'Fecha de emisión', 'Detectada por primera vez', 'Última detección'], escape: '');

            // Por bloques, para pedir la Metadata de CFDI una vez por bloque y no por fila.
            $consulta->chunk(500, function (Collection $bloque) use ($salida, $metadataCfdi): void {
                $metadata = $metadataCfdi->deUuids($bloque->pluck('uuid')->all());

                foreach ($bloque as $cancelacion) {
                    $datos = $metadata[$cancelacion->uuid] ?? null;

                    fputcsv($salida, [
                        $cancelacion->uuid,
                        $cancelacion->estado->etiqueta(),
                        $datos?->rfcEmisor ?? '',
                        $datos?->nombreEmisor ?? '',
                        $datos?->monto ?? '',
                        $datos?->fechaEmision->format('Y-m-d H:i:s') ?? '',
                        $cancelacion->primera_deteccion_el->format('Y-m-d H:i:s'),
                        $cancelacion->ultima_deteccion_el->format('Y-m-d H:i:s'),
                    ], escape: '');
                }
            });

            fclose($salida);
        }, 'cancelaciones-'.now()->format('Ymd-Hi').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    #[Computed]
    public function revisionEnCurso(): bool
    {
        return RevisarCancelacionesAhora::enCurso();
    }

    /**
     * Las Cancelaciones pendientes con el estado, el rango de "Detectada por primera vez" y el UUID buscados.
     *
     * @return LengthAwarePaginator<int, CancelacionPendiente>
     */
    #[Computed]
    public function cancelaciones(): LengthAwarePaginator
    {
        return (new ListadoDeCancelaciones($this->estado, $this->desde, $this->hasta, $this->uuid))->consulta()->paginate(20);
    }

    /**
     * La Metadata de CFDI de las Cancelaciones de la página visible, indexada por UUID.
     *
     * @return array<string, DatosMetadata>
     */
    #[Computed]
    public function metadata(): array
    {
        return app(MetadataCfdi::class)->deUuids($this->cancelaciones->getCollection()->pluck('uuid')->all());
    }

    #[Computed]
    public function ultimaRevision(): ?RevisionDeCancelaciones
    {
        return RevisionDeCancelaciones::latest('id')->first();
    }
}; ?>

<section class="w-full space-y-8" @if ($this->revisionEnCurso) wire:poll.5s="refrescar" @endif>
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1">{{ __('Cancelaciones') }}</flux:heading>

        <div class="flex gap-2">
            <flux:button icon="arrow-down-tray" wire:click="exportar">{{ __('Exportar') }}</flux:button>

            @if ($this->revisionEnCurso)
                <flux:button icon="arrow-path" disabled>{{ __('Revisión en curso…') }}</flux:button>
            @else
                <flux:button icon="arrow-path" wire:click="revisarAhora">{{ __('Revisar ahora') }}</flux:button>
            @endif
        </div>
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
        <flux:input wire:model.live.debounce.300ms="uuid" icon="magnifying-glass" :label="__('UUID')" :placeholder="__('Buscar por UUID')" class="max-w-80" />
        <flux:select wire:model.live="estado" :label="__('Estado')" class="max-w-48">
            <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
            @foreach (EstadoCancelacion::cases() as $opcion)
                <flux:select.option :value="$opcion->value">{{ $opcion->etiqueta() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live="desde" type="date" :label="__('Detectada desde')" class="max-w-44" />
        <flux:input wire:model.live="hasta" type="date" :label="__('Detectada hasta')" class="max-w-44" />
        @if ($estado !== '' || $desde !== '' || $hasta !== '' || $uuid !== '')
            <flux:button variant="ghost" wire:click="limpiarFiltros">{{ __('Quitar filtros') }}</flux:button>
        @endif
    </div>

    <flux:table :paginate="$this->cancelaciones">
        <flux:table.columns>
            <flux:table.column>{{ __('UUID') }}</flux:table.column>
            <flux:table.column>{{ __('Estado') }}</flux:table.column>
            <flux:table.column>{{ __('RFC emisor') }}</flux:table.column>
            <flux:table.column>{{ __('Nombre emisor') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Importe') }}</flux:table.column>
            <flux:table.column>{{ __('Fecha de emisión') }}</flux:table.column>
            <flux:table.column>{{ __('Detectada por primera vez') }}</flux:table.column>
            <flux:table.column>{{ __('Última detección') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->cancelaciones as $cancelacion)
                @php($datos = $this->metadata[$cancelacion->uuid] ?? null)
                <flux:table.row :key="$cancelacion->id">
                    <flux:table.cell class="font-mono">
                        <flux:link :href="route('cancelaciones.detalle', $cancelacion)" wire:navigate>{{ $cancelacion->uuid }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$cancelacion->estado->color()" size="sm">{{ $cancelacion->estado->etiqueta() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="font-mono">{{ $datos?->rfcEmisor ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $datos?->nombreEmisor ?? '—' }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $datos === null ? '—' : '$'.number_format((float) $datos->monto, 2) }}</flux:table.cell>
                    <flux:table.cell>{{ $datos?->fechaEmision->format('d/m/Y') ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $cancelacion->primera_deteccion_el->format('d/m/Y H:i') }}</flux:table.cell>
                    <flux:table.cell>{{ $cancelacion->ultima_deteccion_el->format('d/m/Y H:i') }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8">{{ __('No hay Cancelaciones pendientes.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
