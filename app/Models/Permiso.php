<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permiso extends Model
{
    protected $table = 'permisos';
    protected $primaryKey = 'id_permiso';
    public $timestamps = false;

    protected $fillable = ['nombre_permiso', 'descripcion'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_permisos', 'id_permiso', 'id_rol');
    }
}
