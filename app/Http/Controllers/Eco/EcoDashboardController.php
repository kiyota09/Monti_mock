<?php

namespace App\Http\Controllers\Eco;

use App\Http\Controllers\Controller;
use App\Models\Eco\Inquiry;
use App\Models\Ord\PurchaseOrder;
use Carbon\Carbon;
use Inertia\Inertia;

class EcoDashboardController extends Controller
{
    public function index()
    {
        // Graphs data
        $inquiriesLast30 = Inquiry::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        $quotationsSent = \App\Models\Client\ClientQuotation::whereMonth('created_at', Carbon::now()->month)->count();
        $salesOrders = PurchaseOrder::whereMonth('created_at', Carbon::now()->month)->count();

        return Inertia::render('Dashboard/ECO/Dashboard', [
            'stats' => [
                'inquiries' => $inquiriesLast30,
                'quotations' => $quotationsSent,
                'salesOrders' => $salesOrders,
            ],
        ]);
    }
}