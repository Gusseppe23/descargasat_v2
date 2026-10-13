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
        Schema::create('cancelaciones_pendientes', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('estado')->index();
            $table->dateTime('primera_deteccion_el');
            $table->dateTime('ultima_deteccion_el');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cancelaciones_pendientes');
    }
};
