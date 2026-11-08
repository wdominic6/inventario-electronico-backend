<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    public const ADMINISTRADOR = 'Administrador';
    public const VENTAS = 'Ventas';
    public const ALMACEN = 'Almacén';

    protected $table = 'roles';
    protected $primaryKey = 'id_rol';
    public $timestamps = false;

    protected $fillable = ['nombre_rol', 'descripcion'];

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'rol_permisos', 'id_rol', 'id_permiso');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'id_rol', 'id_rol');
    }
}
