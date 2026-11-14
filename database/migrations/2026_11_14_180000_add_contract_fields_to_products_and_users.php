<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            $table->string('codigo_barras', 100)->nullable()->unique()->after('codigo');
            $table->string('marca', 100)->nullable()->after('nombre');
            $table->unsignedInteger('stock_minimo')->default(0)->after('marca');
            $table->decimal('costo_unitario', 12, 2)->nullable()->after('stock_minimo');
            $table->decimal('precio_unitario', 12, 2)->nullable()->after('costo_unitario');
        });

        Schema::table('usuarios', function (Blueprint $table): void {
            $table->foreignId('id_sucursal')
                ->nullable()
                ->after('id_rol')
                ->constrained('sucursales', 'id_sucursal')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table): void {
            $table->dropForeign(['id_sucursal']);
            $table->dropColumn('id_sucursal');
        });

        Schema::table('productos', function (Blueprint $table): void {
            $table->dropUnique(['codigo_barras']);
            $table->dropColumn([
                'codigo_barras',
                'marca',
                'stock_minimo',
                'costo_unitario',
                'precio_unitario',
            ]);
        });
    }
};
