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
        Schema::create('clientes_api', function (Blueprint $table) {
            $table->id();
            $table->string('client_id')->unique();
            $table->string('nombre');
            $table->string('client_secret');
            $table->boolean('activo')->default(true);
            $table->foreignId('creado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('ultimo_uso_el')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes_api');
    }
};
