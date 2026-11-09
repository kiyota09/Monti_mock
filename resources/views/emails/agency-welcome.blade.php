<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>Agency Portal Access</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:Arial, Helvetica, sans-serif; color:#172033;">
    <span style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">
        Your {{ $agencyName }} agency portal sign-in details are ready.
    </span>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9; padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden;">
                    <tr>
                        <td style="padding:28px 32px; background-color:#102a43; border-bottom:4px solid #2f80ed;">
                            <p style="margin:0; color:#ffffff; font-size:17px; font-weight:800; letter-spacing:1.5px;">MONTI <span style="color:#8ec5ff;">TEXTILE</span></p>
                            <p style="margin:7px 0 0; color:#cbd5e1; font-size:11px; font-weight:700; letter-spacing:2px; text-transform:uppercase;">Agency Portal</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px 32px 12px;">
                            <p style="margin:0 0 8px; color:#64748b; font-size:12px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase;">
                                {{ $isReset ? 'Account security' : 'Your account is ready' }}
                            </p>
                            <h1 style="margin:0; color:#102a43; font-size:25px; line-height:1.3;">{{ $isReset ? 'Your password was reset' : 'Welcome to the Agency Portal' }}</h1>
                            <p style="margin:18px 0 0; color:#334155; font-size:15px; line-height:1.7;">Dear {{ $userName }},</p>
                            @if ($isReset)
                                <p style="margin:10px 0 0; color:#475569; font-size:14px; line-height:1.7;">Monti Corp HR reset the portal password for <strong style="color:#102a43;">{{ $agencyName }}</strong>. Use the sign-in details below to access your account.</p>
                            @else
                                <p style="margin:10px 0 0; color:#475569; font-size:14px; line-height:1.7;">Monti Corp HR created an agency portal account for <strong style="color:#102a43;">{{ $agencyName }}</strong>. Use the sign-in details below to get started.</p>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 32px 8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #dbe5ef; border-radius:12px; overflow:hidden;">
                                <tr>
                                    <td colspan="2" style="padding:13px 16px; background-color:#f1f7fd; border-bottom:1px solid #dbe5ef; color:#285b8f; font-size:11px; font-weight:800; letter-spacing:1.2px; text-transform:uppercase;">Your sign-in details</td>
                                </tr>
                                <tr>
                                    <td style="width:36%; padding:14px 16px; border-bottom:1px solid #e8eef4; color:#64748b; font-size:12px; font-weight:700;">Login email</td>
                                    <td style="padding:14px 16px; border-bottom:1px solid #e8eef4; color:#172033; font-size:14px; font-weight:700; word-break:break-word;">{{ $loginEmail }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px; color:#64748b; font-size:12px; font-weight:700;">Temporary password</td>
                                    <td style="padding:14px 16px; color:#102a43; font-family:Consolas, 'Courier New', monospace; font-size:15px; font-weight:800; letter-spacing:1px; word-break:break-word;">{{ $tempPassword }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:24px 32px 12px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="border-radius:9px; background-color:#1769aa;">
                                        <a href="{{ $portalUrl }}" style="display:inline-block; padding:14px 28px; border:1px solid #1769aa; border-radius:9px; color:#ffffff; font-size:14px; font-weight:700; text-decoration:none;">{{ $isReset ? 'Sign in to your account' : 'Access Agency Portal' }}</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 32px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:14px 16px; border-left:4px solid #e7a83e; border-radius:6px; background-color:#fff8e8; color:#704b13; font-size:12px; line-height:1.7;">
                                        <strong style="display:block; margin-bottom:3px; color:#704b13; font-size:11px; letter-spacing:1px; text-transform:uppercase;">Keep your account secure</strong>
                                        Sign in and change your temporary password. Keep your password private. HR can reset it if you need help.
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:22px 0 0; color:#475569; font-size:13px; line-height:1.7;">If you have questions, please contact the HR Department.</p>
                            <p style="margin:16px 0 0; color:#102a43; font-size:13px; font-weight:700;">Monti HR Department</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 32px; border-top:1px solid #e8eef4; background-color:#f8fafc; color:#94a3b8; font-size:11px; line-height:1.6; text-align:center;">
                            This message contains account credentials intended for {{ $userName }}. Please do not forward it.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
