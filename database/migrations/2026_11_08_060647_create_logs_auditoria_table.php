<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logs_auditoria', function (Blueprint $table) {
            $table->id('id_log');
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario');
            $table->string('accion', 30);
            $table->string('tabla_afectada', 100);
            $table->integer('registro_id')->nullable();
            $table->json('valores_anteriores')->nullable();
            $table->json('valores_nuevos')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('fecha_hora')->useCurrent();

            $table->index(['tabla_afectada', 'registro_id']);
            $table->index('fecha_hora');
            $table->index('id_usuario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs_auditoria');
    }
};