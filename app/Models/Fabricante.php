<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fabricante extends Model
{
    use HasFactory;

    protected $table = 'fabricantes';
    protected $primaryKey = 'id_fabricante';
    public $timestamps = false;

    protected $fillable = ['nombre', 'pais_origen'];
}
