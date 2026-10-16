<?php

namespace Database\Seeders;

use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmContact;
use App\Models\Crm\CrmOpportunity;
use App\Models\Crm\CrmOpportunityHistory;
use App\Models\Crm\CrmStage;
use App\Models\Core\User;
use Illuminate\Database\Seeder;

/**
 * Odoo-style pipeline demo data.
 * - 5 stages: New, Qualified, First Proposition, Won (+ Lost, folded)
 * - 8 contacts (incl. Abby for the demo flow)
 * - 12 opportunities across stages/owners
 * - Activities in every state: planned future, due today, overdue, done, none
 *
 * Safe to re-run: lookups are by unique name/title.
 */
class CrmPipelineSeeder extends Seeder
{
    public function run(): void
    {
        $sales = User::where('is_active', true)->orderBy('id')->take(3)->get();
        if ($sales->isEmpty()) {
            $this->command?->warn('CrmPipelineSeeder: no active users found, skipping.');
            return;
        }
        $me = $sales->first();
        $other = $sales->skip(1)->first() ?? $me;

        // ── 5 stages ──────────────────────────────────────────────
        $stageDefs = [
            ['name' => 'New', 'sequence' => 0, 'default_probability' => 10, 'is_folded' => false, 'is_won' => false],
            ['name' => 'Qualified', 'sequence' => 1, 'default_probability' => 30, 'is_folded' => false, 'is_won' => false],
            ['name' => 'First Proposition', 'sequence' => 2, 'default_probability' => 60, 'is_folded' => false, 'is_won' => false],
            ['name' => 'Won', 'sequence' => 3, 'default_probability' => 100, 'is_folded' => false, 'is_won' => true],
            ['name' => 'Lost', 'sequence' => 4, 'default_probability' => 0, 'is_folded' => true, 'is_won' => false],
        ];
        $stages = [];
        foreach ($stageDefs as $def) {
            $stages[$def['name']] = CrmStage::firstOrCreate(
                ['name' => $def['name']],
                [...$def, 'created_by' => $me->id],
            );
        }

        // ── 8 contacts ────────────────────────────────────────────
        $contactDefs = [
            ['name' => 'Abby Reyes', 'organization' => "Abby's Home Living", 'email' => 'abby@homeliving.example', 'phone' => '+63 917 111 2233'],
            ['name' => 'Marco Santos', 'organization' => 'Santos Hotel Group', 'email' => 'marco@santoshotels.example', 'phone' => '+63 918 222 3344'],
            ['name' => 'Lena Cruz', 'organization' => 'Cruz Apparel Co.', 'email' => 'lena@cruzapparel.example', 'phone' => '+63 919 333 4455'],
            ['name' => 'David Lim', 'organization' => 'Lim Uniform Supply', 'email' => 'david@limuniform.example', 'phone' => '+63 920 444 5566'],
            ['name' => 'Priya Nair', 'organization' => 'Nair Boutique Hotels', 'email' => 'priya@nairhotels.example', 'phone' => '+63 921 555 6677'],
            ['name' => 'Jose Ramos', 'organization' => 'Ramos Workwear Inc.', 'email' => 'jose@ramosworkwear.example', 'phone' => '+63 922 666 7788'],
            ['name' => 'Ana Villanueva', 'organization' => 'Villanueva Linen Trading', 'email' => 'ana@vllinen.example', 'phone' => '+63 923 777 8899'],
            ['name' => 'Tom Becker', 'organization' => 'Becker Outdoor GmbH', 'email' => 'tom@beckeroutdoor.example', 'phone' => '+49 170 888 9900'],
        ];
        $contacts = [];
        foreach ($contactDefs as $def) {
            $contacts[$def['name']] = CrmContact::firstOrCreate(
                ['email' => $def['email']],
                [...$def, 'title' => 'Purchasing Manager', 'is_decision_maker' => true],
            );
        }

        // ── 12 opportunities ──────────────────────────────────────
        // [title, contact, stage, value, priority, owner, expected_close_offset_days]
        $oppDefs = [
            ['Hoodie fleece — 500 pcs', 'Marco Santos', 'New', 85000, 2, $me, 30],
            ['Hotel bedsheet restock Q4', 'Priya Nair', 'New', 120000, 1, $me, 45],
            ['Workwear twill trial order', 'Jose Ramos', 'New', 45000, 0, $other, 21],
            ['Linen tablecloth — 2,000 m', 'Ana Villanueva', 'Qualified', 210000, 3, $me, 28],
            ['Uniform shirting — 1,200 yds', 'David Lim', 'Qualified', 96000, 2, $me, 35],
            ['Boutique robe collection', 'Priya Nair', 'Qualified', 150000, 2, $other, 40],
            ['Denim apron program', 'Lena Cruz', 'First Proposition', 175000, 3, $me, 25],
            ['Outdoor canvas — 800 m', 'Tom Becker', 'First Proposition', 320000, 1, $me, 50],
            ['Hotel towel contract renewal', 'Marco Santos', 'First Proposition', 410000, 3, $other, 20],
            ['Resort quilted throw pillows', 'Abby Reyes', 'Won', 88000, 2, $me, -5],
            ['School uniform gabardine', 'David Lim', 'Won', 64000, 1, $other, -12],
            ['Old inquiry — polyester (dropped)', 'Tom Becker', 'Lost', 30000, 0, $me, -30],
        ];

        $opps = [];
        foreach ($oppDefs as [$title, $contactName, $stageName, $value, $priority, $owner, $closeOffset]) {
            $stage = $stages[$stageName];
            $contact = $contacts[$contactName];
            $opp = CrmOpportunity::firstOrCreate(
                ['title' => $title],
                [
                    'stage_id' => $stage->id,
                    'stage' => $stage->is_won ? 'won' : ($stageName === 'Lost' ? 'lost' : 'qualification'),
                    'contact_id' => $contact->id,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'value' => $value,
                    'priority' => $priority,
                    'probability' => $stage->default_probability,
                    'expected_close' => now()->addDays($closeOffset)->toDateString(),
                    'owner_id' => $owner->id,
                    'source' => 'seed',
                    'medium' => 'manual',
                    'campaign' => 'pipeline-demo',
                    'lost_reason' => $stageName === 'Lost' ? 'Chose a cheaper supplier.' : null,
                ],
            );
            // Keep reruns fresh: re-pin stage/value linkage.
            $opp->update([
                'stage_id' => $stage->id,
                'probability' => $stage->default_probability,
                'owner_id' => $owner->id,
            ]);
            CrmOpportunityHistory::firstOrCreate(
                ['opportunity_id' => $opp->id, 'to_stage' => $stageName],
                ['from_stage' => null, 'changed_by' => $me->id, 'notes' => 'Seeded deal.'],
            );
            $opps[$title] = $opp;
        }

        // ── Activities in every state ─────────────────────────────
        $act = function (string $oppTitle, string $type, string $summary, ?string $due, string $status, $owner, string $notes = '') {
            $opp = $opp = $this->oppByTitle($oppTitle);
            if (! $opp) {
                return;
            }
            $dueAt = $due ? now()->parse($due) : null;
            CrmActivity::firstOrCreate(
                ['opportunity_id' => $opp->id, 'summary' => $summary],
                [
                    'type' => $type,
                    'subject' => $summary,
                    'body' => $notes,
                    'notes' => $notes,
                    'due_at' => $dueAt,
                    'due_date' => $dueAt,
                    'done_at' => $status === 'done' ? now() : null,
                    'status' => $status,
                    'owner_id' => $owner->id,
                    'assigned_to' => $owner->id,
                ],
            );
        };

        // Planned (future) → green
        $act('Linen tablecloth — 2,000 m', 'call', 'Follow-up call on swatches', now()->addDay()->toDateTimeString(), 'planned', $me, 'Confirm color fastness results.');
        $act('Denim apron program', 'meeting', 'Tech-pack review meeting', now()->addDays(3)->toDateTimeString(), 'planned', $me);
        // Due today → yellow
        $act('Uniform shirting — 1,200 yds', 'reminder', 'Send updated quotation', now()->toDateTimeString(), 'planned', $me);
        // Overdue → red
        $act('Hotel towel contract renewal', 'call', 'Chase PO signature', now()->subDays(2)->toDateTimeString(), 'planned', $other, 'Decision maker was on leave.');
        $act('Boutique robe collection', 'todo', 'Prepare lab-dip revisions', now()->subDay()->toDateTimeString(), 'planned', $other);
        // Done → chatter history
        $act('Resort quilted throw pillows', 'meeting', 'Final measurement session', now()->subDays(10)->toDateTimeString(), 'done', $me, 'Closed and won.');
        // "Hoodie fleece" + "Abby" deals intentionally left with NO activity → gray clock.
    }

    protected function oppByTitle(string $title): ?CrmOpportunity
    {
        return CrmOpportunity::where('title', $title)->first();
    }
}
