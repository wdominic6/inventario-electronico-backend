<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Atributo extends Model
{
    protected $table = 'atributos';
    protected $primaryKey = 'id_atributo';
    public $timestamps = false;

    protected $fillable = ['nombre_atributo', 'tipo_dato', 'unidad_medida'];
}
