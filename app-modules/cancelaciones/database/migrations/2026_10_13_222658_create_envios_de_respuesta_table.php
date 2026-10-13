<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('envios_de_respuesta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cancelacion_pendiente_id')->constrained('cancelaciones_pendientes');
            $table->string('respuesta');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('csd_id')->index();
            $table->string('resultado')->nullable();
            $table->string('codigo_sat')->nullable();
            $table->string('estatus_uuid')->nullable();
            $table->text('problema')->nullable();
            $table->string('ruta_enviado')->nullable();
            $table->string('ruta_recibido')->nullable();
            $table->dateTime('terminado_el')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('envios_de_respuesta');
    }
};
