<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HrmEmploymentType extends Model
{
    protected $table = 'hrm_employment_types';
    protected $fillable = ['name', 'slug', 'description', 'is_active', 'archived_at'];
    protected $casts = ['is_active' => 'boolean', 'archived_at' => 'datetime'];

    protected static function booted(): void
    {
        static::saving(function ($m) {
            if (empty($m->slug) && ! empty($m->name)) {
                $m->slug = Str::slug($m->name);
            }
        });
    }
}
