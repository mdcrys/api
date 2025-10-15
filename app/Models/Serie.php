<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Serie extends Model
{
    use HasFactory;

    protected $table = 'serie'; // Nombre de la tabla
    protected $primaryKey = 'id_serie';

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

    /**
     * 🔗 Relación con Proyecto
     */
    public function proyecto()
    {
        return $this->belongsTo(\App\Models\Proyecto::class, 'id_subseccion', 'id_proyecto');
    }

    /**
     * 🔗 Relación con Empresa
     */
    public function empresa()
    {
        return $this->belongsTo(\App\Models\Empresa::class, 'id_empresa');
    }

    /**
     * 🔗 Relación con la serie padre (recursiva)
     */
    public function padre()
    {
        return $this->belongsTo(Serie::class, 'padre_id');
    }

    /**
     * 🔗 Relación con series hijas (recursiva)
     */
    public function hijos()
    {
        return $this->hasMany(Serie::class, 'padre_id');
    }

    /**
     * 🔗 Relación recursiva para cargar todos los hijos de manera anidada
     */
     public function hijosRecursivos()
    {
        return $this->hijos()->with('hijosRecursivos', 'indexaciones');
    }
    /**
     * 🔗 Relación con documentos dentro de la serie
     */
   
   public function indexaciones()
{
    return $this->hasMany(\App\Models\Indexacion::class, 'id_serie', 'id_serie')
                ->orWhere('id_subserie', $this->id_serie);
}


}
