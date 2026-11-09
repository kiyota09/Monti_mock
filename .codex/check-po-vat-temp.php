<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$orders = Illuminate\Support\Facades\DB::table('purchase_orders')->whereNotNull('inquiry_id')->orderByDesc('id')->limit(5)->get(['id', 'po_number', 'client_po_number', 'subtotal', 'tax_rate', 'tax_amount', 'total_amount', 'status']);
foreach ($orders as $order) {
    $order->lines = Illuminate\Support\Facades\DB::table('purchase_order_items')->where('purchase_order_id', $order->id)->get(['quantity', 'unit_price', 'price']);
    $order->sales_orders = Illuminate\Support\Facades\DB::table('sales_orders')->where('purchase_order_id', $order->po_number)->get(['status', 'subtotal', 'tax_rate', 'tax_amount', 'total_amount']);
}
echo json_encode($orders, JSON_PRETTY_PRINT), PHP_EOL;
