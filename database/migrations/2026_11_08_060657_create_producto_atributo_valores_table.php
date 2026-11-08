<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_atributo_valores', function (Blueprint $table) {
            $table->foreignId('id_producto')->constrained('productos', 'id_producto')->onDelete('cascade');
            $table->foreignId('id_atributo')->constrained('atributos', 'id_atributo')->onDelete('cascade');
            $table->string('valor_texto', 255)->nullable();
            $table->decimal('valor_numero', 12, 4)->nullable();
            $table->boolean('valor_booleano')->nullable();

            $table->primary(['id_producto', 'id_atributo']);
            $table->index(['id_atributo', 'valor_numero'], 'idx_attr_val_num');
            $table->index(['id_atributo', 'valor_texto'], 'idx_attr_val_txt');
        });

        DB::statement("ALTER TABLE producto_atributo_valores ADD CONSTRAINT chk_atributo_valor_unico CHECK ((valor_texto IS NOT NULL) + (valor_numero IS NOT NULL) + (valor_booleano IS NOT NULL) = 1)");
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_atributo_valores');
    }
};