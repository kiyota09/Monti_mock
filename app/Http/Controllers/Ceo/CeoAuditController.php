<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Controller;
use App\Models\Ceo\ExecutiveActionLog;
use App\Models\Hrm\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CeoAuditController extends Controller
{
    /**
     * Global audit trail: who did what, to whom, and why.
     * Read-only — the log itself is never modified here.
     */
    public function index(Request $request)
    {
        $query = AuditLog::with(['admin:id,name,email', 'target:id,name,email'])->latest();

        if ($search = $request->search) {
            $query->where(fn ($q) => $q
                ->where('action', 'like', "%{$search}%")
                ->orWhere('reason', 'like', "%{$search}%")
                ->orWhere('target_name', 'like', "%{$search}%")
                ->orWhereHas('admin', fn ($a) => $a->where('name', 'like', "%{$search}%"))
                ->orWhereHas('target', fn ($t) => $t->where('name', 'like', "%{$search}%")));
        }

        return Inertia::render('Dashboard/CEO/AuditTrail', [
            'logs' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only('search'),
            'executiveDecisions' => ExecutiveActionLog::with('actor:id,name')
                ->latest()->take(50)->get(),
        ]);
    }
}
