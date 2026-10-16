<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmLogoPartner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Traits\HasPagePermissions;

class CrmLogoPartnerController extends Controller
{
    use HasPagePermissions;

    /**
     * Upload (or replace) a partner logo for a client or lead.
     */
    public function upload(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'crm_lead_id' => 'nullable|exists:crm_leads,id',
            'logo' => 'required|image|mimes:png,jpg,jpeg,webp,svg|max:5120',
        ]);

        if (empty($validated['client_id']) && empty($validated['crm_lead_id'])) {
            return back()->withErrors(['logo' => 'Attach the logo to a client or a lead.']);
        }

        $path = $request->file('logo')->store('crm-logos', 'public');

        $logo = CrmLogoPartner::create([
            'client_id' => $validated['client_id'] ?? null,
            'crm_lead_id' => $validated['crm_lead_id'] ?? null,
            'logo_path' => $path,
            'original_name' => $request->file('logo')->getClientOriginalName(),
            'uploaded_by' => Auth::id(),
        ]);

        return back()->with('message', 'Company logo uploaded.');
    }

    /**
     * Delete a partner logo (file + record).
     */
    public function destroy($id)
    {
        $logo = CrmLogoPartner::findOrFail($id);

        $user = Auth::user();
        $isOwner = $logo->uploaded_by === $user->id;
        $isManager = in_array($user->position, ['manager', 'general_manager', 'special_officer', 'vice_president'], true);
        $isCrmRole = in_array($user->role, ['CRM', 'CEO', 'COO'], true);

        abort_unless($isOwner || $isManager || $isCrmRole, 403);

        if ($logo->logo_path) {
            Storage::disk('public')->delete($logo->logo_path);
        }
        $logo->delete();

        return back()->with('message', 'Company logo removed.');
    }
}
