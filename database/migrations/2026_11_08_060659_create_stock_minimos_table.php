<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_minimos', function (Blueprint $table) {
            $table->id('id_stock_minimo');
            $table->foreignId('id_producto')->constrained('productos', 'id_producto')->onDelete('cascade');
            $table->foreignId('id_sucursal')->constrained('sucursales', 'id_sucursal')->onDelete('cascade');
            $table->integer('stock_minimo')->default(0);
            $table->timestamps();

            $table->unique(['id_producto', 'id_sucursal'], 'uq_stock_minimo_producto_sucursal');
        });

        DB::statement("ALTER TABLE stock_minimos ADD CONSTRAINT chk_stock_minimo_positivo CHECK (stock_minimo >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_minimos');
    }
};