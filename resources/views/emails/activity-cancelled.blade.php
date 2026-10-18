<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ ucfirst($type) }} Cancelled</title>
</head>
<body style="margin:0; padding:0; background-color:#eef2f7; font-family:'Segoe UI', Arial, Helvetica, sans-serif; color:#1f2937;">
    <span style="display:none; max-height:0; overflow:hidden; opacity:0;">
        Your {{ $type }} with Monti Textiles has been cancelled.
    </span>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef2f7; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 8px 30px rgba(15,23,42,0.12);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color:#7f1d1d; background-image:linear-gradient(135deg,#7f1d1d 0%,#b91c1c 55%,#dc2626 100%); padding:30px 36px;">
                            <p style="margin:0; font-size:12px; letter-spacing:3px; text-transform:uppercase; color:#fecaca; font-weight:700;">Monti Textiles</p>
                            <h1 style="margin:8px 0 0; font-size:23px; line-height:1.3; color:#ffffff; font-weight:800;">
                                {{ ucfirst($type) }} Cancelled
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:34px 36px 8px;">
                            <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">Dear {{ $recipientName }},</p>

                            <p style="margin:0 0 20px; font-size:15px; line-height:1.7; color:#374151;">
                                We're sorry to let you know that the following <strong>{{ $type }}</strong> has been <strong>cancelled</strong>{{ $companyName ? ' (' . $companyName . ')' : '' }}.
                            </p>

                            <!-- Details -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; margin:0 0 24px;">
                                @if($opportunityTitle)
                                <tr>
                                    <td style="padding:13px 18px; background-color:#f8fafc; border-bottom:1px solid #eef2f7; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#64748b; width:38%;">Reference</td>
                                    <td style="padding:13px 18px; background-color:#f8fafc; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:700; color:#0f172a;">{{ $opportunityTitle }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="padding:13px 18px; border-bottom:1px solid #eef2f7; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#64748b;">Activity</td>
                                    <td style="padding:13px 18px; border-bottom:1px solid #eef2f7; font-size:14px; color:#0f172a; text-transform:capitalize;">{{ $type }}</td>
                                </tr>
                                @if($summary)
                                <tr>
                                    <td style="padding:13px 18px; border-bottom:1px solid #eef2f7; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#64748b;">Subject</td>
                                    <td style="padding:13px 18px; border-bottom:1px solid #eef2f7; font-size:14px; color:#0f172a;">{{ $summary }}</td>
                                </tr>
                                @endif
                                @if($wasAt)
                                <tr>
                                    <td style="padding:13px 18px; {{ $ownerName ? 'border-bottom:1px solid #eef2f7;' : '' }} font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#64748b;">Was scheduled</td>
                                    <td style="padding:13px 18px; {{ $ownerName ? 'border-bottom:1px solid #eef2f7;' : '' }} font-size:14px; font-weight:700; color:#0f172a; text-decoration:line-through;">{{ $wasAt }}</td>
                                </tr>
                                @endif
                                @if($ownerName)
                                <tr>
                                    <td style="padding:13px 18px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#64748b;">Contact Person</td>
                                    <td style="padding:13px 18px; font-size:14px; color:#0f172a;">{{ $ownerName }}</td>
                                </tr>
                                @endif
                            </table>

                            <!-- Cancellation reason -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                                <tr>
                                    <td style="padding:16px 18px; background-color:#fef2f2; border-left:4px solid #dc2626; border-radius:8px; font-size:14px; line-height:1.7; color:#7f1d1d;">
                                        <strong style="display:block; margin-bottom:4px; color:#991b1b; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Reason</strong>
                                        {{ $reason }}
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 8px; font-size:15px; line-height:1.7; color:#374151;">
                                Our team will reach out to arrange a new schedule if you'd like. We apologize for any inconvenience.
                            </p>

                            <p style="margin:18px 0 0; font-size:15px; line-height:1.6; color:#374151;">Warm regards,</p>
                            <p style="margin:4px 0 0; font-size:15px; font-weight:800; color:#1e3a8a;">Monti Textiles</p>
                            <p style="margin:2px 0 0; font-size:13px; color:#64748b;">Client Relations Team</p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:22px 36px 30px;">
                            <hr style="border:none; border-top:1px solid #eef2f7; margin:0 0 16px;">
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#94a3b8;">
                                This is an automated notice from Monti Textiles.
                            </p>
                            <p style="margin:8px 0 0; font-size:12px; color:#94a3b8;">&copy; {{ date('Y') }} Monti Textiles. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
