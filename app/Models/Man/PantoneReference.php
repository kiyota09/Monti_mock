<?php

namespace App\Models\Man;

use Illuminate\Database\Eloquent\Model;

class PantoneReference extends Model
{
    protected $fillable = ['name', 'hex', 'color_category', 'combinations', 'created_by'];

    protected $casts = ['combinations' => 'array'];

    public function fabricFormulas()
    {
        return $this->hasMany(PantoneFabricFormula::class)->with('yarnMaterial');
    }
}
