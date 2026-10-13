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
        Schema::create('revisiones_de_cancelaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('csd_id')->nullable()->index();
            $table->string('codigo_sat')->nullable();
            $table->unsignedInteger('pendientes_reportadas')->nullable();
            $table->text('problema')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revisiones_de_cancelaciones');
    }
};
