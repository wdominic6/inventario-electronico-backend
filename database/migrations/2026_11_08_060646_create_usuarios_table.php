<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id('id_usuario');
            $table->foreignId('id_rol')->constrained('roles', 'id_rol');
            $table->string('nombre', 100);
            $table->string('apellido', 100);
            $table->string('email', 150)->unique();
            $table->string('password_hash', 255);
            $table->string('estado', 20)->default('activo');
            $table->timestamp('ultimo_acceso')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE usuarios ADD CONSTRAINT chk_usuario_estado CHECK (estado IN ('activo', 'inactivo', 'bloqueado'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};