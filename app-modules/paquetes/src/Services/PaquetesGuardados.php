<?php

namespace DescargaSat\Paquetes\Services;

use DescargaSat\Paquetes\Contracts\DatosPaquete;
use DescargaSat\Paquetes\Contracts\EstadoPaquete;
use DescargaSat\Paquetes\Contracts\PaqueteDanado;
use DescargaSat\Paquetes\Contracts\Paquetes;
use DescargaSat\Paquetes\Contracts\PaquetesRegistrados;
use DescargaSat\Paquetes\Models\Paquete;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class PaquetesGuardados implements Paquetes
{
    public function registrarPendientes(int $solicitudId, array $idsPaqueteSat): void
    {
        foreach ($idsPaqueteSat as $idPaqueteSat) {
            Paquete::firstOrCreate(
                ['id_paquete_sat' => $idPaqueteSat],
                ['solicitud_id' => $solicitudId, 'estado' => EstadoPaquete::Pendiente],
            );
        }

        PaquetesRegistrados::dispatch($solicitudId);
    }

    public function pendientesDe(int $solicitudId): array
    {
        return Paquete::where('solicitud_id', $solicitudId)
            ->where('estado', EstadoPaquete::Pendiente)
            ->orderBy('id')
            ->get()
            ->map(fn (Paquete $paquete): DatosPaquete => $this->datos($paquete))
            ->all();
    }

    public function porId(int $id): ?DatosPaquete
    {
        $paquete = Paquete::find($id);

        return $paquete === null ? null : $this->datos($paquete);
    }

    public function todosExtraidos(int $solicitudId): bool
    {
        return Paquete::where('solicitud_id', $solicitudId)->exists()
            && ! Paquete::where('solicitud_id', $solicitudId)->where('estado', '!=', EstadoPaquete::Extraido)->exists();
    }

    public function anotarProblema(int $id, ?string $problema): void
    {
        Paquete::whereKey($id)->update([
            'ultimo_problema' => $problema === null ? null : mb_substr($problema, 0, 255),
        ]);
    }

    public function marcarFallido(int $id): void
    {
        Paquete::whereKey($id)->update(['estado' => EstadoPaquete::Fallido]);
    }

    public function guardarZip(int $id, string $zip): void
    {
        $paquete = Paquete::findOrFail($id);

        Storage::disk('local')->put($paquete->rutaZip(), $zip);

        $paquete->estado = EstadoPaquete::Descargado;
        $paquete->save();
    }

    public function extraer(int $id): void
    {
        $paquete = Paquete::findOrFail($id);
        $disco = Storage::disk('local');
        $zip = new ZipArchive;

        if ($zip->open($disco->path($paquete->rutaZip()), ZipArchive::RDONLY) !== true) {
            throw new PaqueteDanado("El ZIP del Paquete {$paquete->id_paquete_sat} no se puede abrir.");
        }

        try {
            $nombres = [];

            for ($indice = 0; $indice < $zip->numFiles; $indice++) {
                $nombre = (string) $zip->getNameIndex($indice);

                if (! $this->esRutaSegura($nombre)) {
                    throw new PaqueteDanado("El ZIP del Paquete {$paquete->id_paquete_sat} contiene una ruta no permitida: {$nombre}");
                }

                if (! str_ends_with($nombre, '/')) {
                    $nombres[$indice] = $nombre;
                }
            }

            foreach ($nombres as $indice => $nombre) {
                $disco->put($paquete->carpeta().'/'.$nombre, (string) $zip->getFromIndex($indice));
            }
        } finally {
            $zip->close();
        }

        $paquete->estado = EstadoPaquete::Extraido;
        $paquete->save();
    }

    /**
     * Evita que un nombre dentro del ZIP escriba fuera de la carpeta del Paquete ("zip slip").
     */
    private function esRutaSegura(string $nombre): bool
    {
        if ($nombre === '' || str_starts_with($nombre, '/') || str_contains($nombre, '\\') || str_contains($nombre, ':')) {
            return false;
        }

        return ! in_array('..', explode('/', $nombre), true);
    }

    private function datos(Paquete $paquete): DatosPaquete
    {
        return new DatosPaquete(
            id: $paquete->id,
            solicitudId: $paquete->solicitud_id,
            idPaqueteSat: $paquete->id_paquete_sat,
            estado: $paquete->estado,
        );
    }
}
