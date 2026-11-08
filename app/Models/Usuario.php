<?php

namespace App\Models;

use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable
{
    use HasFactory;

    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';

    protected $fillable = [
        'id_rol', 'nombre', 'apellido', 'email', 'password_hash', 'estado', 'ultimo_acceso',
    ];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return ['ultimo_acceso' => 'datetime'];
    }

    // La contraseña se guarda en "password_hash", no en "password".
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'id_rol', 'id_rol');
    }

    protected static function newFactory(): UsuarioFactory
    {
        return UsuarioFactory::new();
    }
}
