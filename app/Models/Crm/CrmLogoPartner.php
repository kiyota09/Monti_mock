<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmLogoPartner extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'crm_lead_id',
        'logo_path',
        'original_name',
        'uploaded_by',
    ];

    protected $appends = ['logo_url'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function lead()
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getLogoUrlAttribute()
    {
        // Root-relative path (same convention as the rest of the app:
        // `/storage/...`). Storage::url() returns an absolute URL bound to
        // APP_URL (http://localhost), which breaks when the app is browsed
        // via 127.0.0.1, artisan serve port, or LAN host — image 404s and
        // only the alt text shows.
        return $this->logo_path ? '/storage/' . ltrim($this->logo_path, '/') : null;
    }
}
