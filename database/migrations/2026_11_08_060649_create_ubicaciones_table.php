<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ubicaciones', function (Blueprint $table) {
            $table->id('id_ubicacion');
            $table->foreignId('id_sucursal')->constrained('sucursales', 'id_sucursal')->onDelete('cascade');
            $table->string('estante', 50);
            $table->string('nivel', 50);
            $table->string('casillero', 50);
            $table->string('estado', 20)->default('disponible');
            $table->text('descripcion')->nullable();

            // Índices Únicos para integridad físico-logística y claves foráneas compuestas
            $table->unique(['id_sucursal', 'estante', 'nivel', 'casillero'], 'uq_ubicacion_posicion');
            $table->unique(['id_ubicacion', 'id_sucursal'], 'uq_ubicacion_sucursal');
        });

        DB::statement("ALTER TABLE ubicaciones ADD CONSTRAINT chk_ubicacion_estado CHECK (estado IN ('disponible', 'inactiva', 'llena', 'mantenimiento'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('ubicaciones');
    }
};