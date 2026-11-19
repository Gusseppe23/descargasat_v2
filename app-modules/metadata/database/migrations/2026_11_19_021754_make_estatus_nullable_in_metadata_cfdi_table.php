<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El XML de un CFDI no trae el estatus; solo la Metadata del SAT lo informa.
     */
    public function up(): void
    {
        Schema::table('metadata_cfdi', function (Blueprint $table) {
            $table->string('estatus', 1)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metadata_cfdi', function (Blueprint $table) {
            $table->string('estatus', 1)->nullable(false)->change();
        });
    }
};
