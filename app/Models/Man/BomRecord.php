<?php

namespace App\Models\Man;

use App\Models\Crm\Client;
use App\Models\Inv\Material;
use App\Models\Inv\Product;
use Illuminate\Database\Eloquent\Model;

class BomRecord extends Model
{
    protected $table = 'bom_records';

    protected $fillable = [
        'client_id',
        'product_id',
        'yarn_type',
        'dye_color',
        'weave_design',
        'materials',
    ];

    protected $casts = [
        'materials' => 'array',
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
