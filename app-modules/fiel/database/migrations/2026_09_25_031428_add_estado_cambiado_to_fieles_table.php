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
        Schema::table('fieles', function (Blueprint $table) {
            $table->foreignId('estado_cambiado_por_id')->nullable()->after('subida_por_id')->constrained('users')->nullOnDelete();
            $table->dateTime('estado_cambiado_el')->nullable()->after('estado_cambiado_por_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fieles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('estado_cambiado_por_id');
            $table->dropColumn('estado_cambiado_el');
        });
    }
};
