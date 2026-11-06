<?php

namespace App\Models\Man;

use App\Models\Crm\Client;
use App\Models\Inv\Material;
use App\Models\Inv\Product;
use App\Models\Crm\FabricSampleRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BomRecord extends Model
{
    protected $table = 'bom_records';

    protected $fillable = [
        'client_id',
        'product_id',
        'fabric_sample_request_id',
        'yarn_type',
        'dye_color',
        'weave_design',
        'materials',
        'dyeing_formula',
    ];

    protected $casts = [
        'materials' => 'array',
        'dyeing_formula' => 'array',
    ];

    /**
     * The client that this recipe belongs to.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The product that this recipe is for.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function fabricSampleRequest()
    {
        return $this->belongsTo(FabricSampleRequest::class, 'fabric_sample_request_id');
    }

    /** Sample rounds also keep the recipe_id link; use it for older records
     * created before the direct fabric_sample_request_id was populated. */
    public function sampleRequests(): HasMany
    {
        return $this->hasMany(FabricSampleRequest::class, 'recipe_id');
    }

    /**
     * Check if this recipe uses a specific material.
     */
    public function hasMaterial($materialId): bool
    {
        return isset($this->materials[$materialId]);
    }

    /**
     * Get the material IDs used in this recipe.
     */
    public function getMaterialIds(): array
    {
        return array_keys($this->materials ?? []);
    }
}
