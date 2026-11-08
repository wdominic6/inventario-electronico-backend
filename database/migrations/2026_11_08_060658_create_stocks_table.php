<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id('id_stock');
            $table->foreignId('id_producto')->constrained('productos', 'id_producto')->onDelete('cascade');
            $table->foreignId('id_sucursal')->constrained('sucursales', 'id_sucursal');
            $table->foreignId('id_ubicacion')->constrained('ubicaciones', 'id_ubicacion');
            $table->integer('cantidad')->default(0);
            $table->timestamps();

            // Clave única por combinación física
            $table->unique(['id_producto', 'id_sucursal', 'id_ubicacion'], 'uq_stock_posicion');

            // FK Compuesta hacia ubicaciones para garantizar coincidencia de sucursal
            $table->foreign(['id_ubicacion', 'id_sucursal'])
                  ->references(['id_ubicacion', 'id_sucursal'])
                  ->on('ubicaciones')
                  ->onDelete('cascade');
        });

        DB::statement("ALTER TABLE stocks ADD CONSTRAINT chk_stock_no_negativo CHECK (cantidad >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};