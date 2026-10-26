<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Registration</title>
</head>
<body style="margin:0; padding:0; background-color:#eef2f7; font-family:'Segoe UI', Arial, Helvetica, sans-serif; color:#1f2937;">
    <span style="display:none; max-height:0; overflow:hidden; opacity:0;">
        Complete your Monti Textiles business partner registration.
    </span>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef2f7; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 8px 30px rgba(15,23,42,0.12);">
                    <tr>
                        <td style="background-color:#1e3a8a; background-image:linear-gradient(135deg,#1e3a8a 0%,#4f46e5 55%,#7c3aed 100%); padding:30px 36px;">
                            <p style="margin:0; font-size:12px; letter-spacing:3px; text-transform:uppercase; color:#c7d2fe; font-weight:700;">Monti Textiles</p>
                            <h1 style="margin:8px 0 0; font-size:23px; line-height:1.3; color:#ffffff; font-weight:800;">Partner Registration</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:34px 36px 8px;">
                            <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">Dear {{ $recipientName }},</p>
                            <p style="margin:0 0 20px; font-size:15px; line-height:1.7; color:#374151;">
                                Thank you for reaching out to Monti Textiles{{ $companyName ? ' on behalf of ' . $companyName : '' }}.
                                We would be glad to explore a business partnership with you.
                                Please complete the short registration form below so our team can prepare the right offer for your business.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:28px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $registrationUrl }}"
                                           style="display:inline-block; padding:15px 34px; background-color:#4f46e5; background-image:linear-gradient(135deg,#4f46e5 0%,#7c3aed 100%); color:#ffffff; font-size:15px; font-weight:800; text-decoration:none; border-radius:12px; box-shadow:0 10px 24px rgba(79,70,229,0.28);">
                                            Open Registration Form
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 10px; font-size:13px; line-height:1.7; color:#64748b;">
                                If the button does not open, copy and paste this link into your browser:
                            </p>
                            <p style="margin:0 0 22px; font-size:12px; line-height:1.6; color:#4f46e5; word-break:break-all;">
                                {{ $registrationUrl }}
                            </p>

                            @if($expiresAt)
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#94a3b8;">
                                This registration link is valid until {{ $expiresAt }}.
                            </p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 36px; background-color:#f8fafc; border-top:1px solid #eef2f7;">
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#94a3b8;">
                                Sent by {{ $senderName ?: 'the Monti Textiles CRM Team' }}. If you did not request this,
                                you can safely ignore this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
