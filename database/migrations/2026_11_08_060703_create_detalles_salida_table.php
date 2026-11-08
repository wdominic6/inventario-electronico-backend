<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalles_salida', function (Blueprint $table) {
            $table->id('id_detalle');
            $table->foreignId('id_salida')->constrained('salidas_inventario', 'id_salida')->onDelete('cascade');
            $table->foreignId('id_producto')->constrained('productos', 'id_producto');
            $table->foreignId('id_ubicacion')->constrained('ubicaciones', 'id_ubicacion');
            $table->integer('cantidad');

            $table->unique(['id_salida', 'id_producto', 'id_ubicacion']);
            $table->index('id_producto');
        });

        DB::statement("ALTER TABLE detalles_salida ADD CONSTRAINT chk_salida_cantidad_positiva CHECK (cantidad > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('detalles_salida');
    }
};