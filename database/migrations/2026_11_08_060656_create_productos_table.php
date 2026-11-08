<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id('id_producto');
            $table->foreignId('id_categoria')->constrained('categorias', 'id_categoria');
            $table->foreignId('id_fabricante')->constrained('fabricantes', 'id_fabricante');
            $table->foreignId('id_unidad')->constrained('unidades_medida', 'id_unidad');
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 150);
            $table->string('modelo', 100)->nullable();
            $table->string('encapsulado', 50)->nullable();
            $table->string('voltaje_operacion', 50)->nullable();
            $table->string('tolerancia', 50)->nullable();
            $table->json('especificaciones_extra')->nullable();
            $table->string('estado', 20)->default('activo');
            $table->timestamps();

            // Índices de búsqueda
            $table->index('codigo');
            $table->index('id_categoria');
            $table->index('id_fabricante');
            $table->fullText(['nombre', 'modelo'], 'ft_producto_nombre_modelo');
        });

        DB::statement("ALTER TABLE productos ADD CONSTRAINT chk_producto_estado CHECK (estado IN ('activo', 'inactivo', 'descontinuado'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};