<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Fin\FinInvoice;
use App\Models\Fin\FinPaymongoCheckout;
use App\Services\Fin\FinanceService;
use App\Services\Fin\PayMongoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Partner portal payments: a business client settles its receivable invoices
 * with the same PayMongo hosted checkout used across FIN. Every confirmed
 * charge is written to the receivables ledger, so it appears instantly on the
 * FIN Receivables screen (with its receipt) alongside any manually recorded
 * collections.
 */
class ClientPaymentController extends Controller
{
    public function __construct(protected FinanceService $finance, protected PayMongoService $gateway) {}

    protected function client()
    {
        return Auth::guard('client')->user();
    }

    public function index()
    {
        $client = $this->client();

        return Inertia::render('Client/Payments', [
            'client' => $client,
            'invoices' => $this->finance->invoiceRows($client->id),
            'gateway' => [
                'mode' => $this->gateway->mode(),
                'configured' => $this->gateway->isConfigured(),
            ],
        ]);
    }

    /**
     * Start a PayMongo hosted checkout for one of the client's invoices.
     * Nothing is written to the ledger until PayMongo confirms the charge on
     * the return URL.
     */
    public function startCheckout(Request $request, FinInvoice $invoice)
    {
        $client = $this->client();

        abort_unless((int) $invoice->client_id === (int) $client->id, 403);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . max(0.01, $invoice->balance()),
        ]);

        $amount = round((float) $data['amount'], 2);
        $reference = 'MNT-I' . $invoice->id . '-' . strtoupper(Str::random(10));

        $checkout = FinPaymongoCheckout::create([
            'reference' => $reference,
            'fin_invoice_id' => $invoice->id,
            'amount' => $amount,
            'status' => 'pending',
            'created_by' => null,
        ]);

        $session = $this->gateway->createCheckoutSession($amount, [
            'reference_number' => $reference,
            'description' => 'Payment for invoice ' . $invoice->invoice_no,
            'item_name' => 'Invoice ' . $invoice->invoice_no,
            'item_description' => $client->company_name . ' · ' . $invoice->invoice_no,
            'metadata' => [
                'invoice_id' => (string) $invoice->id,
                'invoice_no' => (string) $invoice->invoice_no,
                'client_id' => (string) $client->id,
                'reference' => $reference,
            ],
            'success_url' => route('client.payments.checkout.return', ['ref' => $reference, 'result' => 'success']),
            'cancel_url' => route('client.payments.checkout.return', ['ref' => $reference, 'result' => 'cancel']),
        ]);

        if (! $session['success'] || ! $session['checkout_url']) {
            $checkout->update(['status' => 'failed']);

            return response()->json([
                'message' => $session['message'] ?? 'Could not start PayMongo checkout.',
            ], 422);
        }

        $checkout->update([
            'paymongo_session_id' => $session['session_id'],
            'checkout_url' => $session['checkout_url'],
        ]);

        return response()->json([
            'checkout_url' => $session['checkout_url'],
            'reference' => $reference,
        ]);
    }

    /**
     * PayMongo return URL: the client lands here after the hosted checkout.
     * The session is verified against the gateway (works locally — no webhook
     * required) and only then is the receivable payment written.
     */
    public function checkoutReturn(Request $request)
    {
        $client = $this->client();
        $reference = (string) $request->query('ref', '');
        $result = (string) $request->query('result', 'success');

        $checkout = FinPaymongoCheckout::with('invoice')->where('reference', $reference)->first();

        if (! $checkout || ! $checkout->invoice || (int) $checkout->invoice->client_id !== (int) $client->id) {
            return redirect()->route('client.payments')
                ->withErrors(['error' => 'Unknown PayMongo checkout reference.']);
        }

        $invoice = $checkout->invoice;

        if ($checkout->isPaid()) {
            return redirect()->route('client.payments')
                ->with('message', 'Your payment for ' . $invoice->invoice_no . ' was already recorded.');
        }

        if ($result === 'cancel') {
            $checkout->update(['status' => 'failed']);

            return redirect()->route('client.payments')
                ->withErrors(['error' => 'PayMongo checkout was cancelled — no payment recorded.']);
        }

        $verify = $this->gateway->verifyCheckoutSession($checkout->paymongo_session_id);

        if (! $verify['paid']) {
            return redirect()->route('client.payments')
                ->withErrors(['error' => $verify['message'] ?? 'PayMongo has not confirmed your payment yet.']);
        }

        $paidAmount = round((float) ($verify['amount'] ?? $checkout->amount), 2);
        $paidAmount = min($paidAmount, max(0.0, $invoice->balance()));

        if ($paidAmount > 0) {
            $this->finance->recordInvoicePayment($invoice, [
                'amount' => $paidAmount,
                'paid_at' => today()->toDateString(),
                'method' => $verify['method'] ?? 'paymongo',
                'reference' => $verify['payment_id'] ?? $checkout->reference,
            ], null);
        }

        $checkout->update([
            'status' => 'paid',
            'method' => $verify['method'],
            'gateway_reference' => $verify['payment_id'],
            'paid_at' => now(),
        ]);

        return redirect()->route('client.payments')
            ->with('message', 'PayMongo payment of ₱' . number_format($paidAmount, 2) . ' recorded for ' . $invoice->invoice_no . '.');
    }
}
