<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Modulo;

class Proyecto extends Model
{
    protected $table = 'proyecto';
    protected $primaryKey = 'id_proyecto';
    public $timestamps = true;

    protected $fillable = [
        'id_empresa',
        'nombre',
        'estado',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // Relación con empresa
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }


    public function modulos()
    {
        return $this->hasMany(Modulo::class, 'id_proyecto');
    }

    
}
