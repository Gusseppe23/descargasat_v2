<?php

namespace DescargaSat\Metadata\Contracts;

/**
 * Interfaz pública del módulo metadata.
 */
interface MetadataCfdi
{
    /**
     * La Metadata de CFDI que se tiene de los UUID dados, indexada por UUID en mayúsculas. Los UUID sin Metadata no aparecen.
     *
     * @param  list<string>  $uuids
     * @return array<string, DatosMetadata>
     */
    public function deUuids(array $uuids): array;

    /**
     * De los UUID dados, los que todavía no tienen Metadata, en mayúsculas.
     *
     * @param  list<string>  $uuids
     * @return list<string>
     */
    public function sinMetadata(array $uuids): array;
}
