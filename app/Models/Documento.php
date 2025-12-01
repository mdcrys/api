<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Documento extends Model
{
    use HasFactory;

    protected $table = 'documentos';
    protected $primaryKey = 'id_documento';

    protected $fillable = [
        'id_serie_subserie',
        'titulo',
        'codigo_documento',
        'ruta_archivo',
        'nombre_archivo',
        'tipo_archivo',
        'tamano_archivo',
        'fecha_documento',
        'numero_documento',
        'fecha_firma',
        'usuario_registro',
        'estado',
          // 🔥 ESTE ES EL IMPORTANTE
        'parametros_indexados_values'
    ];

    protected $casts = [
        'fecha_documento' => 'date',
        'fecha_firma'     => 'date',
    ];

    // 🔗 Relación con Serie/Subserie
    public function serie()
    {
        return $this->belongsTo(\App\Models\Serie::class, 'id_serie_subserie', 'id_serie');
    }
}
