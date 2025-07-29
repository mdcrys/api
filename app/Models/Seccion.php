<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Seccion extends Model
{
    use SoftDeletes;

    protected $table = 'secciones'; // Nombre correcto de la tabla en la base de datos

    protected $fillable = [
        'id_modulo', 'nombre', 'descripcion', 'id_empresa', 'estado'
    ];

    public function modulo()
    {
        return $this->belongsTo(Modulo::class, 'id_modulo');
    }
}

