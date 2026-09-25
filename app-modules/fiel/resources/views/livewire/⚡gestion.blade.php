<?php

use DescargaSat\Fiel\Actions\ActivarFiel;
use DescargaSat\Fiel\Actions\BorrarFiel;
use DescargaSat\Fiel\Actions\DesactivarFiel;
use DescargaSat\Fiel\Actions\RegistrarFiel;
use DescargaSat\Fiel\Models\Fiel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Fiel')] class extends Component {
    use WithFileUploads;

    public ?TemporaryUploadedFile $cer = null;

    public ?TemporaryUploadedFile $key = null;

    public string $contrasena = '';

    /**
     * Sube una nueva Fiel a partir del .cer, la .key y su contraseña.
     */
    public function subir(RegistrarFiel $registrarFiel): void
    {
        $this->validate([
            'cer' => ['required', 'file', 'max:10'],
            'key' => ['required', 'file', 'max:10'],
            'contrasena' => ['required', 'string'],
        ]);

        $registrarFiel($this->cer->get(), $this->key->get(), $this->contrasena, Auth::user());

        $this->reset('cer', 'key', 'contrasena');
        unset($this->fieles);
    }

    /**
     * Convierte la Fiel en la Fiel activa.
     */
    public function activar(int $fielId, ActivarFiel $activarFiel): void
    {
        $activarFiel(Fiel::findOrFail($fielId), Auth::user());

        unset($this->fieles);
    }

    /**
     * Deja de usar la Fiel para presentar nuevas Solicitudes.
     */
    public function desactivar(int $fielId, DesactivarFiel $desactivarFiel): void
    {
        $desactivarFiel(Fiel::findOrFail($fielId), Auth::user());

        unset($this->fieles);
    }

    /**
     * Borra la Fiel y sus archivos.
     */
    public function borrar(int $fielId, BorrarFiel $borrarFiel): void
    {
        $borrarFiel(Fiel::findOrFail($fielId));

        unset($this->fieles);
    }

    #[Computed]
    public function fielActiva(): ?Fiel
    {
        return $this->fieles->firstWhere('activa', true);
    }

    /**
     * Días completos que le quedan a la Fiel activa; negativo si ya venció.
     */
    #[Computed]
    public function diasParaVencer(): ?int
    {
        if ($this->fielActiva === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->fielActiva->vigente_hasta->copy()->startOfDay(), absolute: false);
    }

    /**
     * @return Collection<int, Fiel>
     */
    #[Computed]
    public function fieles(): Collection
    {
        return Fiel::with('subidaPor', 'estadoCambiadoPor')->latest()->get();
    }
}; ?>

<section class="w-full space-y-8">
    <flux:heading size="xl" level="1">{{ __('Fiel') }}</flux:heading>

    @if ($this->fielActiva?->vigente_hasta->isPast())
        <flux:callout variant="danger" icon="x-circle">
            {{ __('La Fiel activa venció el :fecha.', ['fecha' => $this->fielActiva->vigente_hasta->format('d/m/Y')]) }}
        </flux:callout>
    @elseif ($this->diasParaVencer !== null && $this->diasParaVencer <= 30)
        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ __('La Fiel activa vence en :dias días, el :fecha.', [
                'dias' => $this->diasParaVencer,
                'fecha' => $this->fielActiva->vigente_hasta->format('d/m/Y'),
            ]) }}
        </flux:callout>
    @endif

    <form wire:submit="subir" class="max-w-lg space-y-6">
        <flux:input wire:model="cer" type="file" accept=".cer" :label="__('Certificado (.cer)')" />
        <flux:input wire:model="key" type="file" accept=".key" :label="__('Llave privada (.key)')" />
        <flux:input wire:model="contrasena" type="password" :label="__('Contraseña de la llave')" viewable autocomplete="off" />

        <flux:button type="submit" variant="primary">{{ __('Subir Fiel') }}</flux:button>
    </form>

    <flux:error name="fiel" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('RFC') }}</flux:table.column>
            <flux:table.column>{{ __('Razón social') }}</flux:table.column>
            <flux:table.column>{{ __('Número de certificado') }}</flux:table.column>
            <flux:table.column>{{ __('Vigente desde') }}</flux:table.column>
            <flux:table.column>{{ __('Vigente hasta') }}</flux:table.column>
            <flux:table.column>{{ __('Estado') }}</flux:table.column>
            <flux:table.column>{{ __('Subida por') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->fieles as $fiel)
                <flux:table.row :key="$fiel->id">
                    <flux:table.cell>{{ $fiel->rfc }}</flux:table.cell>
                    <flux:table.cell>{{ $fiel->razon_social }}</flux:table.cell>
                    <flux:table.cell>{{ $fiel->numero_certificado }}</flux:table.cell>
                    <flux:table.cell>{{ $fiel->vigente_desde->format('d/m/Y') }}</flux:table.cell>
                    <flux:table.cell>{{ $fiel->vigente_hasta->format('d/m/Y') }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($fiel->activa)
                            <flux:badge color="green" size="sm">{{ __('Activa') }}</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">{{ __('Inactiva') }}</flux:badge>
                        @endif

                        @if ($fiel->estado_cambiado_el !== null)
                            <flux:text size="sm">
                                {{ __($fiel->activa ? 'Activada por :nombre el :fecha' : 'Desactivada por :nombre el :fecha', [
                                    'nombre' => $fiel->estadoCambiadoPor?->name,
                                    'fecha' => $fiel->estado_cambiado_el->format('d/m/Y H:i'),
                                ]) }}
                            </flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $fiel->subidaPor?->name }}
                        <flux:text size="sm">{{ $fiel->created_at?->format('d/m/Y H:i') }}</flux:text>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @if ($fiel->activa)
                            <flux:button size="sm" wire:click="desactivar({{ $fiel->id }})">{{ __('Desactivar') }}</flux:button>
                        @else
                            <flux:button size="sm" wire:click="activar({{ $fiel->id }})">{{ __('Activar') }}</flux:button>
                            <flux:button
                                size="sm"
                                variant="danger"
                                wire:click="borrar({{ $fiel->id }})"
                                wire:confirm="{{ __('¿Borrar esta Fiel y sus archivos? No se puede deshacer.') }}"
                            >{{ __('Borrar') }}</flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8">{{ __('Todavía no hay ninguna Fiel guardada.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
