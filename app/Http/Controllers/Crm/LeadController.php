<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Client;
use App\Models\Eco\CreditAccount;
use App\Models\Crm\CrmApproval;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLogoPartner;
use App\Models\Crm\CrmOpportunity;
use App\Models\Crm\CrmOpportunityHistory;
use App\Models\Crm\CrmStage;
use App\Models\Crm\LeadNote;
use App\Models\Crm\LeadInterview;
use App\Models\Crm\LeadApprovalFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Traits\HasPagePermissions;

class LeadController extends Controller
{
    use HasPagePermissions;

    /**
     * Slim intake inbox: leads are qualified here, then worked as
     * opportunities in the pipeline. Each lead carries its pipeline link
     * (if any) so the page never duplicates the deal board.
     */
    public function index()
    {
        $leads = CrmLead::with([
            'assignedStaff:id,name',
            'logo',
            'contacts' => fn ($q) => $q->where('is_primary', true)->limit(1),
            'opportunities' => fn ($q) => $q->select('id', 'lead_id', 'title', 'stage_id')->latest('id')->limit(1),
        ])
            ->withCount('opportunities')
            ->whereNotIn('status', ['Converted', 'Archived'])
            ->orderBy('created_at', 'asc')
            ->get();

        $permissions = $this->getPagePermissionsForModule('CRM');

        return Inertia::render('Dashboard/CRM/Lead', [
            'leads' => $leads,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a new lead (with duplicate-company guard).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_person' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'interest_fabric' => 'nullable|string',
            'estimated_value' => 'nullable|numeric|min:0',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp,svg|max:5120',
        ]);

        $dupe = CrmLead::whereNotIn('status', ['Converted', 'Archived', 'Lost'])
            ->where(fn ($q) => $q
                ->where('company_name', $validated['company_name'])
                ->orWhere('email', $validated['email']))
            ->first(['id', 'company_name', 'status']);
        if ($dupe) {
            return back()->withErrors(['error' => "Possible duplicate: {$dupe->company_name} is already an open lead ({$dupe->status})."]);
        }

        $lead = CrmLead::create([
            'company_name' => $validated['company_name'],
            'contact_person' => $validated['contact_person'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'interest_fabric' => $validated['interest_fabric'] ?? null,
            'estimated_value' => $validated['estimated_value'] ?? 0,
            'status' => 'Inquiry',
            'assigned_staff_id' => Auth::id(),
        ]);

        // Seed the prospect's primary contact from the intake details.
        $lead->contacts()->create([
            'name' => $lead->contact_person ?: $lead->email,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'is_decision_maker' => true,
            'is_primary' => true,
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('crm-logos', 'public');
            CrmLogoPartner::create([
                'crm_lead_id' => $lead->id,
                'logo_path' => $path,
                'original_name' => $request->file('logo')->getClientOriginalName(),
                'uploaded_by' => Auth::id(),
            ]);
        }

        return back()->with('message', 'New lead created.');
    }

    /**
     * BANT-lite qualification + next step (drives follow-up discipline).
     */
    public function qualify(Request $request, $id)
    {
        $data = $request->validate([
            'budget' => 'nullable|string|max:255',
            'authority' => 'nullable|string|max:255',
            'need_summary' => 'nullable|string|max:2000',
            'timeline' => 'nullable|string|max:255',
            'next_step' => 'nullable|string|max:255',
            'next_step_due' => 'nullable|date',
        ]);

        CrmLead::findOrFail($id)->update($data);

        return back()->with('message', 'Lead qualification saved.');
    }

    /**
     * Intake → pipeline: spin a qualified lead into an opportunity in the
     * first pipeline stage. The lead stays linked (lead_id) and the inbox
     * shows it as "In pipeline" instead of duplicating deal work here.
     */
    public function sendToPipeline(Request $request, $id)
    {
        $lead = CrmLead::with(['contacts', 'opportunities'])->findOrFail($id);

        if ($lead->opportunities->isNotEmpty()) {
            return back()->withErrors(['error' => 'This lead is already in the pipeline.']);
        }

        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'value' => 'nullable|numeric|min:0',
        ]);

        $stage = CrmStage::orderBy('sequence')->first();
        if (! $stage) {
            return back()->withErrors(['error' => 'No pipeline stages exist yet. Create one first.'])->withInput();
        }

        $primary = $lead->contacts->where('is_primary', true)->first() ?? $lead->contacts->first();

        $opp = CrmOpportunity::create([
            'title' => $data['title'] ?: trim(($lead->company_name ?? 'Untitled') . ($lead->interest_fabric ? " — {$lead->interest_fabric}" : '')),
            'stage_id' => $stage->id,
            'stage' => 'qualification',
            'lead_id' => $lead->id,
            'contact_id' => $primary?->id,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'value' => $data['value'] ?? $lead->estimated_value ?? 0,
            'probability' => $stage->default_probability,
            'source' => $lead->source ?? 'lead',
            'owner_id' => $lead->assigned_staff_id ?? Auth::id(),
        ]);

        CrmOpportunityHistory::create([
            'opportunity_id' => $opp->id,
            'from_stage' => null,
            'to_stage' => $stage->name,
            'changed_by' => Auth::id(),
            'notes' => "Qualified from lead: {$lead->company_name}.",
        ]);

        return back()->with('message', "“{$opp->title}” sent to {$stage->name}.");
    }

    /**
     * Update lead status (move to next stage).
     */
    public function updateStatus(Request $request, $id)
    {
        $lead = CrmLead::findOrFail($id);
        $lead->update(['status' => $request->status]);

        return back();
    }

    /**
     * Convert a lead to a business client.
     */
    public function convertToClient(Request $request)
    {
        $validated = $request->validate([
            'lead_id' => 'required|exists:crm_leads,id',
            'company_name' => 'required|string|max:255',
            'business_type' => 'required|string',
            'tin_number' => 'required|string|unique:clients,tin_number',
            'contact_person' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email',
            'phone' => 'required|string',
            'company_address' => 'required|string',
            'password' => 'required|string|min:8',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp,svg|max:5120',
        ]);

        try {
            DB::beginTransaction();

            $client = Client::create([
                'company_name' => $validated['company_name'],
                'business_type' => $validated['business_type'],
                'tin_number' => $validated['tin_number'],
                'contact_person' => $validated['contact_person'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'company_address' => $validated['company_address'],
                'password' => bcrypt($validated['password']),
                'status' => 'active',
            ]);

            if ($request->hasFile('logo')) {
                $path = $request->file('logo')->store('crm-logos', 'public');
                CrmLogoPartner::create([
                    'client_id' => $client->id,
                    'logo_path' => $path,
                    'original_name' => $request->file('logo')->getClientOriginalName(),
                    'uploaded_by' => Auth::id(),
                ]);
            } else {
                // Carry over the lead's logo, if any
                CrmLogoPartner::where('crm_lead_id', $validated['lead_id'])
                    ->update(['client_id' => $client->id, 'crm_lead_id' => null]);
            }

            CreditAccount::create([
                'client_id' => $client->id,
                'outstanding_balance' => 0.00,
                'is_good_payer' => 1,
            ]);

            $lead = CrmLead::find($validated['lead_id']);
            $lead->update(['status' => 'Converted']);

            // Carry the prospect's primary contact onto the new account.
            $primary = $lead->contacts()->where('is_primary', true)->first()
                ?? $lead->contacts()->first();
            $client->contacts()->create([
                'name' => $primary?->name ?? $client->contact_person ?: $client->email,
                'title' => $primary?->title,
                'email' => $primary?->email ?? $client->email,
                'phone' => $primary?->phone ?? $client->phone,
                'is_decision_maker' => (bool) ($primary?->is_decision_maker ?? true),
                'is_primary' => true,
                'notes' => 'Carried over from lead conversion.',
            ]);

            DB::commit();
            return back()->with('message', 'Lead converted to client successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Conversion failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Add a note to a lead (with approval for staff).
     */
    public function addNote(Request $request, $id)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        $lead = CrmLead::findOrFail($id);

        if (in_array($user->position, ['manager', 'vice_president'], true) || in_array($user->role, ['CEO', 'COO'], true)) {
            $lead->notes()->create([
                'user_id' => $user->id,
                'note' => $validated['note'],
            ]);
            return back()->with('message', 'Note added.');
        }

        // Staff needs approval
        CrmApproval::create([
            'user_id' => $user->id,
            'action' => 'add_note',
            'data' => [
                'lead_id' => $lead->id,
                'note' => $validated['note'],
            ],
            'status' => 'pending',
        ]);

        return back()->with('message', 'Note submitted for approval.');
    }

    /**
     * Schedule an interview for a lead (with approval for staff).
     */
    public function scheduleInterview(Request $request, $id)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'scheduled_at' => 'required|date',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $lead = CrmLead::findOrFail($id);

        if (in_array($user->position, ['manager', 'vice_president'], true) || in_array($user->role, ['CEO', 'COO'], true)) {
            $lead->interviews()->create([
                'user_id' => $user->id,
                'scheduled_at' => $validated['scheduled_at'],
                'location' => $validated['location'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
            return back()->with('message', 'Interview scheduled.');
        }

        CrmApproval::create([
            'user_id' => $user->id,
            'action' => 'schedule_interview',
            'data' => array_merge(['lead_id' => $lead->id], $validated),
            'status' => 'pending',
        ]);

        return back()->with('message', 'Interview request submitted for approval.');
    }

    /**
     * Upload approval file for a lead (with approval for staff).
     */
    public function uploadApprovalFile(Request $request, $id)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $lead = CrmLead::findOrFail($id);

        if (in_array($user->position, ['manager', 'vice_president'], true) || in_array($user->role, ['CEO', 'COO'], true)) {
            $path = $request->file('file')->store('lead_approval_files', 'public');
            $lead->approvalFiles()->create([
                'file_path' => $path,
                'original_name' => $request->file('file')->getClientOriginalName(),
                'uploaded_by' => $user->id,
            ]);
            return back()->with('message', 'File uploaded.');
        }

        // Staff: store temporarily
        $path = $request->file('file')->store('temp_approval_files', 'public');
        CrmApproval::create([
            'user_id' => $user->id,
            'action' => 'upload_approval_file',
            'data' => [
                'lead_id' => $lead->id,
                'file_path' => $path,
                'original_name' => $request->file('file')->getClientOriginalName(),
            ],
            'status' => 'pending',
        ]);

        return back()->with('message', 'File upload submitted for approval.');
    }

    /**
     * Accept lead (move to Closed-Won) – manager/CEO only.
     */
    public function acceptLead($id)
    {
        $user = Auth::user();
        $lead = CrmLead::findOrFail($id);

        if (in_array($user->position, ['manager', 'vice_president'], true) || in_array($user->role, ['CEO', 'COO'], true)) {
            $lead->update(['status' => 'Closed-Won']);
            return back()->with('message', 'Lead accepted and moved to Closed-Won.');
        }

        CrmApproval::create([
            'user_id' => $user->id,
            'action' => 'accept_lead',
            'data' => ['lead_id' => $lead->id],
            'status' => 'pending',
        ]);

        return back()->with('message', 'Accept request submitted for approval.');
    }

    /**
     * Reject lead (move to Lost) – manager/CEO only.
     */
    public function rejectLead(Request $request, $id)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'reject_reason' => 'required|string|max:255',
        ]);

        $lead = CrmLead::findOrFail($id);

        if (in_array($user->position, ['manager', 'vice_president'], true) || in_array($user->role, ['CEO', 'COO'], true)) {
            $lead->update(['status' => 'Lost', 'lost_reason' => $validated['reject_reason']]);
            return back()->with('message', 'Lead rejected and marked as Lost.');
        }

        CrmApproval::create([
            'user_id' => $user->id,
            'action' => 'reject_lead',
            'data' => [
                'lead_id' => $lead->id,
                'lost_reason' => $validated['reject_reason'],
            ],
            'status' => 'pending',
        ]);

        return back()->with('message', 'Rejection request submitted for approval.');
    }
}