<?php

use DescargaSat\Paquetes\Actions\ReintentarPaquete;
use DescargaSat\Paquetes\Contracts\EstadoPaquete;
use DescargaSat\Paquetes\Models\Paquete;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public int $solicitudId;

    /**
     * Vuelve a encolar la descarga de un Paquete Fallido de esta Solicitud.
     */
    public function reintentar(int $paqueteId, ReintentarPaquete $reintentarPaquete): void
    {
        $reintentarPaquete(Paquete::where('solicitud_id', $this->solicitudId)->findOrFail($paqueteId));

        unset($this->paquetes);
    }

    /**
     * @return Collection<int, Paquete>
     */
    #[Computed]
    public function paquetes(): Collection
    {
        return Paquete::where('solicitud_id', $this->solicitudId)->orderBy('id')->get();
    }
}; ?>

<div class="space-y-4" @if ($this->paquetes->contains(fn (Paquete $paquete): bool => $paquete->estado->enCurso())) wire:poll.15s @endif>
    <flux:heading size="lg" level="2">{{ __('Paquetes') }}</flux:heading>

    <flux:error name="paquete" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Paquete') }}</flux:table.column>
            <flux:table.column>{{ __('Estado') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->paquetes as $paquete)
                <flux:table.row :key="$paquete->id">
                    <flux:table.cell class="font-mono">{{ $paquete->id_paquete_sat }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="match ($paquete->estado) {
                            EstadoPaquete::Extraido => 'green',
                            EstadoPaquete::Fallido => 'red',
                            default => 'blue',
                        }">{{ $paquete->estado->etiqueta() }}</flux:badge>
                        @if ($paquete->ultimo_problema !== null)
                            <flux:text size="sm">{{ $paquete->ultimo_problema }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @if (in_array($paquete->estado, [EstadoPaquete::Descargado, EstadoPaquete::Extraido], true))
                            <flux:button size="sm" icon="arrow-down-tray" :href="route('paquetes.zip', $paquete)">{{ __('Bajar ZIP') }}</flux:button>
                        @endif
                        @if ($paquete->estado === EstadoPaquete::Fallido)
                            <flux:button size="sm" icon="arrow-path" wire:click="reintentar({{ $paquete->id }})">{{ __('Reintentar') }}</flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3">{{ __('Esta Solicitud todavía no tiene Paquetes.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
