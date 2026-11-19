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
        Schema::create('metadata_cfdi', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('rfc_emisor', 13);
            $table->string('nombre_emisor')->nullable();
            $table->string('rfc_receptor', 13);
            $table->string('nombre_receptor')->nullable();
            $table->string('rfc_pac', 13)->nullable();
            $table->dateTime('fecha_emision');
            $table->dateTime('fecha_certificacion_sat')->nullable();
            $table->decimal('monto', 18, 2);
            $table->string('efecto', 1);
            $table->string('estatus', 1);
            $table->dateTime('fecha_cancelacion')->nullable();
            $table->unsignedBigInteger('paquete_id')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metadata_cfdi');
    }
};
