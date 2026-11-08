<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    use HasFactory;

    protected $table = 'categorias';
    protected $primaryKey = 'id_categoria';
    public $timestamps = false;

    protected $fillable = ['nombre_categoria', 'descripcion'];

    public function atributos(): BelongsToMany
    {
        return $this->belongsToMany(Atributo::class, 'categoria_atributos', 'id_categoria', 'id_atributo')
            ->withPivot('es_obligatorio');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'id_categoria', 'id_categoria');
    }
}
