<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entradas_inventario', function (Blueprint $table) {
            $table->id('id_entrada');
            $table->string('codigo_entrada', 50)->unique();
            $table->foreignId('id_sucursal')->constrained('sucursales', 'id_sucursal');
            $table->foreignId('id_proveedor')->constrained('proveedores', 'id_proveedor');
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario');
            $table->timestamp('fecha_entrada')->useCurrent();
            $table->string('estado', 30)->default('Completado');
            $table->text('observacion')->nullable();
        });

        DB::statement("ALTER TABLE entradas_inventario ADD CONSTRAINT chk_entrada_estado CHECK (estado IN ('Pendiente', 'Completado', 'Anulado'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('entradas_inventario');
    }
};