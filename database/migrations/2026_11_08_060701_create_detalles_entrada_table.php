<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalles_entrada', function (Blueprint $table) {
            $table->id('id_detalle');
            $table->foreignId('id_entrada')->constrained('entradas_inventario', 'id_entrada')->onDelete('cascade');
            $table->foreignId('id_producto')->constrained('productos', 'id_producto');
            $table->foreignId('id_ubicacion')->constrained('ubicaciones', 'id_ubicacion');
            $table->integer('cantidad');
            $table->decimal('costo_unitario', 12, 2)->nullable();

            $table->unique(['id_entrada', 'id_producto', 'id_ubicacion']);
            $table->index('id_producto');
        });

        DB::statement("ALTER TABLE detalles_entrada ADD CONSTRAINT chk_cantidad_positiva CHECK (cantidad > 0)");
        DB::statement("ALTER TABLE detalles_entrada ADD CONSTRAINT chk_costo_positivo CHECK (costo_unitario IS NULL OR costo_unitario >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('detalles_entrada');
    }
};