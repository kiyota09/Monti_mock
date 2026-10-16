# CRM Pipeline (Odoo-style Kanban)

Odoo-modeled deal pipeline inside the existing Monti stack — **no stack split**:
Laravel 11 + Inertia + Vue 3 (`<script setup>`) + MySQL, `vuedraggable` (already
installed) for drag-and-drop, Tailwind for styling. No Pinia was added — the
board owns state and `usePipeline.js` derives totals/bars, so everything
recalculates instantly from props.

## Folder structure

```
app/Models/Crm/
  CrmStage.php                  kanban columns (sequence, folded, won, default probability)
  CrmStageAutomationRule.php    placeholder for future automations (never executed yet)
  CrmOpportunity.php            + stage_id/contact/priority/notes/marketing fields, activity_state
  CrmActivity.php               + reminder/todo types, planned|done|cancelled, due_date/assigned_to
  CrmContact.php                + organization
app/Http/Controllers/Crm/
  PipelineController.php        boardProps() shared by board + detail page; index()/show()
  OpportunityController.php     pipeline index/show, quick-create (existing|inline contact),
                                full update, drag/chevron move (stage_id or legacy key), priority
  StageController.php           store / update / reorder / destroy (block-or-move)
  ActivityController.php        schedule (mark_done flag) / done / cancel / destroy
routes/Crm.php                  + crm.stages.*, opportunities.update/priority, activities.cancel
database/migrations/2026_10_03_000001_crm_pipeline_odoo.php
database/seeders/CrmPipelineSeeder.php   5 stages, 8 contacts, 12 deals, 6 activities
resources/js/composables/crm/usePipeline.js
resources/js/Components/crm/pipeline/
  PipelineBoard  → Pages/Dashboard/CRM/Opportunities.vue (board page)
  StageColumn.vue, OpportunityCard.vue, QuickCreateForm.vue,
  ActivityModal.vue, CalendarPicker.vue, StageEditModal.vue, ActivityBar.vue
  OpportunityForm → Pages/Dashboard/CRM/OpportunityShow.vue (detail page)
```

## Database schema (additive, BC-safe)

- `crm_stages(id, name, sequence, is_folded, is_won, default_probability, created_by)`
- `crm_stage_automation_rules(id, stage_id→cascade, trigger, action, payload json, is_active)` — future use
- `crm_opportunities` += `stage_id→nullOnDelete`, `contact_id→nullOnDelete`,
  `priority tinyInt 0-3`, `internal_notes`, `source/medium/campaign/referred_by`,
  `email`, `phone`. Legacy string `stage` is kept and synced (won/lost mapping).
- `crm_contacts` += `organization`
- `crm_activities` += `summary`, `due_date`, `assigned_to→users`, `notes`,
  `status planned|done|cancelled` (backfilled from `done_at`; legacy columns synced).

## API endpoints (Inertia posts, same `opportunities`/`activities` permissions)

| Method | Route | Payload |
|---|---|---|
| GET | `crm.opportunities` | board props: stages, opportunities (+contact, owner, next_activity, activity_state), contacts, salespeople, currentUserId |
| POST | `crm.opportunities.store` | `{title, value, stage_id?, contact_id? \| contact_name+organization+email+phone, priority?, expected_close?}` |
| PATCH | `crm.opportunities.update` | `{title?, contact_id?, email?, phone?, value?, probability?, priority?, owner_id?, expected_close?, internal_notes?, source?, medium?, campaign?, referred_by?, stage_id?}` |
| POST | `crm.opportunities.move` | `{stage_id}` preferred; `{stage}` legacy key/name still accepted |
| POST | `crm.opportunities.priority` | `{priority: 0-3}` |
| POST / PATCH / DELETE | `crm.stages.*` | `{name?, default_probability?, is_folded?, is_won?}`, reorder `{ordered_ids[]}`, destroy `{mode: block\|move, target_stage_id?}` |
| POST | `crm.activities.store` | `{opportunity_id, type: call\|meeting\|reminder\|todo(+legacy), summary, due_date?, assigned_to?, notes?, mark_done?}` |
| POST | `crm.activities.done` / `crm.activities.cancel` | — |

## Setup & run

```bash
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\CrmPipelineSeeder --force
npm run build:skip-check   # or npm run dev
```

Open **CRM → Opportunities**. Demo: keep **My Pipeline** (default, X clears it) →
**+ Stage** “Second Proposition”, drag it before **Won** → gear menu Fold/Unfold →
**+** on **New** → pick Abby (email/phone auto-fill) → “Abby wants 200 lamps”,
₱2,000, ★★ → phone icon on card → schedule call for tomorrow via **Open Calendar**
(green bar segment) → drag card to **Qualified** (totals move) → open card →
rename to “Abby wants 400 lamps”, ₱4,000 → chevron to **Second Proposition**.
Overdue = red, today = amber, planned = green, none = gray clock.

## Leads: slim intake inbox (not a second pipeline)

`Pages/Dashboard/CRM/Lead.vue` is intentionally **not** a kanban. Jobs: capture
(New Lead) → BANT qualify → **Send to Pipeline** (`POST crm.lead.pipeline`,
creates the deal in the first stage linked via `lead_id`) or Convert to
Client / Mark Lost. Leads already carrying an opportunity render as
"In pipeline →" links to the deal. Negotiation, approvals and closing live
only in Opportunities. All legacy lead routes (notes/interviews/files/status)
are kept for BC but no longer drive the UI.

## Assumptions

- Adapted to this repo's stack per “change if needed” (no separate Express app).
- `owner_id` = salesperson; My Pipeline = `owner_id == auth user`.
- Deleting a stage with deals is blocked unless a move target is chosen.
- Probability defaults from the stage but stays manually editable.
- Reports/forecasting and real automation rules are out of scope by design.
