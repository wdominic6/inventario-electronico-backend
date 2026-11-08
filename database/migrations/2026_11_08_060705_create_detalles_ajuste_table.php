<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalles_ajuste', function (Blueprint $table) {
            $table->id('id_detalle');
            $table->foreignId('id_ajuste')->constrained('ajustes_inventario', 'id_ajuste')->onDelete('cascade');
            $table->foreignId('id_producto')->constrained('productos', 'id_producto');
            $table->foreignId('id_ubicacion')->constrained('ubicaciones', 'id_ubicacion');
            $table->integer('cantidad_ajustada');
            $table->string('tipo_ajuste', 20);

            $table->unique(['id_ajuste', 'id_producto', 'id_ubicacion']);
            $table->index('id_producto');
        });

        DB::statement("ALTER TABLE detalles_ajuste ADD CONSTRAINT chk_cantidad_ajustada_positiva CHECK (cantidad_ajustada > 0)");
        DB::statement("ALTER TABLE detalles_ajuste ADD CONSTRAINT chk_positiva_o_negativa CHECK (tipo_ajuste IN ('Positivo', 'Negativo'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('detalles_ajuste');
    }
};