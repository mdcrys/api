<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Serie;
use App\Models\Empresa;
use App\Models\Modulo;

class Proyecto extends Model
{
    protected $table = 'proyecto';
    protected $primaryKey = 'id_proyecto';
    public $timestamps = true;

    protected $fillable = [
        'id_empresa',
        'nombre',
        'padre_id',
        'nivel',
        'estado',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * 🔗 Relación recursiva: hijos (subsecciones)
     */
    public function subsecciones()
    {
        return $this->hasMany(Proyecto::class, 'padre_id', 'id_proyecto')
                    ->with([
                        'subsecciones',     // Subniveles recursivos
                        'series.padre',     // Padre de cada serie
                        'series.hijosRecursivos' // Hijos de cada serie
                    ]);
    }

    /**
     * 🔗 Relación recursiva: padre
     */
    public function parent()
    {
        return $this->belongsTo(Proyecto::class, 'padre_id', 'id_proyecto')
                    ->with('parent'); // Carga recursiva hacia arriba
    }

    /**
     * 🔗 Relación con Empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    /**
     * 🔗 Relación con Módulos
     */
    public function modulos()
    {
        return $this->hasMany(Modulo::class, 'id_proyecto');
    }

    /**
     * 🔗 Relación con Series
     */
    public function series()
    {
        return $this->hasMany(Serie::class, 'id_subseccion', 'id_proyecto')
                    ->with(['padre', 'hijosRecursivos']); // Padre e hijos recursivos de la serie
    }

    // App\Models\Proyecto.php
public function indexaciones()
{
    return $this->hasMany(\App\Models\Indexacion::class, 'id_proyecto', 'id_proyecto')
                ->orWhere('id_subseccion', $this->id_proyecto)
                ->orWhere('id_subseccion2', $this->id_proyecto);
}



}
