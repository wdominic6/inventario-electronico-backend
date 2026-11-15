<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sucursales', function (Blueprint $table): void {
            $table->string('codigo', 30)->nullable()->unique()->after('id_sucursal');
        });

        Schema::table('categorias', function (Blueprint $table): void {
            $table->string('prefijo_codigo', 30)->nullable()->unique()->after('nombre_categoria');
        });

        Schema::table('ubicaciones', function (Blueprint $table): void {
            $table->string('codigo', 50)->nullable()->unique()->after('id_ubicacion');
        });
    }

    public function down(): void
    {
        Schema::table('ubicaciones', function (Blueprint $table): void {
            $table->dropUnique(['codigo']);
            $table->dropColumn('codigo');
        });

        Schema::table('categorias', function (Blueprint $table): void {
            $table->dropUnique(['prefijo_codigo']);
            $table->dropColumn('prefijo_codigo');
        });

        Schema::table('sucursales', function (Blueprint $table): void {
            $table->dropUnique(['codigo']);
            $table->dropColumn('codigo');
        });
    }
};
