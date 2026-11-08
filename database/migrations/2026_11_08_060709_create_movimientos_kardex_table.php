<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_kardex', function (Blueprint $table) {
            $table->id('id_movimiento_kardex');
            $table->foreignId('id_producto')->constrained('productos', 'id_producto');
            $table->foreignId('id_sucursal')->constrained('sucursales', 'id_sucursal');
            $table->foreignId('id_ubicacion')->constrained('ubicaciones', 'id_ubicacion');
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario');
            $table->foreignId('id_tipo_movimiento')->constrained('tipos_movimiento', 'id_tipo_movimiento');
            
            // Documentos de origen (Exactamente uno debe ser NO NULL)
            $table->foreignId('id_entrada')->nullable()->constrained('entradas_inventario', 'id_entrada');
            $table->foreignId('id_salida')->nullable()->constrained('salidas_inventario', 'id_salida');
            $table->foreignId('id_ajuste')->nullable()->constrained('ajustes_inventario', 'id_ajuste');
            $table->foreignId('id_transferencia')->nullable()->constrained('transferencias', 'id_transferencia');

            $table->integer('cantidad');
            $table->integer('stock_anterior');
            $table->integer('stock_resultante');
            $table->text('observacion')->nullable();
            $table->timestamp('fecha_hora')->useCurrent();

            // FK Compuesta a ubicaciones para validar sucursal correspondiente
            $table->foreign(['id_ubicacion', 'id_sucursal'])
                  ->references(['id_ubicacion', 'id_sucursal'])
                  ->on('ubicaciones');

            // Índices de consulta para trazabilidad
            $table->index(['id_producto', 'fecha_hora']);
            $table->index(['id_sucursal', 'fecha_hora']);
            $table->index('id_entrada');
            $table->index('id_salida');
            $table->index('id_ajuste');
            $table->index('id_transferencia');
            $table->index('id_tipo_movimiento');
        });

        DB::statement("ALTER TABLE movimientos_kardex ADD CONSTRAINT chk_kardex_origen_exacto_uno CHECK ((id_entrada IS NOT NULL) + (id_salida IS NOT NULL) + (id_ajuste IS NOT NULL) + (id_transferencia IS NOT NULL) = 1)");
        DB::statement("ALTER TABLE movimientos_kardex ADD CONSTRAINT chk_kardex_cantidad_positiva CHECK (cantidad > 0)");
        DB::statement("ALTER TABLE movimientos_kardex ADD CONSTRAINT chk_kardex_stock_anterior CHECK (stock_anterior >= 0)");
        DB::statement("ALTER TABLE movimientos_kardex ADD CONSTRAINT chk_kardex_stock_resultante CHECK (stock_resultante >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_kardex');
    }
};