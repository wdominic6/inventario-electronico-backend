<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transferencias', function (Blueprint $table) {
            $table->id('id_transferencia');
            $table->string('codigo_transferencia', 50)->unique();
            $table->foreignId('id_sucursal_origen')->constrained('sucursales', 'id_sucursal');
            $table->foreignId('id_sucursal_destino')->constrained('sucursales', 'id_sucursal');
            $table->foreignId('id_usuario_solicitante')->constrained('usuarios', 'id_usuario');
            $table->foreignId('id_usuario_aprobador')->nullable()->constrained('usuarios', 'id_usuario');
            $table->foreignId('id_usuario_receptor')->nullable()->constrained('usuarios', 'id_usuario');
            $table->string('estado', 30)->default('Pendiente');
            $table->string('estado_logistico', 30)->default('Sin Despachar');
            $table->timestamp('fecha_solicitud')->useCurrent();
            $table->timestamp('fecha_despacho')->nullable();
            $table->timestamp('fecha_recepcion')->nullable();

            $table->index('estado');
            $table->index('estado_logistico');
            $table->index('fecha_solicitud');
            $table->index(['id_sucursal_origen', 'estado']);
            $table->index(['id_sucursal_destino', 'estado']);
        });

        DB::statement("ALTER TABLE transferencias ADD CONSTRAINT chk_transferencia_sucursales_distintas CHECK (id_sucursal_origen <> id_sucursal_destino)");
        DB::statement("ALTER TABLE transferencias ADD CONSTRAINT chk_transferencia_fechas CHECK (fecha_recepcion IS NULL OR fecha_despacho IS NULL OR fecha_recepcion >= fecha_despacho)");
        DB::statement("ALTER TABLE transferencias ADD CONSTRAINT chk_transfer_estado CHECK (estado IN ('Pendiente', 'Aprobada', 'Rechazada', 'Cancelada'))");
        DB::statement("ALTER TABLE transferencias ADD CONSTRAINT chk_transfer_logistico CHECK (estado_logistico IN ('Sin Despachar', 'En Transito', 'Recibida Parcial', 'Recibida Completa'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencias');
    }
};