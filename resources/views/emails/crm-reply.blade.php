<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0; padding:0; background-color:#eef2f7; font-family:'Segoe UI', Arial, Helvetica, sans-serif; color:#1f2937;">
    <span style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ $subjectLine }}</span>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef2f7; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 8px 30px rgba(15,23,42,0.12);">
                    <tr>
                        <td style="background-color:#1e3a8a; background-image:linear-gradient(135deg,#1e3a8a 0%,#4f46e5 55%,#7c3aed 100%); padding:30px 36px;">
                            <p style="margin:0; font-size:12px; letter-spacing:3px; text-transform:uppercase; color:#c7d2fe; font-weight:700;">Monti Textiles</p>
                            <h1 style="margin:8px 0 0; font-size:23px; line-height:1.3; color:#ffffff; font-weight:800;">{{ $subjectLine }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:34px 36px;">
                            <p style="margin:0 0 18px; font-size:15px; line-height:1.7;">Dear {{ $recipientName }},</p>
                            <div style="font-size:15px; line-height:1.75; color:#374151;">{!! nl2br(e($bodyText)) !!}</div>
                            <p style="margin:28px 0 0; font-size:15px; line-height:1.7; color:#374151;">
                                Warm regards,<br>
                                <strong style="color:#0f172a;">{{ $senderName ?: 'Monti Textiles CRM Team' }}</strong>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 36px; background-color:#f8fafc; border-top:1px solid #eef2f7;">
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#94a3b8;">
                                This message was sent by Monti Textiles in reply to your email.
                                If you need anything else, simply reply to this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
