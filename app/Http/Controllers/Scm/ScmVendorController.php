<?php

namespace App\Http\Controllers\Scm;

use App\Http\Controllers\Controller;
use App\Models\Pro\Supplier;
use App\Models\Pro\VendorRegistration;
use App\Models\Pro\VendorRequirement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class ScmVendorController extends Controller
{
    public function index()
    {
        $registrations = VendorRegistration::with('requirements')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'business_name' => $r->business_name,
                'representative_name' => $r->representative_name,
                'email' => $r->email,
                'phone_number' => $r->phone_number,
                'address' => $r->address,
                'status' => $r->status,
                'rejection_reason' => $r->rejection_reason,
                'created_at' => $r->created_at,
                'requirements' => $r->requirements,
            ]);

        return Inertia::render('Dashboard/SCM/Vendors', [
            'registrations' => $registrations,
        ]);
    }

    public function approve(Request $request, VendorRegistration $registration)
    {
        $validated = $request->validate([
            'requirements' => 'nullable|array',
        ]);

        // The supplier should already exist from the registration process.
        // Find it by email, do NOT overwrite the password.
        $supplier = Supplier::where('email', $registration->email)->first();

        if (!$supplier) {
            // Fallback: create supplier (should never happen if registration flow works)
            // Use the original data from registration, but generate a random password
            // because we don't have the plaintext password here. The vendor will need to reset.
            $supplier = Supplier::create([
                'business_name' => $registration->business_name,
                'representative_name' => $registration->representative_name,
                'address' => $registration->address,
                'phone_number' => $registration->phone_number,
                'email' => $registration->email,
                'password' => Hash::make('temporary-reset-required'), // forces password reset
            ]);
        }

        // Update the registration status and link to supplier
        $registration->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
            'supplier_id' => $supplier->id,
            'rejection_reason' => null,
            'rejected_at' => null,
            'rejected_by' => null,
        ]);

        // Keep the suppliers table in sync — ECO reads approved
        // suppliers (and approval_status) from this column.
        $supplier->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_reason' => null,
        ]);

        // Save requirements if provided
        if ($validated['requirements']) {
            $registration->requirements()->delete();
            foreach ($validated['requirements'] as $req) {
                VendorRequirement::create([
                    'vendor_registration_id' => $registration->id,
                    'requirement_name' => $req['requirement_name'],
                    'description' => $req['description'] ?? null,
                    'value' => $req['value'] ?? null,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Vendor approved successfully.');
    }

    public function reject(Request $request, VendorRegistration $registration)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $registration->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'rejected_at' => now(),
            'rejected_by' => auth()->id(),
        ]);

        // Keep the suppliers table in sync so ECO reflects the outcome.
        $supplier = Supplier::where('email', $registration->email)->first();
        if ($supplier) {
            $supplier->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'rejected_at' => now(),
                'rejected_by' => auth()->id(),
            ]);
        }

        return redirect()->back()->with('success', 'Vendor registration rejected.');
    }
}