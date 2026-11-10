<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    use HasFactory;

    protected $table = 'productos';
    protected $primaryKey = 'id_producto';

    protected $fillable = [
        'id_categoria', 'id_fabricante', 'id_unidad', 'codigo', 'nombre', 'modelo',
        'encapsulado', 'voltaje_operacion', 'tolerancia', 'especificaciones_extra', 'estado',
    ];

    protected function casts(): array
    {
        return ['especificaciones_extra' => 'array'];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    public function fabricante(): BelongsTo
    {
        return $this->belongsTo(Fabricante::class, 'id_fabricante', 'id_fabricante');
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'id_unidad', 'id_unidad');
    }

    // Atributos EAV: cada fila de producto_atributo_valores usa UNA de las tres columnas de valor.
    public function atributos(): BelongsToMany
    {
        return $this->belongsToMany(Atributo::class, 'producto_atributo_valores', 'id_producto', 'id_atributo')
            ->withPivot(['valor_texto', 'valor_numero', 'valor_booleano']);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'id_producto', 'id_producto');
    }
}
