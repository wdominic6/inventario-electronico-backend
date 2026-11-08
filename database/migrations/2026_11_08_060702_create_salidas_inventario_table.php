<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salidas_inventario', function (Blueprint $table) {
            $table->id('id_salida');
            $table->string('codigo_salida', 50)->unique();
            $table->foreignId('id_sucursal')->constrained('sucursales', 'id_sucursal');
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario');
            $table->string('cliente_referencia', 150)->nullable();
            $table->timestamp('fecha_salida')->useCurrent();
            $table->string('estado', 30)->default('Completado');
            $table->text('observacion')->nullable();
        });

        DB::statement("ALTER TABLE salidas_inventario ADD CONSTRAINT chk_salida_estado CHECK (estado IN ('Completado', 'Anulado'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('salidas_inventario');
    }
};