<?php

namespace App\Models\Man;

use App\Models\Inv\Material;
use Illuminate\Database\Eloquent\Model;

class PantoneFabricFormula extends Model
{
    protected $fillable = ['pantone_reference_id', 'yarn_material_id', 'combinations', 'is_demo_data'];

    protected $casts = ['combinations' => 'array', 'is_demo_data' => 'boolean'];

    public function yarnMaterial()
    {
        return $this->belongsTo(Material::class, 'yarn_material_id');
    }

    public function pantoneReference()
    {
        return $this->belongsTo(PantoneReference::class);
    }
}
