<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class LeadApprovalFile extends Model
{
    protected $fillable = ['lead_id', 'file_path', 'original_name', 'uploaded_by'];

    public function lead()
    {
        return $this->belongsTo(CrmLead::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
