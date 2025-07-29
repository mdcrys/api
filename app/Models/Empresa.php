<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $table = 'empresa';
    protected $primaryKey = 'id_empresa';
    public $timestamps = true;

    protected $fillable = [
    'nombre_empresa',
    'ruc_empresa',
    'telefono',
    'correo',
    'direccion',
    'estado',
    'imagen_empresa'
];

}
