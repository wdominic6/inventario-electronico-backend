<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id('id_sucursal');
            $table->string('nombre_sucursal', 100);
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('estado', 20)->default('activa');
        });

        DB::statement("ALTER TABLE sucursales ADD CONSTRAINT chk_sucursal_estado CHECK (estado IN ('activa', 'inactiva', 'mantenimiento'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursales');
    }
};