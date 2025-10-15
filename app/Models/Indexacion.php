<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Indexacion extends Model
{
    // ✅ Nombre correcto de la tabla
    protected $table = 'indexaciones';
    protected $primaryKey = 'id_indexacion';

    // ✅ Campos permitidos
    protected $fillable = [
        'id_proyecto',
        'id_subseccion',
        'id_subseccion2',
        'id_serie',
        'id_subserie',
        'id_modulo',
        'campos_extra',
        'archivo_url',
        'estado',
        'creado_en',
        'updated_at',
    ];

    // ✅ Si tu tabla NO usa created_at y updated_at automáticos
    public $timestamps = false;

    // ✅ Convertir JSON y fechas
    protected $casts = [
        'campos_extra' => 'array',
        'creado_en' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // 🔗 Relación con Proyecto
    public function proyecto()
    {
        return $this->belongsTo(\App\Models\Proyecto::class, 'id_proyecto', 'id_proyecto');
    }

    // 🔗 Relación con Subsección 1
    public function subseccion()
    {
        return $this->belongsTo(\App\Models\Proyecto::class, 'id_subseccion', 'id_proyecto');
    }

    // 🔗 Relación con Subsección 2
    public function subseccion2()
    {
        return $this->belongsTo(\App\Models\Proyecto::class, 'id_subseccion2', 'id_proyecto');
    }

    // 🔗 Relación con Serie principal
    public function serie()
    {
        return $this->belongsTo(\App\Models\Serie::class, 'id_serie', 'id_serie');
    }

    // 🔗 Relación con Subserie (si aplica)
    public function subserie()
    {
        return $this->belongsTo(\App\Models\Serie::class, 'id_subserie', 'id_serie');
    }
}
