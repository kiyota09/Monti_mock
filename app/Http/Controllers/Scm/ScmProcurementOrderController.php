<?php

namespace App\Http\Controllers\Scm;

use App\Http\Controllers\Controller;
use App\Models\Scm\MaterialRequest;
use Inertia\Inertia;

class ScmProcurementOrderController extends Controller
{
    public function index()
    {
        $procurementRequests = MaterialRequest::where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($req) => [
                'id' => $req->id,
                'req_number' => $req->req_number,
                'material_name' => $req->material_name,
                'required_qty' => $req->required_qty,
                'unit' => $req->unit,
                'urgency' => $req->urgency,
                'status' => $req->status,
                'created_at' => $req->created_at,
            ]);

        return Inertia::render('Dashboard/SCM/ProcurementOrder', [
            'procurementRequests' => $procurementRequests,
        ]);
    }

    public function sendToProcurementModule(MaterialRequest $materialRequest)
    {
        if ($materialRequest->status !== 'pending') {
            return redirect()->back()->withErrors(['error' => 'Request already processed.']);
        }

        $materialRequest->update([
            'status' => 'forwarded',
            'forwarded_at' => now(),
            'forwarded_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Procurement request sent to PRO module.');
    }
}