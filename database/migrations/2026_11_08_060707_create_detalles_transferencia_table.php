<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalles_transferencia', function (Blueprint $table) {
            $table->id('id_detalle');
            $table->foreignId('id_transferencia')->constrained('transferencias', 'id_transferencia')->onDelete('cascade');
            $table->foreignId('id_producto')->constrained('productos', 'id_producto');
            $table->foreignId('id_ubicacion_origen')->constrained('ubicaciones', 'id_ubicacion');
            $table->foreignId('id_ubicacion_destino')->nullable()->constrained('ubicaciones', 'id_ubicacion');
            $table->integer('cantidad_solicitada');
            $table->integer('cantidad_enviada')->default(0);
            $table->integer('cantidad_recibida')->default(0);
            $table->integer('cantidad_diferencia')->default(0);
            $table->text('motivo_diferencia')->nullable();

            $table->unique(['id_transferencia', 'id_producto', 'id_ubicacion_origen'], 'uq_detalle_transfer_origen');
        });

        DB::statement("ALTER TABLE detalles_transferencia ADD CONSTRAINT chk_transfer_cantidad_solicitada CHECK (cantidad_solicitada > 0)");
        DB::statement("ALTER TABLE detalles_transferencia ADD CONSTRAINT chk_transfer_cantidad_enviada CHECK (cantidad_enviada >= 0)");
        DB::statement("ALTER TABLE detalles_transferencia ADD CONSTRAINT chk_transfer_cantidad_recibida CHECK (cantidad_recibida >= 0)");
        DB::statement("ALTER TABLE detalles_transferencia ADD CONSTRAINT chk_transfer_cantidad_diferencia CHECK (cantidad_diferencia >= 0)");
        DB::statement("ALTER TABLE detalles_transferencia ADD CONSTRAINT chk_transfer_envio_menor_solicitud CHECK (cantidad_enviada <= cantidad_solicitada)");
        DB::statement("ALTER TABLE detalles_transferencia ADD CONSTRAINT chk_transfer_recepcion_menor_envio CHECK (cantidad_recibida <= cantidad_enviada)");
    }

    public function down(): void
    {
        Schema::dropIfExists('detalles_transferencia');
    }
};