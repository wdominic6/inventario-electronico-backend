<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atributos', function (Blueprint $table) {
            $table->id('id_atributo');
            $table->string('nombre_atributo', 100)->unique();
            $table->string('tipo_dato', 20);
            $table->string('unidad_medida', 20)->nullable();
        });

        DB::statement("ALTER TABLE atributos ADD CONSTRAINT chk_atributo_tipo_dato CHECK (tipo_dato IN ('texto', 'numero', 'booleano', 'lista'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('atributos');
    }
};