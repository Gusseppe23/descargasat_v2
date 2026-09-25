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
        Schema::create('fieles', function (Blueprint $table) {
            $table->id();
            $table->string('rfc', 13);
            $table->string('razon_social');
            $table->string('numero_certificado', 20)->unique();
            $table->dateTime('vigente_desde');
            $table->dateTime('vigente_hasta');
            $table->boolean('activa')->default(false);
            $table->string('ruta_cer');
            $table->string('ruta_key');
            $table->text('contrasena');
            $table->foreignId('subida_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fieles');
    }
};
