<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmLogoPartner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ClientProfileController extends Controller
{
    public function edit()
    {
        $client = Auth::guard('client')->user();
        $client->load('logo');
        return Inertia::render('Client/Profile', ['client' => $client]);
    }

    public function update(Request $request)
    {
        $client = Auth::guard('client')->user();

        $validated = $request->validate([
            'company_name'    => 'sometimes|required|string|max:255',
            'business_type'   => 'sometimes|required|string|max:255',
            'tin_number'      => 'nullable|string|max:50',
            'contact_person'  => 'sometimes|required|string|max:255',
            'phone'           => 'sometimes|required|string|max:20',
            'company_address' => 'sometimes|required|string',
            'city'            => 'nullable|string|max:100',
            'province'        => 'nullable|string|max:100',
            'postal_code'     => 'nullable|string|max:20',
            'latitude'        => 'nullable|numeric|between:-90,90',
            'longitude'       => 'nullable|numeric|between:-180,180',
            'logo'            => 'nullable|image|mimes:png,jpg,jpeg,webp,svg|max:5120',
        ]);

        $data = [];

        if ($request->has('company_name')) {
            $data['company_name'] = $validated['company_name'];
        }
        if ($request->has('business_type')) {
            $data['business_type'] = $validated['business_type'];
        }
        if ($request->has('tin_number')) {
            $data['tin_number'] = $validated['tin_number'];
        }
        if ($request->has('contact_person')) {
            $data['contact_person'] = $validated['contact_person'];
        }
        if ($request->has('phone')) {
            $data['phone'] = $validated['phone'];
        }
        if ($request->has('company_address')) {
            $data['company_address'] = $validated['company_address'];
        }
        if ($request->has('city')) {
            $data['city'] = $validated['city'];
        }
        if ($request->has('province')) {
            $data['province'] = $validated['province'];
        }
        if ($request->has('postal_code')) {
            $data['postal_code'] = $validated['postal_code'];
        }
        if ($request->has('latitude')) {
            $data['latitude'] = $validated['latitude'] !== '' ? (float) $validated['latitude'] : null;
        }
        if ($request->has('longitude')) {
            $data['longitude'] = $validated['longitude'] !== '' ? (float) $validated['longitude'] : null;
        }

        $logoFile = $validated['logo'] ?? null;

        $client->update($data);

        if ($logoFile) {
            $existing = CrmLogoPartner::where('client_id', $client->id)->get();
            foreach ($existing as $old) {
                if ($old->logo_path) {
                    Storage::disk('public')->delete($old->logo_path);
                }
                $old->delete();
            }

            $path = $logoFile->store('crm-logos', 'public');
            CrmLogoPartner::create([
                'client_id' => $client->id,
                'logo_path' => $path,
                'original_name' => $logoFile->getClientOriginalName(),
            ]);
        }

        return back()->with('success', 'Profile updated.');
    }

    public function destroyLogo()
    {
        $client = Auth::guard('client')->user();

        $logos = CrmLogoPartner::where('client_id', $client->id)->get();
        foreach ($logos as $logo) {
            if ($logo->logo_path) {
                Storage::disk('public')->delete($logo->logo_path);
            }
            $logo->delete();
        }

        return back()->with('success', 'Company logo removed.');
    }

    /**
     * Save (upload/replace) the company logo on its own,
     * without submitting the rest of the profile form.
     */
    public function storeLogo(Request $request)
    {
        $client = Auth::guard('client')->user();

        $validated = $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,webp,svg|max:5120',
        ]);

        $existing = CrmLogoPartner::where('client_id', $client->id)->get();
        foreach ($existing as $old) {
            if ($old->logo_path) {
                Storage::disk('public')->delete($old->logo_path);
            }
            $old->delete();
        }

        $path = $validated['logo']->store('crm-logos', 'public');
        $logo = CrmLogoPartner::create([
            'client_id' => $client->id,
            'logo_path' => $path,
            'original_name' => $validated['logo']->getClientOriginalName(),
        ]);

        return back()->with('success', 'Company logo saved.');
    }
}