<?php

use DescargaSat\Csd\Actions\ActivarCsd;
use DescargaSat\Csd\Actions\BorrarCsd;
use DescargaSat\Csd\Actions\DesactivarCsd;
use DescargaSat\Csd\Actions\RegistrarCsd;
use DescargaSat\Csd\Models\Csd;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('CSD')] class extends Component {
    use WithFileUploads;

    public ?TemporaryUploadedFile $cer = null;

    public ?TemporaryUploadedFile $key = null;

    public string $contrasena = '';

    /**
     * Sube un nuevo CSD a partir del .cer, la .key y su contraseña.
     */
    public function subir(RegistrarCsd $registrarCsd): void
    {
        $this->validate([
            'cer' => ['required', 'file', 'max:10'],
            'key' => ['required', 'file', 'max:10'],
            'contrasena' => ['required', 'string'],
        ]);

        $registrarCsd($this->cer->get(), $this->key->get(), $this->contrasena, Auth::user());

        $this->reset('cer', 'key', 'contrasena');
        unset($this->csds);
    }

    /**
     * Convierte el CSD en el CSD activo.
     */
    public function activar(int $csdId, ActivarCsd $activarCsd): void
    {
        $activarCsd(Csd::findOrFail($csdId), Auth::user());

        unset($this->csds);
    }

    /**
     * Deja de usar el CSD para revisar y responder cancelaciones.
     */
    public function desactivar(int $csdId, DesactivarCsd $desactivarCsd): void
    {
        $desactivarCsd(Csd::findOrFail($csdId), Auth::user());

        unset($this->csds);
    }

    /**
     * Borra el CSD y sus archivos.
     */
    public function borrar(int $csdId, BorrarCsd $borrarCsd): void
    {
        $borrarCsd(Csd::findOrFail($csdId));

        unset($this->csds);
    }

    /**
     * @return Collection<int, Csd>
     */
    #[Computed]
    public function csds(): Collection
    {
        return Csd::with('subidoPor', 'estadoCambiadoPor')->latest()->get();
    }
}; ?>

<section class="w-full space-y-8">
    <flux:heading size="xl" level="1">{{ __('CSD') }}</flux:heading>

    <flux:text>{{ __('Certificado de sello digital con el que la aplicación responde las cancelaciones de CFDI como ente público.') }}</flux:text>

    <form wire:submit="subir" class="max-w-lg space-y-6">
        <flux:input wire:model="cer" type="file" accept=".cer" :label="__('Certificado (.cer)')" />
        <flux:input wire:model="key" type="file" accept=".key" :label="__('Llave privada (.key)')" />
        <flux:input wire:model="contrasena" type="password" :label="__('Contraseña de la llave')" viewable autocomplete="off" />

        <flux:button type="submit" variant="primary">{{ __('Subir CSD') }}</flux:button>
    </form>

    <flux:error name="csd" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('RFC') }}</flux:table.column>
            <flux:table.column>{{ __('Razón social') }}</flux:table.column>
            <flux:table.column>{{ __('Número de certificado') }}</flux:table.column>
            <flux:table.column>{{ __('Vigente desde') }}</flux:table.column>
            <flux:table.column>{{ __('Vigente hasta') }}</flux:table.column>
            <flux:table.column>{{ __('Estado') }}</flux:table.column>
            <flux:table.column>{{ __('Subido por') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->csds as $csd)
                <flux:table.row :key="$csd->id">
                    <flux:table.cell>{{ $csd->rfc }}</flux:table.cell>
                    <flux:table.cell>{{ $csd->razon_social }}</flux:table.cell>
                    <flux:table.cell>{{ $csd->numero_certificado }}</flux:table.cell>
                    <flux:table.cell>{{ $csd->vigente_desde->format('d/m/Y') }}</flux:table.cell>
                    <flux:table.cell>{{ $csd->vigente_hasta->format('d/m/Y') }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($csd->activo)
                            <flux:badge color="green" size="sm">{{ __('Activo') }}</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">{{ __('Inactivo') }}</flux:badge>
                        @endif

                        @if ($csd->estado_cambiado_el !== null)
                            <flux:text size="sm">
                                {{ __($csd->activo ? 'Activado por :nombre el :fecha' : 'Desactivado por :nombre el :fecha', [
                                    'nombre' => $csd->estadoCambiadoPor?->name,
                                    'fecha' => $csd->estado_cambiado_el->format('d/m/Y H:i'),
                                ]) }}
                            </flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $csd->subidoPor?->name }}
                        <flux:text size="sm">{{ $csd->created_at?->format('d/m/Y H:i') }}</flux:text>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @if ($csd->activo)
                            <flux:button size="sm" wire:click="desactivar({{ $csd->id }})">{{ __('Desactivar') }}</flux:button>
                        @else
                            <flux:button size="sm" wire:click="activar({{ $csd->id }})">{{ __('Activar') }}</flux:button>
                            <flux:button
                                size="sm"
                                variant="danger"
                                wire:click="borrar({{ $csd->id }})"
                                wire:confirm="{{ __('¿Borrar este CSD y sus archivos? No se puede deshacer.') }}"
                            >{{ __('Borrar') }}</flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8">{{ __('Todavía no hay ningún CSD guardado.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
