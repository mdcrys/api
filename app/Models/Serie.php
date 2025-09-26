<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Serie extends Model
{
    use HasFactory;

    protected $table = 'serie'; // nombre de la tabla

    protected $primaryKey = 'id_serie'; // clave primaria

    protected $fillable = [
        'id_subseccion',
        'id_empresa',
        'padre_id',
        'nombre',
        'descripcion',
        'nivel',
        'estado',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    // Si quieres usar soft deletes
    protected $dates = ['deleted_at'];

    // Relaciones
    public function proyecto()
    {
        return $this->belongsTo(\App\Models\Proyecto::class, 'id_proyecto');
    }

    public function empresa()
    {
        return $this->belongsTo(\App\Models\Empresa::class, 'id_empresa');
    }

    public function padre()
    {
        return $this->belongsTo(Serie::class, 'padre_id');
    }

    public function hijos()
    {
        return $this->hasMany(Serie::class, 'padre_id');
    }
}
