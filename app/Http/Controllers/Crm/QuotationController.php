<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmClientAssignment;
use App\Models\Eco\EcoQuotation;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Traits\HasPagePermissions;

class QuotationController extends Controller
{
    use HasPagePermissions;

    /**
     * ECO quotations surfaced inside CRM (read-only; issuing stays in ECO).
     * Staff see only their assigned clients' quotes.
     */
    public function index()
    {
        $user = Auth::user();

        $query = EcoQuotation::with(['items', 'client:id,company_name', 'inquiry:id,client_id'])
            ->latest();

        if ($user->role === 'CRM' && $user->position === 'staff') {
            $ids = CrmClientAssignment::where('staff_id', $user->id)->pluck('client_id');
            $query->whereIn('client_id', $ids);
        }

        $quotations = $query->take(150)->get();

        return Inertia::render('Dashboard/CRM/Quotations', [
            'quotations' => $quotations,
            'summary' => [
                'sent' => $quotations->where('status', 'sent')->count(),
                'accepted' => $quotations->where('status', 'accepted')->count(),
                'value' => round($quotations->where('status', 'accepted')->sum('grand_total'), 2),
            ],
            'permissions' => $this->getPagePermissionsForModule('CRM'),
        ]);
    }
}
