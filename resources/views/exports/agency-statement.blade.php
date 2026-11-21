<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $statement->code }}</title>
    <style>
        body { color: #000; font: 14px Arial, sans-serif; margin: 32px; }
        h1, h2, p, th, td { color: #000; }
        h1 { margin-bottom: 4px; }
        .meta { margin: 0 0 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #555; padding: 7px 9px; text-align: left; }
        th { background: #e5e7eb; }
        .totals { margin: 20px 0 0 auto; width: 320px; }
        .totals td:last-child { text-align: right; }
        @media print { body { margin: 16mm; } }
    </style>
</head>
<body>
    <h1>{{ $statement->code }}</h1>
    <p class="meta">{{ $agency?->name }} · {{ $period }} · {{ ucfirst($statement->status) }}</p>

    <h2>Breakdown by worker and request</h2>
    <table>
        <thead><tr><th>Worker</th><th>Request</th><th>Department</th><th>Days</th><th>Hours</th><th>OT hours</th><th>Rate</th><th>Labor</th></tr></thead>
        <tbody>
            @foreach ($calc['lines'] as $line)
                <tr>
                    <td>{{ $line['worker_name'] }}</td><td>{{ $line['request_code'] }}</td><td>{{ $line['department'] }}</td>
                    <td>{{ $line['days'] }}</td><td>{{ $line['hours'] }}h</td><td>{{ $line['overtime_hours'] }}</td>
                    <td>₱{{ number_format((float) $line['rate'], 2) }}</td><td>₱{{ number_format((float) $line['labor'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tbody>
            <tr><td>Records</td><td>{{ $calc['count'] }}</td></tr>
            <tr><td>Labor cost</td><td>₱{{ number_format((float) $statement->labor_cost, 2) }}</td></tr>
            <tr><td>Service fee</td><td>₱{{ number_format((float) $statement->service_fee, 2) }}</td></tr>
            <tr><th>Total</th><th>₱{{ number_format((float) $statement->total, 2) }}</th></tr>
        </tbody>
    </table>
</body>
</html>
