<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Crm\Client;
use App\Models\Crm\CrmContact;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmOpportunity;
use App\Models\Crm\CrmOpportunityHistory;
use App\Models\Crm\CrmRegistrationInvite;
use App\Models\Crm\CrmStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Public registration form reached from the registration button inside a
 * CRM-sent email. Submitting it creates a lead + contact + a deal in the
 * first open Opportunities stage.
 */
class CrmRegistrationController extends Controller
{
    public function show(string $token)
    {
        $invite = CrmRegistrationInvite::with(['thread:id,from_email,from_name', 'sender:id,name'])
            ->where('token', $token)
            ->firstOrFail();

        return Inertia::render('Crm/RegistrationForm', [
            'token' => $token,
            'invite' => [
                'email' => $invite->email,
                'name' => $invite->name,
                'company_name' => $invite->thread?->from_name,
                'sent_by' => $invite->sender?->name,
                'expires_at' => $invite->expires_at?->toIso8601String(),
            ],
            'registered' => (bool) $invite->used_at,
            'expired' => $invite->isExpired(),
            'opportunity' => $invite->used_at
                ? optional($invite->opportunity)->only(['id', 'title'])
                : null,
        ]);
    }

    public function store(Request $request, string $token)
    {
        $invite = CrmRegistrationInvite::with('thread:id,from_email,from_name')
            ->where('token', $token)
            ->firstOrFail();

        if (! $invite->isUsable()) {
            return redirect()
                ->route('crm.registration.show', $token)
                ->withErrors(['error' => $invite->used_at
                    ? 'This registration form has already been submitted.'
                    : 'This registration link has expired.']);
        }

        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'business_type' => 'nullable|string|max:100',
            'contact_person' => 'required|string|max:255',
            'phone' => 'required|string|max:32',
            'company_address' => 'required|string|max:1000',
            'tin_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',
        ]);

        $email = strtolower(trim($invite->email));

        $staffId = $invite->sent_by
            ?: User::where('role', 'CRM')->where('position', 'manager')->value('id')
            ?: User::where('role', 'CRM')->value('id')
            ?: User::orderBy('id')->value('id');

        if (! $staffId) {
            return back()->withErrors(['error' => 'Registration could not be completed because no CRM owner is configured.']);
        }

        $stage = CrmStage::pipelineOrder()->first(fn (CrmStage $s) => ! $s->isTerminal());
        if (! $stage) {
            $stage = CrmStage::create([
                'name' => 'New',
                'sequence' => 0,
                'default_probability' => 10,
                'is_folded' => false,
                'is_won' => false,
                'is_lost' => false,
                'created_by' => $staffId,
            ]);
        }

        try {
            DB::beginTransaction();

            $lead = CrmLead::firstOrCreate(
                ['email' => $email],
                [
                    'company_name' => $data['company_name'],
                    'contact_person' => $data['contact_person'],
                    'phone' => $data['phone'],
                    'interest_fabric' => 'Not specified',
                    'estimated_value' => 0,
                    'status' => 'Inquiry',
                    'assigned_staff_id' => $staffId,
                    'source' => 'registration_form',
                    'need_summary' => $data['notes'] ?? null,
                ],
            );

            $contact = CrmContact::firstOrCreate(
                ['email' => $email],
                [
                    'lead_id' => $lead->id,
                    'name' => $data['contact_person'],
                    'organization' => $data['company_name'],
                    'phone' => $data['phone'],
                    'is_decision_maker' => true,
                    'is_primary' => true,
                    'notes' => $data['notes'] ?? null,
                ],
            );

            if (! $contact->lead_id) {
                $contact->update(['lead_id' => $lead->id]);
            }

            $client = Client::whereRaw('LOWER(email) = ?', [$email])->first();

            $opportunity = CrmOpportunity::create([
                'title' => $data['company_name'] . ' - Registration',
                'stage_id' => $stage->id,
                'stage' => 'qualification',
                'contact_id' => $contact->id,
                'client_id' => $client?->id,
                'lead_id' => $lead->id,
                'value' => 0,
                'priority' => 0,
                'probability' => $stage->default_probability,
                'email' => $email,
                'phone' => $data['phone'],
                'owner_id' => $staffId,
                'source' => 'registration_form',
                'medium' => 'email',
                'internal_notes' => trim(
                    "Submitted through the emailed registration form.\n"
                    . 'Business type: ' . ($data['business_type'] ?: 'Not specified') . "\n"
                    . 'Address: ' . $data['company_address'] . "\n"
                    . 'TIN: ' . ($data['tin_number'] ?: 'Not provided') . "\n"
                    . 'Notes: ' . ($data['notes'] ?: '-')
                ),
            ]);

            CrmOpportunityHistory::create([
                'opportunity_id' => $opportunity->id,
                'from_stage' => null,
                'to_stage' => $stage->name,
                'changed_by' => $staffId,
                'notes' => 'Registered through the emailed registration form.',
            ]);

            $invite->update([
                'used_at' => now(),
                'lead_id' => $lead->id,
                'opportunity_id' => $opportunity->id,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CRM registration form failed for ' . $email . ': ' . $e->getMessage());

            return back()->withErrors(['error' => 'Registration could not be completed. Please try again.']);
        }

        return redirect()
            ->route('crm.registration.show', $token)
            ->with('message', 'Registration submitted! Our CRM team will reach out shortly.');
    }
}
