<?php

namespace App\Mail;

use App\Models\Scm\ProcurementPayment;
use App\Models\Scm\PurchaseInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SupplierInvoicePaid extends Mailable
{
    use Queueable, SerializesModels;

    public PurchaseInvoice $invoice;
    public ProcurementPayment $payment;

    public function __construct(PurchaseInvoice $invoice, ProcurementPayment $payment)
    {
        $this->invoice = $invoice;
        $this->payment = $payment;
    }

    public function build()
    {
        return $this->subject("Payment Received - Invoice {$this->invoice->invoice_number}")
            ->view('emails.supplier-invoice-paid');
    }
}