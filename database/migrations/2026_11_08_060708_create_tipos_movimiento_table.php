<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_movimiento', function (Blueprint $table) {
            $table->id('id_tipo_movimiento');
            $table->string('nombre_tipo', 50)->unique();
            $table->integer('factor');
        });

        DB::statement("ALTER TABLE tipos_movimiento ADD CONSTRAINT chk_dos_factores_permitidos CHECK (factor IN (-1, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_movimiento');
    }
};