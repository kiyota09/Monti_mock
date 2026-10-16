<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;



class Position extends Model
{
    protected $table = 'position_to_applicants';

    protected $fillable = [

        'position',
        
        'status',

    ];
}
