<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class ThemePreference extends Model
{
    protected $fillable = ['guard', 'user_id', 'theme'];
}
