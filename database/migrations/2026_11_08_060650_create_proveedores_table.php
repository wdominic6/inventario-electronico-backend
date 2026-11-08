<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id('id_proveedor');
            $table->string('razon_social', 150);
            $table->string('ruc', 20)->nullable()->unique();
            $table->string('contacto', 100)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('estado', 20)->default('activo');
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement("ALTER TABLE proveedores ADD CONSTRAINT chk_proveedor_estado CHECK (estado IN ('activo', 'inactivo'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};