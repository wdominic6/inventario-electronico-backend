<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ubicacion extends Model
{
    use HasFactory;

    protected $table = 'ubicaciones';
    protected $primaryKey = 'id_ubicacion';
    public $timestamps = false;

    protected $fillable = ['id_sucursal', 'estante', 'nivel', 'casillero', 'estado', 'descripcion'];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal', 'id_sucursal');
    }
}
