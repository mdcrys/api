<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Modulo;
use App\Models\Empresa;

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
                    ->with('subsecciones'); // Carga recursiva hacia abajo
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
}
