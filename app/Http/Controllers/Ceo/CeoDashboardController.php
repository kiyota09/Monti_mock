<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmLead;
use App\Models\Ord\PurchaseOrder;
use App\Models\Core\User;
use App\Models\Hrm\LeaveRequest;
use App\Models\Pro\Supplier;
use App\Models\Scm\MaterialRequest;
use App\Models\Man\ManufacturingOrder;
use App\Models\Man\Machine;
use App\Models\Man\Fabric;
use App\Models\Inv\Product;
use App\Models\Eco\CreditAccount;
use App\Models\Scm\ScmPurchaseOrder;
use App\Models\Crm\Client;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;

class CeoDashboardController extends Controller
{
    public function index()
    {
        // Basic Stats
        $totalOrders = PurchaseOrder::count();
        $totalRevenue = PurchaseOrder::where('status', 'approved')->sum('total_amount');
        $activeEmployees = User::where('is_active', true)->count();
        $pendingLeads = CrmLead::where('status', 'Inquiry')->count();

        // Revenue Trend (last 7 days)
        $revenueTrend = $this->getRevenueTrend();

        // Activity Stats
        $activityStats = $this->getActivityStats();

        // Recent Transactions
        $transactions = $this->getRecentTransactions();

        // Department Modules Stats
        $modules = $this->getDepartmentModules();

        $stats = [
            'totalOrders' => $totalOrders,
            'totalRevenue' => $totalRevenue,
            'activeEmployees' => $activeEmployees,
            'pendingLeads' => $pendingLeads,
        ];

        return Inertia::render('Dashboard/CEO/Index', [
            'stats' => $stats,
            'revenueTrend' => $revenueTrend,
            'activityStats' => $activityStats,
            'transactions' => $transactions,
            'modules' => $modules,
        ]);
    }

    /**
     * Get revenue trend for the last 7 days
     */
    private function getRevenueTrend()
    {
        $labels = [];
        $values = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $date->format('D');
            
            $dailyRevenue = PurchaseOrder::where('status', 'approved')
                ->whereDate('created_at', $date)
                ->sum('total_amount');
            
            // Convert to percentage of max for chart height (max 100% = highest daily revenue in week)
            $values[] = $dailyRevenue;
        }

        // Normalize values to percentages (max value becomes 100)
        $maxValue = max($values) ?: 1;
        $normalizedValues = array_map(function($value) use ($maxValue) {
            return ($value / $maxValue) * 100;
        }, $values);

        return [
            'labels' => $labels,
            'values' => $normalizedValues,
            'rawValues' => $values,
        ];
    }

    /**
     * Get activity stats (Receipts, Contributions, Owes)
     */
    private function getActivityStats()
    {
        // Receipts: Total approved purchase orders (all time)
        $receiptsValue = PurchaseOrder::where('status', 'approved')->sum('total_amount');
        
        // Contributions: Total approved purchase orders for current month
        $contributionsValue = PurchaseOrder::where('status', 'approved')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('total_amount');
        
        // Owes: Total outstanding balance from credit accounts
        $owesValue = CreditAccount::sum('outstanding_balance');
        
        // Calculate trends (compare with previous month for contributions and receipts)
        $lastMonthReceipts = PurchaseOrder::where('status', 'approved')
            ->whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereYear('created_at', Carbon::now()->subMonth()->year)
            ->sum('total_amount');
        
        $lastMonthContributions = PurchaseOrder::where('status', 'approved')
            ->whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereYear('created_at', Carbon::now()->subMonth()->year)
            ->sum('total_amount');
        
        $lastMonthOwes = CreditAccount::whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->sum('outstanding_balance') ?: 1;
        
        $receiptsTrend = $lastMonthReceipts > 0 
            ? (($receiptsValue - $lastMonthReceipts) / $lastMonthReceipts) * 100 
            : 11.5;
        
        $contributionsTrend = $lastMonthContributions > 0 
            ? (($contributionsValue - $lastMonthContributions) / $lastMonthContributions) * 100 
            : 4.5;
        
        $owesTrend = $lastMonthOwes > 0 
            ? (($owesValue - $lastMonthOwes) / $lastMonthOwes) * 100 
            : -20.5;
        
        return [
            'receipts' => [
                'value' => number_format($receiptsValue, 2, ',', '.'),
                'trend' => ($receiptsTrend >= 0 ? '+' : '') . number_format($receiptsTrend, 1) . '%',
                'isUp' => $receiptsTrend >= 0,
            ],
            'contributions' => [
                'value' => number_format($contributionsValue, 2, ',', '.'),
                'trend' => ($contributionsTrend >= 0 ? '+' : '') . number_format($contributionsTrend, 1) . '%',
                'isUp' => $contributionsTrend >= 0,
            ],
            'owes' => [
                'value' => number_format($owesValue, 2, ',', '.'),
                'trend' => ($owesTrend >= 0 ? '+' : '') . number_format($owesTrend, 1) . '%',
                'isUp' => $owesTrend >= 0,
            ],
        ];
    }

    /**
     * Get recent transactions (mix of client purchase orders and supplier orders)
     */
    private function getRecentTransactions()
    {
        $transactions = [];
        
        // Get recent purchase orders from clients
        $clientOrders = PurchaseOrder::with('client')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function($order) {
                $amount = $order->total_amount;
                return [
                    'id' => $order->po_number,
                    'name' => $order->client->company_name ?? 'Unknown Client',
                    'role' => 'Client',
                    'status' => ucfirst($order->status),
                    'date' => $order->created_at->format('M d, Y'),
                    'amount' => '-₱' . number_format($amount, 2),
                    'isNegative' => true,
                    'avatar' => $this->getInitials($order->client->company_name ?? 'C'),
                ];
            });
        
        // Get recent supplier purchase orders
        $supplierOrders = ScmPurchaseOrder::with('supplier')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function($order) {
                $amount = $order->grand_total;
                return [
                    'id' => $order->po_number,
                    'name' => $order->supplier->business_name ?? 'Unknown Supplier',
                    'role' => 'Supplier',
                    'status' => ucfirst($order->status),
                    'date' => $order->created_at->format('M d, Y'),
                    'amount' => '-₱' . number_format($amount, 2),
                    'isNegative' => true,
                    'avatar' => $this->getInitials($order->supplier->business_name ?? 'S'),
                ];
            });
        
        // Merge and take latest 4
        $allTransactions = $clientOrders->concat($supplierOrders)
            ->sortByDesc('date')
            ->take(4)
            ->values();
        
        // If less than 4, add dummy data or fill with more client orders
        if ($allTransactions->count() < 4) {
            $additional = PurchaseOrder::with('client')
                ->orderBy('created_at', 'desc')
                ->limit(4 - $allTransactions->count())
                ->get()
                ->map(function($order) {
                    $amount = $order->total_amount;
                    return [
                        'id' => $order->po_number,
                        'name' => $order->client->company_name ?? 'Unknown Client',
                        'role' => 'Client',
                        'status' => ucfirst($order->status),
                        'date' => $order->created_at->format('M d, Y'),
                        'amount' => '-₱' . number_format($amount, 2),
                        'isNegative' => true,
                        'avatar' => $this->getInitials($order->client->company_name ?? 'C'),
                    ];
                });
            $allTransactions = $allTransactions->concat($additional)->take(4);
        }
        
        return $allTransactions;
    }

    /**
     * Get department modules statistics
     */
    private function getDepartmentModules()
    {
        // Human Resource Module
        $totalEmployees = User::where('is_active', true)->count();
        $openLeaveRequests = LeaveRequest::where('status', 'pending')->count();
        
        // Calculate attendance rate for current month
        $totalWorkingDays = 22; // approximate
        $totalAttendance = DB::table('attendance_logs')
            ->whereMonth('date', Carbon::now()->month)
            ->where('status', '!=', 'Absent')
            ->count();
        $uniqueEmployees = User::where('is_active', true)->count();
        $expectedAttendance = $uniqueEmployees * $totalWorkingDays;
        $attendanceRate = $expectedAttendance > 0 
            ? round(($totalAttendance / $expectedAttendance) * 100) 
            : 96;
        
        // Supply Chain Module
        $suppliersCount = Supplier::where('status', 'approved')->count();
        $pendingMaterialRequests = MaterialRequest::where('status', 'pending')->count();
        // Calculate on-time delivery rate (simplified)
        $totalOrders = ScmPurchaseOrder::count();
        $onTimeOrders = ScmPurchaseOrder::whereRaw('expected_delivery >= created_at')->count();
        $onTimeRate = $totalOrders > 0 ? round(($onTimeOrders / $totalOrders) * 100) : 94;
        
        // Manufacturing Module
        $activeManufacturingOrders = ManufacturingOrder::where('status', 'in_progress')->count();
        $totalMachines = Machine::count();
        $runningMachines = Machine::where('status', 'available')->count();
        $defectiveFabrics = Fabric::where('status', 'rejected')->count();
        $totalFabrics = Fabric::count();
        $defectRate = $totalFabrics > 0 ? round(($defectiveFabrics / $totalFabrics) * 100, 1) : 2.5;
        
        // Inventory Module
        $totalProducts = Product::count();
        // $lowStockAlerts = Product::whereColumn('stock_on_hand', '<', 'moq')->count();
        // $inventoryValue = Product::sum(DB::raw('unit_cost * stock_on_hand'));
        
        return [
            [
                'title' => 'Human Resource',
                'icon' => 'Users',
                'stats' => [
                    'Total' => $totalEmployees,
                    'Open' => $openLeaveRequests,
                    'Attendance' => $attendanceRate . '%',
                ],
                'color' => 'text-blue-500',
                'bg' => 'bg-blue-50',
            ],
            [
                'title' => 'Supply Chain',
                'icon' => 'ShoppingCart',
                'stats' => [
                    'Suppliers' => $suppliersCount,
                    'Pending' => $pendingMaterialRequests,
                    'On-time' => $onTimeRate . '%',
                ],
                'color' => 'text-blue-500',
                'bg' => 'bg-blue-50',
            ],
            [
                'title' => 'Manufacturing',
                'icon' => 'Factory',
                'stats' => [
                    'Orders' => $activeManufacturingOrders,
                    'Running' => $runningMachines . '/' . $totalMachines,
                    'Defect' => $defectRate . '%',
                ],
                'color' => 'text-blue-500',
                'bg' => 'bg-blue-50',
            ],
            [
                'title' => 'Inventory',
                'icon' => 'Package',
                'stats' => [
                    'SKUs' => $totalProducts,
                    // 'Alerts' => $lowStockAlerts,
                    // 'Value' => '₱' . number_format($inventoryValue / 1000000, 1) . 'M',
                ],
                'color' => 'text-blue-500',
                'bg' => 'bg-blue-50',
            ],
        ];
    }

    /**
     * Get initials from a string for avatar
     */
    private function getInitials($name)
    {
        $words = explode(' ', $name);
        $initials = '';
        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper(substr($word, 0, 1));
            }
        }
        return substr($initials, 0, 2);
    }
}