<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; color: #1e293b; }
        .code { font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #0284c7; }
        .box { border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; max-width: 480px; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Verify your email</h2>
        <p>Use this verification code to confirm your Monti Textile applicant account:</p>
        <p class="code">{{ $code }}</p>
        <p>This code expires in {{ $expiresMinutes }} minutes. If you did not request this, you can ignore this email.</p>
    </div>
</body>
</html>
