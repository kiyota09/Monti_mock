<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; color: #1e293b; line-height: 1.6; }
        .box { border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; max-width: 520px; }
        .row { margin: 4px 0; }
        .label { color: #64748b; }
        .value { font-weight: bold; }
        .btn { display: inline-block; margin-top: 16px; padding: 10px 20px; background: #0284c7; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: bold; }
        .footer { margin-top: 20px; font-size: 13px; color: #64748b; }
    </style>
</head>
<body>
    <div class="box">
        <p>Dear {{ $userName }},</p>

        @if ($isReset)
            <p>Your portal password for <strong>{{ $agencyName }}</strong> has been reset by Monti Corp HR.</p>
        @else
            <p>A portal login for <strong>{{ $agencyName }}</strong> has been created for you by Monti Corp HR.</p>
        @endif

        <p class="row"><span class="label">Login email:</span> <span class="value">{{ $loginEmail }}</span></p>
        <p class="row"><span class="label">Temporary Password:</span> <span class="value">{{ $tempPassword }}</span></p>

        <p>Please sign in and keep your password private. HR can reset it for you if needed.</p>

        <a class="btn" href="{{ $portalUrl }}">Access Agency Portal</a>

        <p class="footer">If you have any questions, please contact the HR Department.<br><br><strong>Monti HR Department</strong></p>
    </div>
</body>
</html>
