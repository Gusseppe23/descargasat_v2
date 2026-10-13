<?php

namespace DescargaSat\Cancelaciones\Models;

use Carbon\CarbonImmutable;
use DescargaSat\Cancelaciones\Database\Factories\RevisionDeCancelacionesFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Una Revisión de cancelaciones: con qué CSD se hizo, qué respondió el SAT y, si lo hubo, el problema.
 *
 * @property int $id
 * @property int|null $csd_id
 * @property string|null $codigo_sat
 * @property int|null $pendientes_reportadas
 * @property string|null $problema
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Table('revisiones_de_cancelaciones')]
#[Fillable(['csd_id', 'codigo_sat', 'pendientes_reportadas', 'problema'])]
class RevisionDeCancelaciones extends Model
{
    /** @use HasFactory<RevisionDeCancelacionesFactory> */
    use HasFactory;
}
