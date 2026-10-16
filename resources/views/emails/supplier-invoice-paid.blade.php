<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <h2 style="color: #059669;">Payment Received</h2>

    <p>Dear {{ $invoice->supplier_name }},</p>

    <p>We're writing to confirm that Monti Textiles has processed payment for the following invoice:</p>

    <table style="border-collapse: collapse; width: 100%; margin: 16px 0;">
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">Invoice Number</td>
            <td style="padding: 6px 0; font-weight: bold;">{{ $invoice->invoice_number }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">PO Number</td>
            <td style="padding: 6px 0;">{{ $invoice->po_number }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">Payment Reference</td>
            <td style="padding: 6px 0;">{{ $payment->payment_number }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">Amount Paid</td>
            <td style="padding: 6px 0; font-weight: bold;">₱{{ number_format($payment->amount, 2) }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">Payment Method</td>
            <td style="padding: 6px 0;">{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</td>
        </tr>
        @if($payment->bank_reference)
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">Reference No.</td>
            <td style="padding: 6px 0;">{{ $payment->bank_reference }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">Date Paid</td>
            <td style="padding: 6px 0;">{{ \Illuminate\Support\Carbon::parse($payment->paid_date)->format('F j, Y') }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">Invoice Status</td>
            <td style="padding: 6px 0; text-transform: uppercase; font-weight: bold;">{{ $invoice->status }}</td>
        </tr>
    </table>

    @if($payment->remarks)
    <p><strong>Remarks:</strong> {{ $payment->remarks }}</p>
    @endif

    <p>Thank you for your continued partnership.</p>

    <p style="color: #9ca3af; font-size: 12px; margin-top: 24px;">
        This is an automated notification from the Monti Textiles Procurement System.
    </p>
</body>
</html>