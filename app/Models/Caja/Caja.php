<?php

namespace App\Models\Caja;

use Carbon\Carbon;
use App\Models\Configuration\Sucursale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Caja extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        "sucursale_id",
        "type",
        "amount"
    ];
    public function setCreatedAtAttribute($value) {
        date_default_timezone_set("America/Lima");
        $this->attributes["created_at"] = Carbon::now();
    }
    public function setUpdatedAtAttribute($value) {
        date_default_timezone_set("America/Lima");
        $this->attributes["updated_at"] = Carbon::now();
    }

    public function sucursale() {
        return $this->belongsTo(Sucursale::class,"sucursale_id");
    }
}
