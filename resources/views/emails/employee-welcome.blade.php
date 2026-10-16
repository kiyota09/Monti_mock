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
        <p>Dear {{ $employeeName }},</p>

        <p><strong>Welcome to Monti Corporation!</strong></p>

        <p>Your employee account has been successfully created.</p>

        <p class="row"><span class="label">Employee ID:</span> <span class="value">{{ $employeeId }}</span></p>
        <p class="row"><span class="label">Position:</span> <span class="value">{{ $position }}</span></p>
        <p class="row"><span class="label">Department:</span> <span class="value">{{ $department }}</span></p>
        <p class="row"><span class="label">Company Email:</span> <span class="value">{{ $companyEmail }}</span></p>
        <p class="row"><span class="label">Temporary Password:</span> <span class="value">{{ $tempPassword }}</span></p>

        <p>Your training is scheduled to begin on <strong>{{ $trainingStartDate }}</strong>.</p>

        <p>You may use your company email to access the Employee Portal after activation.</p>

        <a class="btn" href="{{ $portalUrl }}">Access Employee Portal</a>

        <p class="footer">If you have any questions, please contact the HR Department.<br><br>Welcome to the team!<br><br><strong>Monti HR Department</strong></p>
    </div>
</body>
</html>
