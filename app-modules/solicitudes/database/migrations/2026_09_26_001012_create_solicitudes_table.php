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
        Schema::create('solicitudes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fiel_id')->index();
            $table->foreignId('presentada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('servicio', 20);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('tipo_descarga', 20);
            $table->string('tipo_solicitud', 20);
            $table->string('tipo_comprobante', 1)->nullable();
            $table->string('estado_comprobante', 20);
            $table->string('id_solicitud_sat', 36)->nullable()->unique();
            $table->string('estado', 20)->index();
            $table->unsignedSmallInteger('codigo_sat')->nullable();
            $table->string('mensaje_sat')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes');
    }
};
