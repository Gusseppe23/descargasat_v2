<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una Solicitud por UUID (folio) guarda el UUID pedido; una automática la presenta la aplicación, sin usuario.
     */
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->string('uuid', 36)->nullable()->index()->after('estado_comprobante');
            $table->boolean('automatica')->default(false)->after('presentada_por_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->dropIndex(['uuid']);
            $table->dropColumn(['uuid', 'automatica']);
        });
    }
};
