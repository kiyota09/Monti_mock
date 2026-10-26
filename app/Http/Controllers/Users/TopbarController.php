<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Core\Notification;
use App\Models\Core\User;
use App\Models\Sec\SecretaryMemo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class TopbarController extends Controller
{
    /**
     * One-shot poll payload for the universal top bar:
     * unread general notifications, unread chat messages, latest memos.
     */
    public function summary(Request $request)
    {
        $user = $request->user();

        // Exact unread count across the viewer's threads (usually a handful).
        $msgUnread = 0;
        $parts = \DB::table('thread_participants')->where('user_id', $user->id)->get(['thread_id', 'last_read_at']);
        foreach ($parts as $p) {
            $q = \App\Models\Core\ChatMessage::where('thread_id', $p->thread_id)->where('sender_id', '!=', $user->id);
            if ($p->last_read_at) {
                $q->where('created_at', '>', $p->last_read_at);
            }
            $msgUnread += $q->count();
        }

        return response()->json([
            // Bell = general notifications only. Chat ('message') and memo
            // items have their own dedicated icons, so they are excluded
            // here to avoid double-counting.
            'notifications_unread' => Notification::where('user_id', $user->id)->where('is_read', false)
                ->whereNotIn('type', ['message', 'messages', 'messenger', 'chat', 'memo', 'memos'])
                ->count(),
            'messages_unread' => $msgUnread,
            'memos_count' => $this->visibleMemos($user)->count(),
        ]);
    }

    public function notifications(Request $request)
    {
        // Bell feed excludes chat + memo notifications — those surfaces
        // have their own icons/panels (messenger + memos).
        $items = Notification::with('creator:id,name')
            ->where('user_id', $request->user()->id)
            ->whereNotIn('type', ['message', 'messages', 'messenger', 'chat', 'memo', 'memos'])
            ->latest()
            ->take(15)
            ->get();

        return response()->json([
            'unread' => Notification::where('user_id', $request->user()->id)->where('is_read', false)
                ->whereNotIn('type', ['message', 'messages', 'messenger', 'chat', 'memo', 'memos'])
                ->count(),
            'items' => $items,
        ]);
    }

    public function readNotification(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['is_read' => true]);

        return response()->json(['ok' => true]);
    }

    public function readAllNotifications(Request $request)
    {
        Notification::where('user_id', $request->user()->id)->where('is_read', false)->update(['is_read' => true]);

        return response()->json(['ok' => true]);
    }

    /**
     * Published secretary memos visible to this account:
     * audience 'all' or matching the viewer's role (case-insensitive).
     */
    public function memos(Request $request)
    {
        $items = $this->visibleMemos($request->user())->take(20)->get();

        $names = User::whereIn('id', $items
            ->flatMap(fn ($m) => \App\Models\Sec\SecretaryMemo::audienceUserIds($m->audience))
            ->unique()->values()
        )->pluck('name', 'id')->all();

        $items->each(fn ($m) => $m->setAttribute('audience_label', $m->audienceLabel($names)));

        return response()->json(['items' => $items]);
    }

    protected function visibleMemos($user)
    {
        return SecretaryMemo::with('creator:id,name')->visibleTo($user)->latest('published_at');
    }

    /**
     * Internal user directory for starting conversations.
     */
    public function directory(Request $request)
    {
        $users = User::where('is_active', true)
            ->where('id', '!=', $request->user()->id)
            ->orderBy('name')
            ->take(150)
            ->get(['id', 'name', 'email', 'role', 'position', 'profile_photo_path'])
            ->map(fn ($u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email,
                'role' => $u->role, 'position' => $u->position,
                'photo' => $u->profile_photo_path ? '/storage/'.ltrim($u->profile_photo_path, '/') : null,
            ]);

        return response()->json(['users' => $users]);
    }

    /**
     * Global command search (Ctrl+K): jump to pages + look up records
     * (job/PO numbers, clients, materials, products, stock lots, people).
     * Everything returned is pre-filtered by what the viewer may access.
     */
    public function search(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['pages' => [], 'records' => []]);
        }

        $user = $request->user();
        $needle = mb_strtolower($q);

        return response()->json([
            'pages' => $this->searchPages($user, $needle),
            'records' => $this->searchRecords($user, $q),
        ]);
    }

    // ─── Page registry ──────────────────────────────────────────────
    protected function pageRegistry(): array
    {
        // [label, route, module, page] — module/page=null means role-gated.
        return [
            ['HRM Dashboard', 'hrm.dashboard', 'HRM', 'dashboard'],
            ['Employees', 'hrm.employees.index', 'HRM', 'employee'],
            ['Agencies', 'hrm.agencies.index', 'HRM', 'agencies'],
            ['Agency Workers', 'hrm.workers.index', 'HRM', 'workers'],
            ['Manpower Requests', 'hrm.manpower-requests.index', 'HRM', 'requests'],
            ['Agency Attendance', 'hrm.attendance.index', 'HRM', 'agency_attendance'],
            ['Agency Billing', 'hrm.billing.index', 'HRM', 'agency_billing'],
            ['Applications', 'hrm.applications.index', 'HRM', 'application'],
            ['Interviews', 'hrm.interview.index', 'HRM', 'interview'],
            ['Trainees', 'hrm.trainee.index', 'HRM', 'trainee'],
            ['Onboarding', 'hrm.onboarding.status.index', 'HRM', 'onboarding'],
            ['Payroll', 'hrm.payroll', 'HRM', 'payroll'],
            ['Compensation & Salary Management', 'hrm.compensation', 'HRM', 'compensation'],
            ['HR Analytics', 'hrm.analytics', 'HRM', 'analytics'],
            ['CRM Dashboard', 'crm.dashboard', 'CRM', 'dashboard'],
            ['Leads', 'crm.lead', 'CRM', 'leads'],
            ['Approvals', 'crm.approval.index', 'CRM', 'approvals'],
            ['Customer Profiles', 'crm.customerprofile.index', 'CRM', 'customer_profiles'],
            ['Inquiries', 'crm.inquiries', 'CRM', 'inquiry'],
            ['Messaging', 'crm.messages', 'CRM', 'messaging'],
            ['Email', 'crm.email.index', 'CRM', 'email'],
            ['Investigations', 'crm.investigation.index', 'CRM', 'investigation'],
            ['SCM Dashboard', 'scm.dashboard', 'SCM', 'dashboard'],
            ['Sales Orders', 'scm.sales-orders', 'SCM', 'sales'],
            ['Procurement Orders', 'scm.procurement-orders', 'SCM', 'procurement'],
            ['Demand Planning', 'scm.planning', 'SCM', 'planning'],
            ['Purchase Orders (SCM)', 'scm.purchase-orders', 'SCM', 'purchase'],
            ['Inbound Deliveries', 'scm.deliveries', 'SCM', 'deliveries'],
            ['Vendors', 'scm.vendors', 'SCM', 'vendor'],
            ['SCM Analytics', 'scm.analytics', 'SCM', 'analytics'],
            ['Production Orders', 'man.manager.production', 'MAN', 'production'],
            ['Recipes', 'man.recipes', 'MAN', 'production'],
            ['Rejected Items', 'man.manager.rejected', 'MAN', 'reject'],
            ['Plant Inventory', 'man.inventory.index', 'MAN', 'inventory'],
            ['Logistics Dashboard', 'logistics.dashboard', 'LOG', 'dashboard'],
            ['Load', 'logistics.load.index', 'LOG', 'load'],
            ['Dispatch', 'logistics.dispatch.index', 'LOG', 'dispatch'],
            ['Fleet', 'logistics.fleet.index', 'LOG', 'fleet'],
            ['Drivers', 'logistics.drivers.index', 'LOG', 'drivers'],
            ['Routes', 'logistics.routes', 'LOG', 'routes'],
            ['Tracking', 'logistics.tracking', 'LOG', 'tracking'],
            ['Proof of Delivery', 'logistics.proof.index', 'LOG', 'proof'],
            ['Logistics Reports', 'logistics.reports.index', 'LOG', 'reports'],
            ['ECO Dashboard', 'eco.dashboard', 'ECO', 'dashboard'],
            ['Store', 'eco.store', 'ECO', 'store'],
            ['ECO Suppliers', 'eco.suppliers', 'ECO', 'supplier'],
            ['ECO Clients', 'eco.clients', 'ECO', 'client'],
            ['Credit', 'eco.credit', 'ECO', 'credit'],
            ['Push Center', 'eco.push', 'ECO', 'push'],
            ['ORD Dashboard', 'ord.dashboard', 'ORD', 'dashboard'],
            ['Orders', 'ord.orders', 'ORD', 'orders'],
            ['Productions', 'ord.productions', 'ORD', 'productions'],
            ['Delivery Tracking', 'ord.delivery', 'ORD', 'delivery'],
            ['Returns', 'ord.returns', 'ORD', 'returns'],
            ['Warehouses', 'warehouse.index', 'WAR', 'warehouse'],
            ['Receiving', 'warehouse.receiving', 'WAR', 'receiving'],
            ['Packages', 'warehouse.packages', 'WAR', 'packages'],
            ['Rejects', 'warehouse.rejects', 'WAR', 'reject'],
            ['Inventory Dashboard', 'inv.dashboard', 'INV', 'dashboard'],
            ['Materials', 'inv.materials', 'INV', 'materials'],
            ['Products', 'inv.products', 'INV', 'products'],
            ['Bill of Materials', 'inv.bom', 'INV', 'bom'],
            ['Stock Checker', 'inv.checker', 'INV', 'checker'],
            ['Procurement Dashboard', 'pro.manager.dashboard', 'PRO', 'dashboard'],
            ['Material Requests', 'pro.manager.material-requests', 'PRO', 'requests'],
            ['Supplier Quotations', 'pro.manager.supplier-quotations', 'PRO', 'quotations'],
            ['Receipts', 'pro.manager.receipt', 'PRO', 'receipt'],
            ['Order Tracking', 'pro.manager.tracking', 'PRO', 'tracking'],
            ['Finance Overview', 'fin.manager.dashboard', 'FIN', 'dashboard'],
            ['Receivables', 'fin.manager.receivables', 'FIN', 'receivables'],
            ['Payables', 'fin.manager.payables', 'FIN', 'payables'],
            ['Expenses', 'fin.manager.expenses', 'FIN', 'expenses'],
            ['Finance Payroll', 'fin.manager.payroll', 'FIN', 'payroll'],
            ['Finance Reports', 'fin.manager.reports', 'FIN', 'reports'],
            ['PO Approvals', 'fin.manager.approvals', 'FIN', 'approvals'],
            ['IT Dashboard', 'it.dashboard', 'IT', 'dashboard'],
            ['Service Desk', 'it.tickets', 'IT', 'tickets'],
            ['IT Assets', 'it.assets', 'IT', 'assets'],
            ['Monitoring', 'it.monitoring', 'IT', 'monitoring'],
            ['Knowledge Base', 'it.knowledge', 'IT', 'knowledge'],
            ['IT Changes', 'it.changes', 'IT', 'changes'],
            ['Org Access Control', 'it.access-control', 'IT', 'access_control'],
            ['Access Logs', 'it.access-logs', 'IT', 'access_logs'],
            ['Workforce Dashboard', 'workforce.dashboard', 'WRF', 'dashboard'],
            ['Scheduler', 'workforce.scheduler', 'WRF', 'scheduler'],
            ['Leave Requests', 'workforce.leave', 'WRF', 'leave'],
            ['Absences', 'workforce.absent', 'WRF', 'absent'],
            ['My Profile', 'profile.edit', null, null],
            // Executive (role-gated, not page-permission gated)
            ['CEO Dashboard', 'ceo.dashboard', 'CEO', null],
            ['Executive Inbox', 'ceo.inbox', 'CEO', null],
            ['Approvals Center', 'ceo.approvals', 'CEO', null],
            ['Executive Reports', 'ceo.reports', 'CEO', null],
            ['Board Pack', 'ceo.board-pack', 'CEO', null],
            ['Deliveries', 'ceo.deliveries', 'CEO', null],
            ['Traceability', 'ceo.traceability', 'CEO', null],
            ['VP Operations', 'vp.operations', 'COO', null],
            ['Directives', 'vp.directives', 'COO', null],
            ['Secretary Dashboard', 'secretary.dashboard', 'SEC', null],
        ];
    }

    protected function searchPages($user, string $needle): array
    {
        $out = [];
        foreach ($this->pageRegistry() as [$label, $routeName, $module, $page]) {
            if (! Route::has($routeName)) {
                continue;
            }
            if (mb_stripos($label, $needle) === false && mb_stripos($module ?? '', $needle) === false) {
                continue;
            }
            if (! $this->canSeePage($user, $module, $page)) {
                continue;
            }
            $out[] = ['label' => $label, 'sub' => $module ?? 'Account', 'route' => $routeName, 'params' => []];
            if (count($out) >= 10) {
                break;
            }
        }

        return $out;
    }

    protected function canSeePage($user, ?string $module, ?string $page): bool
    {
        if ($module === null) {
            return true; // account-level pages (profile)
        }
        if ($module === 'CEO') {
            return in_array($user->role, ['CEO', 'COO'], true);
        }
        if ($module === 'COO') {
            return $user->role === 'COO' || $user->role === 'CEO';
        }
        if ($module === 'SEC') {
            return in_array($user->position, ['secretary', 'special_officer'], true) || $user->role === 'CEO';
        }
        if ($module === 'WRF') {
            return \App\Models\Work\WorkforcePermission::where('user_id', $user->id)->exists()
                || in_array($user->position, ['secretary', 'special_officer'], true);
        }
        // Vice President: no HRM pages — the COO role is standalone.
        if (strtoupper((string) $module) === 'HRM'
            && (in_array($user->role ?? '', ['COO'], true)
                || ($user->position ?? '') === 'vice_president')) {
            return false;
        }

        $record = \App\Models\Core\PagePermission::where('user_id', $user->id)
            ->whereIn('module', [$module, strtoupper($module), strtolower($module)])
            ->get(['page', 'permission_level'])
            ->first(fn ($row) => strtolower((string) $row->page) === strtolower($page));
        if ($record !== null) {
            // An explicit 'disabled' row always wins — even for executives.
            if (strtolower($record->permission_level ?? 'edit') === 'disabled') {
                return false;
            }
            // President & Vice President see every CRM page.
            if (strtoupper((string) $module) === 'CRM'
                && (in_array($user->role ?? '', ['CEO', 'COO'], true)
                    || ($user->position ?? '') === 'vice_president')) {
                return true;
            }
            return strtolower($record->permission_level ?? 'edit') !== 'disabled';
        }
        // President & Vice President see every CRM page (no explicit rows).
        if (strtoupper((string) $module) === 'CRM'
            && (in_array($user->role ?? '', ['CEO', 'COO'], true)
                || ($user->position ?? '') === 'vice_president')) {
            return true;
        }
        // Native manager/staff auto-access (no explicit rows for the module).
        if (strtoupper($user->role) === $module && in_array($user->position, ['manager', 'staff'])) {
            return true;
        }
        if (in_array($user->position, ['secretary', 'special_officer']) && $this->rootModule($user) === $module) {
            return true;
        }

        return false;
    }

    protected function rootModule($user): ?string
    {
        if (! empty($user->is_manufacturing_supervisor)) {
            return 'MAN';
        }
        foreach (['HRM', 'CRM', 'MAN', 'LOG'] as $core) {
            if (str_contains(strtoupper($user->role ?? ''), $core)) {
                return $core;
            }
        }

        return null;
    }

    protected function canSeeModule($user, string $module): bool
    {
        if (in_array($module, ['CEO', 'COO', 'SEC'], true)) {
            return $this->canSeePage($user, $module, 'dashboard');
        }
        if ($module === 'WRF') {
            return $this->canSeePage($user, 'WRF', 'dashboard');
        }
        // Vice President: no HRM module — the COO role is standalone.
        if (strtoupper($module) === 'HRM'
            && (in_array($user->role ?? '', ['COO'], true)
                || ($user->position ?? '') === 'vice_president')) {
            return false;
        }
        $hasUsable = \App\Models\Core\PagePermission::where('user_id', $user->id)
            ->whereIn('module', [$module, strtoupper($module), strtolower($module)])
            ->where(function ($q) {
                $q->whereIn('permission_level', ['view', 'edit'])->orWhereNull('permission_level');
            })->exists();
        if ($hasUsable) {
            return true;
        }
        // President & Vice President can always see the CRM module.
        if (strtoupper($module) === 'CRM'
            && (in_array($user->role ?? '', ['CEO', 'COO'], true)
                || ($user->position ?? '') === 'vice_president')) {
            return true;
        }
        if (strtoupper($user->role) === $module && in_array($user->position, ['manager', 'staff'])) {
            return true;
        }

        return false;
    }

    // ─── Record search ──────────────────────────────────────────────
    protected function searchRecords($user, string $q): array
    {
        $out = [];
        $like = "%{$q}%";

        if ($this->canSeeModule($user, 'ORD')) {
            $sos = \App\Models\Ord\SalesOrder::with('client')
                ->where('jo_number', 'like', $like)->take(4)->get();
            foreach ($sos as $so) {
                $out[] = [
                    'label' => $so->jo_number ?? ('JO-'.$so->id),
                    'sub' => 'Job order · '.($so->client?->company_name ?? '—'),
                    'route' => 'ord.orders.show', 'params' => ['type' => 'so', 'id' => $so->id],
                ];
            }
            $pos = \App\Models\Ord\PurchaseOrder::with('client')
                ->where('po_number', 'like', $like)->take(4)->get();
            foreach ($pos as $po) {
                $out[] = [
                    'label' => $po->po_number,
                    'sub' => 'Client PO · '.($po->client?->company_name ?? '—'),
                    'route' => 'ord.orders.show', 'params' => ['type' => 'po', 'id' => $po->id],
                ];
            }
        }

        if ($this->canSeeModule($user, 'CRM')) {
            $clients = \App\Models\Crm\Client::where('company_name', 'like', $like)->take(4)->get();
            foreach ($clients as $c) {
                $out[] = [
                    'label' => $c->company_name,
                    'sub' => 'Client · '.($c->email ?? ''),
                    'route' => 'crm.customerprofile.index', 'params' => [],
                ];
            }
        }

        if ($this->canSeeModule($user, 'INV')) {
            $mats = \App\Models\Inv\Material::where('name', 'like', $like)->orWhere('mat_id', 'like', $like)->take(4)->get();
            foreach ($mats as $m) {
                $out[] = ['label' => $m->name, 'sub' => 'Material · '.$m->mat_id, 'route' => 'inv.materials', 'params' => []];
            }
            $prods = \App\Models\Inv\Product::where('name', 'like', $like)->orWhere('sku', 'like', $like)->take(4)->get();
            foreach ($prods as $p) {
                $out[] = ['label' => $p->name, 'sub' => 'Product · '.($p->sku ?? ''), 'route' => 'inv.products', 'params' => []];
            }
        }

        if ($this->canSeeModule($user, 'WAR')) {
            $lots = \App\Models\War\WarehouseStockItem::with('material')
                ->where('control_number', 'like', $like)->take(4)->get();
            foreach ($lots as $s) {
                $out[] = [
                    'label' => $s->control_number,
                    'sub' => 'Stock lot · '.($s->material?->name ?? '').' '.$s->quantity.$s->unit,
                    'route' => 'warehouse.monitor', 'params' => ['warehouse' => $s->warehouse_id],
                ];
            }
        }

        if ($this->canSeeModule($user, 'HRM') || $user->role === 'CEO') {
            $people = User::where('name', 'like', $like)->orWhere('email', 'like', $like)
                ->where('is_active', true)->take(4)->get(['id', 'name', 'email', 'role']);
            foreach ($people as $p) {
                $out[] = [
                    'label' => $p->name,
                    'sub' => 'Employee · '.$p->role.' · '.$p->email,
                    'route' => 'hrm.employees.show', 'params' => ['id' => $p->id],
                ];
            }
        }

        return array_slice($out, 0, 20);
    }
}
