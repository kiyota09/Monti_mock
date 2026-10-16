<?php

namespace App\Models\Crm;

use App\Models\Core\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class LeadNote extends Model
{
    protected $fillable = ['lead_id', 'user_id', 'note'];

    public function lead()
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
