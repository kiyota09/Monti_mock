<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your payslip</title>
</head>
<body style="margin:0;background:#f3f4f6;color:#111827;font-family:Arial,sans-serif;">
    <div style="max-width:680px;margin:24px auto;padding:24px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
        <h1 style="margin:0 0 6px;font-size:22px;">Your payslip</h1>
        <p style="margin:0 0 22px;color:#6b7280;font-size:14px;">Payroll has been marked paid. Keep this message for your records.</p>

        <table role="presentation" style="width:100%;border-collapse:collapse;margin-bottom:20px;font-size:13px;">
            <tr><td style="padding:7px 0;color:#6b7280;">Employee</td><td style="padding:7px 0;text-align:right;font-weight:bold;">{{ $payroll->employee_name }}</td></tr>
            <tr><td style="padding:7px 0;color:#6b7280;">Employee ID</td><td style="padding:7px 0;text-align:right;">{{ $payroll->employee_id }}</td></tr>
            <tr><td style="padding:7px 0;color:#6b7280;">Department</td><td style="padding:7px 0;text-align:right;">{{ $payroll->department }}</td></tr>
            <tr><td style="padding:7px 0;color:#6b7280;">Pay period</td><td style="padding:7px 0;text-align:right;">{{ $payroll->period }}</td></tr>
            <tr><td style="padding:7px 0;color:#6b7280;">Payment date</td><td style="padding:7px 0;text-align:right;">{{ $payroll->paid_at?->format('M j, Y') ?? now()->format('M j, Y') }}</td></tr>
            <tr><td style="padding:7px 0;color:#6b7280;">Payment reference</td><td style="padding:7px 0;text-align:right;">{{ $payroll->payment_reference ?: '—' }}</td></tr>
        </table>

        <h2 style="margin:20px 0 8px;font-size:15px;">Earnings</h2>
        <table role="presentation" style="width:100%;border-collapse:collapse;font-size:13px;">
            <tr><td style="padding:8px;border-bottom:1px solid #e5e7eb;">Regular pay</td><td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;">₱{{ number_format((float) $payroll->total_days_amt, 2) }}</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #e5e7eb;">Allowance</td><td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;">₱{{ number_format((float) $payroll->allowance, 2) }}</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #e5e7eb;">Night differential</td><td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;">₱{{ number_format((float) $payroll->night_amt, 2) }}</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #e5e7eb;">Overtime</td><td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;">₱{{ number_format((float) $payroll->ot_amt, 2) }}</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #e5e7eb;">Sunday / rest day</td><td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;">₱{{ number_format((float) $payroll->sun_sp_amt, 2) }}</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #e5e7eb;">Holiday pay</td><td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;">₱{{ number_format((float) $payroll->holiday_amt, 2) }}</td></tr>
            <tr><td style="padding:10px 8px;font-weight:bold;">Gross pay</td><td style="padding:10px 8px;text-align:right;font-weight:bold;">₱{{ number_format((float) $payroll->gross_pay, 2) }}</td></tr>
        </table>

        <h2 style="margin:20px 0 8px;font-size:15px;">Deductions</h2>
        <table role="presentation" style="width:100%;border-collapse:collapse;font-size:13px;">
            @foreach ([
                'SSS' => $payroll->sss_deduction,
                'PhilHealth' => $payroll->philhealth_deduction,
                'Pag-IBIG' => $payroll->pagibig_deduction,
                'Tax withheld' => $payroll->tax_withheld,
                'SSS loan' => $payroll->sss_loan,
                'PF loan' => $payroll->pf_loan,
                'Advance' => $payroll->advance_deduction,
                'Late deductions' => $payroll->late_total_deduction,
            ] as $label => $amount)
                @if ((float) $amount !== 0.0)
                    <tr><td style="padding:8px;border-bottom:1px solid #e5e7eb;">{{ $label }}</td><td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;">₱{{ number_format((float) $amount, 2) }}</td></tr>
                @endif
            @endforeach
            <tr><td style="padding:10px 8px;font-weight:bold;">Total deductions</td><td style="padding:10px 8px;text-align:right;font-weight:bold;">₱{{ number_format(max(0, (float) $payroll->gross_pay - (float) $payroll->net_pay), 2) }}</td></tr>
        </table>

        <div style="margin-top:16px;padding:16px;border-radius:8px;background:#ecfdf5;color:#065f46;">
            <span style="font-size:13px;font-weight:bold;">Net pay</span>
            <strong style="float:right;font-size:18px;">₱{{ number_format((float) $payroll->net_pay, 2) }}</strong>
        </div>
        <p style="margin:20px 0 0;color:#9ca3af;font-size:11px;">This is an automatically generated payslip. Contact HR if you have questions about the details.</p>
    </div>
</body>
</html>
