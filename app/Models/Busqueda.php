<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Busqueda extends Model
{
    use HasFactory;

    // Nombre real de la tabla
    protected $table = 'indexaciones';

    // Nombre de la PRIMARY KEY
    protected $primaryKey = 'id_indexacion';

    // Si el ID es auto incremental
    public $incrementing = true;

    // Tipo de clave (int)
    protected $keyType = 'int';

    // Tu tabla NO usa created_at → pero SÍ usa creado_en
    public $timestamps = false;

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'id_proyecto',
        'id_subseccion',
        'id_subseccion2',
        'id_serie',
        'id_subserie',
        'id_modulo',
        'campos_extra',
        'archivo_url',
        'texto_ocr',
        'estado',
        'creado_en',
        'updated_at'
    ];
}
