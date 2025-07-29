<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Modulo extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nombre', 'descripcion', 'id_empresa', 'id_proyecto', 'estado'
    ];

    public function secciones()
    {
        return $this->hasMany(Seccion::class, 'id_modulo');
    }
}
