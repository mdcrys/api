<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Documento extends Model
{
    protected $table = 'documentos';

    protected $primaryKey = 'id_documento';

    protected $fillable = [
        'tipologia',
        'tema',
        'id_estanteria',
        'id_caja',
        'fecha',
        'archivo_url',
        'estado',
        'id_seccion',
        'id_modulo'
    ];

    public $timestamps = true;
}
