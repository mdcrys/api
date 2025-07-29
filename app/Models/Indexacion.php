<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Indexacion extends Model
{
    protected $table = 'indexaciones';
    protected $primaryKey = 'id_indexacion';

    // ✅ Campos que se pueden llenar masivamente
    protected $fillable = [
        'id_modulo',
        'campos_extra',
        'archivo_url',
        'estado',
        'creado_en',
        'updated_at',
    ];

    // ✅ Por si no usas timestamps de Laravel
    public $timestamps = false;

    // ✅ Opcional: convertir JSON automáticamente
    protected $casts = [
        'campos_extra' => 'array',
        'creado_en' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }
}
