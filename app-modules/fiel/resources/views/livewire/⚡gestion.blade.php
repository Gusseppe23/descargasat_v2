<?php

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
     * @return Collection<int, Fiel>
     */
    #[Computed]
    public function fieles(): Collection
    {
        return Fiel::with('subidaPor')->latest()->get();
    }
}; ?>

<section class="w-full space-y-8">
    <flux:heading size="xl" level="1">{{ __('Fiel') }}</flux:heading>

    <form wire:submit="subir" class="max-w-lg space-y-6">
        <flux:input wire:model="cer" type="file" accept=".cer" :label="__('Certificado (.cer)')" />
        <flux:input wire:model="key" type="file" accept=".key" :label="__('Llave privada (.key)')" />
        <flux:input wire:model="contrasena" type="password" :label="__('Contraseña de la llave')" viewable autocomplete="off" />

        <flux:button type="submit" variant="primary">{{ __('Subir Fiel') }}</flux:button>
    </form>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('RFC') }}</flux:table.column>
            <flux:table.column>{{ __('Razón social') }}</flux:table.column>
            <flux:table.column>{{ __('Número de certificado') }}</flux:table.column>
            <flux:table.column>{{ __('Vigente desde') }}</flux:table.column>
            <flux:table.column>{{ __('Vigente hasta') }}</flux:table.column>
            <flux:table.column>{{ __('Estado') }}</flux:table.column>
            <flux:table.column>{{ __('Subida por') }}</flux:table.column>
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
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $fiel->subidaPor?->name }}
                        <flux:text size="sm">{{ $fiel->created_at?->format('d/m/Y H:i') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">{{ __('Todavía no hay ninguna Fiel guardada.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
