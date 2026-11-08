<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categoria_atributos', function (Blueprint $table) {
            $table->foreignId('id_categoria')->constrained('categorias', 'id_categoria')->onDelete('cascade');
            $table->foreignId('id_atributo')->constrained('atributos', 'id_atributo')->onDelete('cascade');
            $table->boolean('es_obligatorio')->default(false);

            $table->primary(['id_categoria', 'id_atributo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria_atributos');
    }
};