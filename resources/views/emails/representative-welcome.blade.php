<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Monti Textiles</title>
</head>
<body style="margin:0; padding:0; background-color:#eef2f7; font-family:'Segoe UI', Arial, Helvetica, sans-serif; color:#1f2937;">
    <span style="display:none; max-height:0; overflow:hidden; opacity:0;">
        Your Monti Textiles partner portal account is ready — sign-in details inside.
    </span>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef2f7; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 8px 30px rgba(15,23,42,0.12);">
                    <!-- Celebration header -->
                    <tr>
                        <td style="background-color:#1e3a8a; background-image:linear-gradient(135deg,#1e3a8a 0%,#4f46e5 55%,#7c3aed 100%); padding:40px 36px; text-align:center;">
                            <p style="margin:0 0 6px; font-size:12px; letter-spacing:4px; text-transform:uppercase; color:#c7d2fe; font-weight:700;">Monti Textiles</p>
                            <h1 style="margin:0; font-size:27px; line-height:1.3; color:#ffffff; font-weight:800;">Welcome Aboard! &#127881;</h1>
                            <p style="margin:10px 0 0; font-size:14px; color:#e0e7ff;">You have been granted access to the partner portal.</p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:36px 36px 8px;">
                            <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">Dear {{ $representativeName }},</p>

                            <p style="margin:0 0 20px; font-size:15px; line-height:1.7; color:#374151;">
                                Warm greetings from Monti Textiles! We are pleased to welcome you as an authorized
                                representative of <strong>{{ $companyName }}</strong>. Your partner portal account has
                                been created, and you can now sign in to track orders, review quotations, manage
                                documents, and stay connected with our team.
                            </p>

                            <!-- Credentials card -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; margin:0 0 24px;">
                                <tr>
                                    <td colspan="2" style="padding:14px 18px; background-color:#eef2ff; border-bottom:1px solid #e0e7ff; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:#4338ca;">
                                        Your Sign-in Credentials
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px; border-bottom:1px solid #eef2f7; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#64748b; width:38%;">Username</td>
                                    <td style="padding:14px 18px; border-bottom:1px solid #eef2f7; font-size:14px; font-weight:700; color:#0f172a;">{{ $email }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#64748b;">Temporary Password</td>
                                    <td style="padding:14px 18px; font-size:14px; font-weight:700; color:#0f172a; letter-spacing:1px;">{{ $password }}</td>
                                </tr>
                            </table>

                            <!-- CTA -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 26px;">
                                <tr>
                                    <td style="border-radius:10px; background-color:#4f46e5;">
                                        <a href="{{ $portalUrl }}" style="display:inline-block; padding:14px 38px; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none;">Sign in to the Partner Portal</a>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                                <tr>
                                    <td style="padding:16px 18px; background-color:#fff7ed; border-left:4px solid #f59e0b; border-radius:8px; font-size:14px; line-height:1.7; color:#7c2d12;">
                                        <strong style="display:block; margin-bottom:4px; color:#92400e; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Security Reminder</strong>
                                        For your protection, please sign in and change your temporary password to
                                        something only you know. Never share your credentials with anyone.
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 26px; font-size:15px; line-height:1.7; color:#374151;">
                                Thank you for being part of {{ $companyName }}'s journey with us. Should you need any
                                assistance, simply reply to this email and our team will be happy to help.
                            </p>

                            <p style="margin:0; font-size:15px; line-height:1.6; color:#374151;">Warmest regards,</p>
                            <p style="margin:4px 0 0; font-size:15px; font-weight:800; color:#1e3a8a;">Monti Textiles</p>
                            <p style="margin:2px 0 0; font-size:13px; color:#64748b;">Client Relations Team</p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:22px 36px 30px;">
                            <hr style="border:none; border-top:1px solid #eef2f7; margin:0 0 16px;">
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#94a3b8;">
                                You are receiving this email because you were added as a representative of
                                {{ $companyName }} on Monti Textiles.
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
