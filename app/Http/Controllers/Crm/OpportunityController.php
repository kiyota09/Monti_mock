<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Mail\ClientWelcomeMail;
use App\Models\Crm\Client;
use App\Models\Crm\CrmContact;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmOpportunity;
use App\Models\Crm\CrmOpportunityHistory;
use App\Models\Crm\CrmStage;
use App\Models\Eco\CreditAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OpportunityController extends Controller
{
    /**
     * Deal pipeline board (Odoo-style kanban). Delegates to PipelineController
     * so the board and the detail page share one prop builder.
     */
    public function index()
    {
        return app(PipelineController::class)->index();
    }

    public function show(CrmOpportunity $opportunity)
    {
        return app(PipelineController::class)->show($opportunity);
    }

    /**
     * Quick-create from a stage "+" or the "New" button.
     * Accepts an existing contact_id OR an inline new-contact payload
     * (contact_name + organization + email + phone).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'stage_id' => 'nullable|exists:crm_stages,id',
            'contact_id' => 'nullable|exists:crm_contacts,id',
            'contact_name' => 'nullable|string|max:255',
            'organization' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:32',
            'client_id' => 'nullable|exists:clients,id',
            'lead_id' => 'nullable|exists:crm_leads,id',
            'value' => 'required|numeric|min:0',
            'priority' => 'nullable|integer|min:0|max:3',
            'expected_close' => 'nullable|date',
        ]);

        $contactId = $data['contact_id'] ?? null;
        $clientId = $data['client_id'] ?? null;
        $leadId = $data['lead_id'] ?? null;

        if ($contactId) {
            $contact = CrmContact::find($contactId);
            $clientId ??= $contact?->client_id;
        } elseif (! empty($data['contact_name'])) {
            $contact = CrmContact::create([
                'name' => $data['contact_name'],
                'organization' => $data['organization'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'client_id' => $clientId,
                'lead_id' => $leadId,
                'is_primary' => false,
            ]);
            $contactId = $contact->id;
        }

        if (empty($contactId) && empty($clientId) && empty($leadId)) {
            return back()->withErrors(['error' => 'Link the deal to a contact, account or lead.'])->withInput();
        }

        $stage = isset($data['stage_id'])
            ? CrmStage::find($data['stage_id'])
            : CrmStage::orderBy('sequence')->first();
        if (! $stage) {
            return back()->withErrors(['error' => 'No pipeline stages exist yet. Create one first.'])->withInput();
        }

        // Fallbacks from the linked records.
        $contact = $contactId ? CrmContact::find($contactId) : null;
        $email = $data['email'] ?? $contact?->email;
        $phone = $data['phone'] ?? $contact?->phone;

        $opp = CrmOpportunity::create([
            'title' => $data['title'],
            'stage_id' => $stage->id,
            'stage' => $this->legacyStageKey($stage),
            'contact_id' => $contactId,
            'client_id' => $clientId,
            'lead_id' => $leadId,
            'value' => $data['value'],
            'priority' => $data['priority'] ?? 0,
            'probability' => $stage->default_probability,
            'expected_close' => $data['expected_close'] ?? null,
            'email' => $email,
            'phone' => $phone,
            'owner_id' => Auth::id(),
        ]);

        CrmOpportunityHistory::create([
            'opportunity_id' => $opp->id,
            'from_stage' => null,
            'to_stage' => $stage->name,
            'changed_by' => Auth::id(),
            'notes' => 'Deal opened.',
        ]);

        return back()->with('message', "“{$opp->title}” opened in {$stage->name}.");
    }

    /**
     * Full edit from the opportunity form.
     */
    public function update(Request $request, CrmOpportunity $opportunity)
    {
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'contact_id' => 'nullable|exists:crm_contacts,id',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:32',
            'value' => 'sometimes|required|numeric|min:0',
            'probability' => 'sometimes|required|integer|min:0|max:100',
            'priority' => 'sometimes|required|integer|min:0|max:3',
            'owner_id' => 'nullable|exists:users,id',
            'expected_close' => 'nullable|date',
            'internal_notes' => 'nullable|string|max:5000',
            'source' => 'nullable|string|max:120',
            'medium' => 'nullable|string|max:120',
            'campaign' => 'nullable|string|max:120',
            'referred_by' => 'nullable|string|max:255',
            'stage_id' => 'sometimes|required|exists:crm_stages,id',
        ]);

        if (isset($data['stage_id']) && (int) $data['stage_id'] !== (int) $opportunity->stage_id) {
            $from = $opportunity->stageRef?->name ?? $opportunity->stage;
            $to = CrmStage::find($data['stage_id']);

            // Same forward-only rule as drag-and-drop moves.
            $fromStage = $opportunity->stageRef;
            if ($fromStage && ! $to->is_lost
                && (int) $to->default_probability < (int) $fromStage->default_probability) {
                return back()->withErrors([
                    'error' => "Deals only move forward — “{$to->name}” is behind “{$fromStage->name}”. Move it to a Lost stage to close it.",
                ]);
            }

            $data['probability'] = $data['probability'] ?? $to->default_probability;
            $data['stage'] = $this->legacyStageKey($to);
            $opportunity->update($data);
            CrmOpportunityHistory::create([
                'opportunity_id' => $opportunity->id,
                'from_stage' => $from,
                'to_stage' => $to->name,
                'changed_by' => Auth::id(),
                'notes' => 'Moved from the opportunity form.',
            ]);
        } else {
            $opportunity->update($data);
        }

        return back()->with('message', 'Opportunity saved.');
    }

    public function setPriority(Request $request, CrmOpportunity $opportunity)
    {
        $data = $request->validate(['priority' => 'required|integer|min:0|max:3']);
        $opportunity->update(['priority' => $data['priority']]);

        return back()->with('message', 'Priority updated.');
    }

    /**
     * Won deal → business client account. Creates the client portal login,
     * links the opportunity (and its lead) to it, and seeds a credit account.
     */
    public function convertToClient(Request $request, CrmOpportunity $opportunity)
    {
        $opportunity->loadMissing(['stageRef', 'contact', 'lead', 'client']);

        if (! $opportunity->stageRef?->is_won) {
            return back()->withErrors(['error' => 'Only deals in the Won stage can be converted to a client account.']);
        }
        if ($opportunity->client_id) {
            return back()->withErrors(['error' => 'This deal already has a client account.']);
        }

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'business_type' => 'required|string',
            'tin_number' => 'required|string|unique:clients,tin_number',
            'contact_person' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email',
            'phone' => 'required|string',
            'company_address' => 'required|string',
            'password' => 'required|string|min:8',
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
                'must_change_password' => true,
                'status' => 'active',
            ]);

            CreditAccount::create([
                'client_id' => $client->id,
                'outstanding_balance' => 0.00,
                'is_good_payer' => 1,
            ]);

            $contact = $opportunity->contact;
            $client->contacts()->create([
                'name' => $contact?->name ?? $client->contact_person ?: $client->email,
                'title' => $contact?->title,
                'email' => $contact?->email ?? $client->email,
                'phone' => $contact?->phone ?? $client->phone,
                'is_decision_maker' => (bool) ($contact?->is_decision_maker ?? true),
                'is_primary' => true,
                'notes' => 'Carried over from won opportunity.',
            ]);

            $opportunity->update(['client_id' => $client->id]);

            // Attach any files uploaded from this card to the new client too.
            $opportunity->documents()->whereNull('client_id')->update(['client_id' => $client->id]);

            if ($opportunity->lead_id) {
                CrmLead::where('id', $opportunity->lead_id)->update(['status' => 'Converted']);
            }

            CrmOpportunityHistory::create([
                'opportunity_id' => $opportunity->id,
                'from_stage' => $opportunity->stageRef?->name,
                'to_stage' => $opportunity->stageRef?->name,
                'changed_by' => Auth::id(),
                'notes' => "Converted to client account: {$client->company_name}.",
            ]);

            DB::commit();

            // Welcome email with portal credentials — best-effort, never blocks
            // the account from being created.
            $emailSent = true;
            try {
                Mail::to($client->email)->send(new ClientWelcomeMail(
                    companyName: $client->company_name,
                    contactPerson: $client->contact_person,
                    email: $client->email,
                    password: $validated['password'],
                    portalUrl: route('client.login'),
                ));
            } catch (\Throwable $e) {
                $emailSent = false;
                Log::warning('Client welcome email failed for client #' . $client->id . ': ' . $e->getMessage());
            }

            return back()->with('message', $emailSent
                ? "Client account created for {$client->company_name}. Sign-in details emailed to {$client->email}."
                : "Client account created for {$client->company_name}, but the welcome email could not be sent.");
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Account creation failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Drag-and-drop / chevron move. Accepts canonical stage_id (preferred)
     * or a legacy stage key / stage name for BC.
     */
    public function move(Request $request, CrmOpportunity $opportunity)
    {
        $data = $request->validate([
            'stage_id' => 'nullable|exists:crm_stages,id',
            'stage' => 'nullable|string',
            'lost_reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        $target = null;
        if (! empty($data['stage_id'])) {
            $target = CrmStage::findOrFail($data['stage_id']);
        } elseif (! empty($data['stage'])) {
            // Legacy key (qualification|sampling|…) or exact stage name.
            $target = CrmStage::where('name', $data['stage'])->first()
                ?? $this->stageForLegacyKey($data['stage']);
            if (! $target) {
                // Legacy terminal keys with no matching column: keep legacy behavior.
                return $this->moveLegacy($opportunity, $data);
            }
        } else {
            return back()->withErrors(['error' => 'Choose a target stage.']);
        }

        // Forward-only rule: a deal may only advance to a stage with an equal or
        // higher default probability, or be dropped into a Lost stage.
        $fromStage = $opportunity->stageRef ?? CrmStage::find($opportunity->stage_id);
        if ($fromStage && ! $target->is_lost
            && (int) $target->default_probability < (int) $fromStage->default_probability) {
            return back()->withErrors([
                'error' => "Deals only move forward — “{$target->name}” is behind “{$fromStage->name}”. Move it to a Lost stage to close it.",
            ]);
        }

        $from = $opportunity->stageRef?->name ?? $opportunity->stage;
        $opportunity->update([
            'stage_id' => $target->id,
            'stage' => $this->legacyStageKey($target),
            'probability' => $target->default_probability,
            'lost_reason' => null,
        ]);
        CrmOpportunityHistory::create([
            'opportunity_id' => $opportunity->id,
            'from_stage' => $from,
            'to_stage' => $target->name,
            'changed_by' => Auth::id(),
            'notes' => $data['notes'] ?? null,
        ]);

        $this->maybeCloseLead($opportunity, $target);

        return back()->with('message', "Deal moved to {$target->name}.");
    }

    /**
     * Lost deal → Archives. Marks the deal as totally failed and hides it
     * from the board (it lives in the Archives tab from now on).
     */
    public function archive(CrmOpportunity $opportunity)
    {
        $opportunity->loadMissing('stageRef');

        if (! $opportunity->stageRef?->is_lost) {
            return back()->withErrors(['error' => 'Only deals in a Lost stage can be archived.']);
        }
        if ($opportunity->archived_at) {
            return back()->with('message', 'This deal is already archived.');
        }

        $opportunity->update(['archived_at' => now()]);

        CrmOpportunityHistory::create([
            'opportunity_id' => $opportunity->id,
            'from_stage' => $opportunity->stageRef?->name,
            'to_stage' => 'Archived',
            'changed_by' => Auth::id(),
            'notes' => 'Deal archived as totally failed.',
        ]);

        return back()->with('message', "“{$opportunity->title}” moved to Archives.");
    }

    // ── Legacy helpers (BC for the old fixed stage list) ──────────────

    protected function legacyStageKey(CrmStage $stage): string
    {
        if ($stage->is_won) {
            return 'won';
        }
        if ($stage->is_lost) {
            return 'lost';
        }
        $name = strtolower($stage->name);
        foreach (['qualification', 'sampling', 'quotation', 'negotiation'] as $key) {
            if (str_contains($name, $key)) {
                return $key;
            }
        }
        // Lost-style columns map to lost so forecasts stay correct.
        if (str_contains($name, 'lost') || str_contains($name, 'cancel')) {
            return 'lost';
        }

        return 'qualification';
    }

    protected function stageForLegacyKey(string $key): ?CrmStage
    {
        $stages = CrmStage::orderBy('sequence')->get();
        $byName = $stages->first(fn ($s) => strtolower($s->name) === strtolower($key));
        if ($byName) {
            return $byName;
        }
        $map = [
            'qualification' => ['new', 'qualif'],
            'sampling' => ['sampl'],
            'quotation' => ['quot', 'proposition', 'proposal'],
            'negotiation' => ['negot'],
            'won' => ['won'],
            'lost' => ['lost'],
        ];
        foreach ($map[$key] ?? [] as $needle) {
            $hit = $stages->first(fn ($s) => str_contains(strtolower($s->name), $needle));
            if ($hit) {
                return $hit;
            }
        }

        return null;
    }

    protected function moveLegacy(CrmOpportunity $opportunity, array $data)
    {
        if (! in_array($data['stage'], CrmOpportunity::allowedMoves($opportunity->stage), true)) {
            return back()->withErrors(['error' => "Cannot move deal from {$opportunity->stage} to {$data['stage']}."]);
        }
        if ($data['stage'] === 'lost' && empty($data['lost_reason'])) {
            return back()->withErrors(['error' => 'A lost reason is required to close a deal as lost.']);
        }

        $from = $opportunity->stage;
        $opportunity->update([
            'stage' => $data['stage'],
            'probability' => CrmOpportunity::STAGES[$data['stage']],
            'lost_reason' => $data['stage'] === 'lost' ? $data['lost_reason'] : null,
        ]);
        CrmOpportunityHistory::create([
            'opportunity_id' => $opportunity->id,
            'from_stage' => $from,
            'to_stage' => $data['stage'],
            'changed_by' => Auth::id(),
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('message', "Deal moved to {$data['stage']}.");
    }

    protected function maybeCloseLead(CrmOpportunity $opportunity, CrmStage $target): void
    {
        if ($target->is_won && $opportunity->lead_id && ! $opportunity->client_id) {
            $lead = $opportunity->lead;
            if ($lead && $lead->status !== 'Converted') {
                $lead->update(['status' => 'Closed-Won']);
            }
        }
    }
}
