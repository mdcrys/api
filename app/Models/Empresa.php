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
        'imagen_empresa',
        'ruta_firma',
        'contrasena_firma',
        'fecha_subida_firma',
        'fecha_expiracion_firma',
        'fecha_ultima_firma',
        'estado'
    ];

    // Empresa.php
public function usuarios()
{
    return $this->hasMany(User::class, 'id_empresa', 'id_empresa');
}

}
