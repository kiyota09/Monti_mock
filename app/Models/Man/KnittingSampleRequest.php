<?php

namespace App\Models\Man;

use Illuminate\Database\Eloquent\Model;

class KnittingSampleRequest extends Model
{
    public const STATUS_DISCARDED = 'discarded';

    protected $fillable = [
        'code', 'fabric_sample_request_id', 'color_index', 'fabric_design', 'texture', 'gsm',
        'yarn_material_id', 'yarn_type', 'pantone_name', 'pantone_hex', 'formula',
        'requested_weight', 'status', 'requested_by', 'operator_id', 'machine_id',
        'produced_weight', 'remarks', 'processed_at',
    ];

    protected $casts = ['formula' => 'array', 'processed_at' => 'datetime'];

    public function fabricSampleRequest() { return $this->belongsTo(\App\Models\Crm\FabricSampleRequest::class); }
    public function operator() { return $this->belongsTo(\App\Models\Core\User::class, 'operator_id'); }
    public function requestedBy() { return $this->belongsTo(\App\Models\Core\User::class, 'requested_by'); }
    public function machine() { return $this->belongsTo(Machine::class); }
}
