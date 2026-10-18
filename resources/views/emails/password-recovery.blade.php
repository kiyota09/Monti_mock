<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; color: #1e293b; background: #f1f5f9; margin: 0; padding: 24px; }
        .box { border: 1px solid #e2e8f0; border-radius: 12px; padding: 28px; max-width: 520px; margin: 0 auto; background: #ffffff; }
        h2 { margin-top: 0; color: #0f172a; }
        .password { font-size: 24px; font-weight: bold; letter-spacing: 3px; color: #0284c7; background: #f0f9ff; border: 1px dashed #7dd3fc; border-radius: 10px; padding: 14px 18px; text-align: center; }
        .warn { margin-top: 18px; padding: 12px 16px; border-radius: 10px; background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; font-weight: bold; }
        .btn { display: inline-block; margin-top: 18px; padding: 10px 20px; border-radius: 8px; background: #0284c7; color: #ffffff; text-decoration: none; font-weight: bold; }
        .muted { color: #64748b; font-size: 12px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Password Recovery</h2>
        <p>Hello {{ $recipientName }},</p>
        <p>We received a request to recover the password for your <strong>{{ $portal }}</strong> account. A new temporary password has been generated for you:</p>

        <p class="password">{{ $newPassword }}</p>

        <p>You can now sign in using this password:</p>
        <a class="btn" href="{{ $loginUrl }}">Go to Login</a>

        <div class="warn">
            IMPORTANT: Please change this password immediately after logging in. Do not share it with anyone.
        </div>

        <p class="muted">
            If you did not request a password recovery, please contact Monti Textile support right away, as someone may have access to your email account.
        </p>
    </div>
</body>
</html>
