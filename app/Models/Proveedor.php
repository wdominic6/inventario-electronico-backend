<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    use HasFactory;

    // La tabla solo tiene created_at.
    public const UPDATED_AT = null;

    protected $table = 'proveedores';
    protected $primaryKey = 'id_proveedor';

    protected $fillable = [
        'razon_social', 'ruc', 'contacto', 'telefono', 'email', 'direccion', 'estado',
    ];
}
