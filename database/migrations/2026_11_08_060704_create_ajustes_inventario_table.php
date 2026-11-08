<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes_inventario', function (Blueprint $table) {
            $table->id('id_ajuste');
            $table->string('codigo_ajuste', 50)->unique();
            $table->foreignId('id_sucursal')->constrained('sucursales', 'id_sucursal');
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario');
            $table->text('motivo');
            $table->timestamp('fecha_ajuste')->useCurrent();
            $table->string('estado', 30)->default('Aplicado');
        });

        DB::statement("ALTER TABLE ajustes_inventario ADD CONSTRAINT chk_ajuste_estado CHECK (estado IN ('Pendiente', 'Aplicado', 'Rechazado'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_inventario');
    }
};