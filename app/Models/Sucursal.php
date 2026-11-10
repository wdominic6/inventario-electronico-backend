<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sucursal extends Model
{
    use HasFactory;

    protected $table = 'sucursales';
    protected $primaryKey = 'id_sucursal';
    public $timestamps = false;

    protected $fillable = ['nombre_sucursal', 'direccion', 'telefono', 'estado'];

    public function ubicaciones(): HasMany
    {
        return $this->hasMany(Ubicacion::class, 'id_sucursal', 'id_sucursal');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'id_sucursal', 'id_sucursal');
    }
}
