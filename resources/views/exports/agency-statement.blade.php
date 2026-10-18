<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $statement->code }} — Statement</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color: #0f172a; margin: 0; background: #f1f5f9; }
        .sheet { background: #fff; max-width: 900px; margin: 24px auto; padding: 40px; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0f172a; padding-bottom: 16px; }
        .brand h1 { margin: 0; font-size: 22px; letter-spacing: .5px; text-transform: uppercase; }
        .brand p { margin: 2px 0 0; color: #64748b; font-size: 12px; }
        .meta { text-align: right; font-size: 12px; color: #334155; }
        .meta strong { display: block; font-size: 18px; color: #0f172a; }
        h2 { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin: 24px 0 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        th { background: #f8fafc; text-transform: uppercase; font-size: 10px; letter-spacing: .5px; color: #64748b; }
        .num { text-align: right; }
        .totals { margin-top: 16px; margin-left: auto; width: 320px; font-size: 13px; }
        .totals div { display: flex; justify-content: space-between; padding: 4px 0; }
        .totals .grand { border-top: 2px solid #0f172a; margin-top: 4px; padding-top: 8px; font-weight: 800; font-size: 16px; }
        .foot { margin-top: 32px; font-size: 11px; color: #94a3b8; }
        .toolbar { max-width: 900px; margin: 20px auto -8px; display: flex; gap: 8px; justify-content: flex-end; }
        .toolbar a, .toolbar button { font: inherit; font-size: 13px; font-weight: 700; padding: 8px 14px; border-radius: 10px; border: 1px solid #cbd5e1; background: #fff; color: #334155; cursor: pointer; text-decoration: none; }
        .toolbar .primary { background: #2563eb; border-color: #2563eb; color: #fff; }
        @media print {
            body { background: #fff; }
            .sheet { margin: 0; padding: 0; max-width: none; }
            .toolbar { display: none; }
            @page { margin: 16mm; }
        }
    </style>
</head>
<body>
    @php
        $money = fn ($v) => '₱' . number_format((float) $v, 2);
        $fmtH = function ($v) {
            $t = (float) $v;
            if ($t <= 0) return '0h';
            $h = (int) floor($t);
            $m = (int) round(($t - $h) * 60);
            if ($m === 60) { $h++; $m = 0; }
            return $m === 0 ? "{$h}h" : "{$h}h {$m}m";
        };
    @endphp

    <div class="toolbar">
        <a href="{{ url()->previous() }}">Back</a>
        <button type="button" class="primary" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <div class="sheet">
        <div class="head">
            <div class="brand">
                <h1>Monti Corporation</h1>
                <p>Manpower billing statement</p>
            </div>
            <div class="meta">
                <strong>{{ $statement->code }}</strong>
                <div>{{ $statement->status }}</div>
                <div>{{ $period }}</div>
            </div>
        </div>

        <h2>Agency</h2>
        <table>
            <tr>
                <th style="width:25%">Agency</th>
                <td>{{ $agency?->name ?? '—' }}</td>
                <th style="width:25%">Period</th>
                <td>{{ $period }}</td>
            </tr>
            @if ($statement->paid_at)
                <tr>
                    <th>Paid on</th>
                    <td>{{ $statement->paid_at->toDateString() }}</td>
                    <th>Reference</th>
                    <td>{{ $statement->payment_reference ?? '—' }}</td>
                </tr>
            @endif
        </table>

        <h2>Labor breakdown</h2>
        <table>
            <thead>
                <tr>
                    <th>Worker</th>
                    <th>Request</th>
                    <th>Department</th>
                    <th class="num">Days</th>
                    <th class="num">Hours</th>
                    <th class="num">OT</th>
                    <th class="num">Rate</th>
                    <th class="num">Labor</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($calc['lines'] as $l)
                    <tr>
                        <td>{{ $l['worker_name'] }} <span style="color:#94a3b8">({{ $l['worker_code'] }})</span></td>
                        <td>{{ $l['request_code'] ?? '—' }}</td>
                        <td>{{ $l['department'] ?? '—' }}</td>
                        <td class="num">{{ $l['days'] }}</td>
                        <td class="num">{{ $fmtH($l['hours']) }}</td>
                        <td class="num">{{ $fmtH($l['overtime_hours']) }}</td>
                        <td class="num">{{ $l['rate'] !== null ? $money($l['rate']) : '—' }}</td>
                        <td class="num">{{ $money($l['labor']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;color:#94a3b8">No records.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="totals">
            <div><span>Labor cost</span><span>{{ $money($statement->labor_cost) }}</span></div>
            <div><span>Service fee</span><span>{{ $money($statement->service_fee) }}</span></div>
            <div class="grand"><span>Total</span><span>{{ $money($statement->total) }}</span></div>
        </div>

        <p class="foot">Generated {{ now()->format('M j, Y g:i A') }} · {{ $statement->code }}</p>
    </div>

    <script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
</body>
</html>
