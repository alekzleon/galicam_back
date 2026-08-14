<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $copy['title'] ?? 'Reset your password' }}</title>
</head>
<body style="margin:0;background:#f5f7fb;color:#1f2937;font-family:Arial,Helvetica,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f5f7fb;padding:24px 12px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:24px 28px;background:#111827;color:#ffffff;">
                            <div style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;color:#d1d5db;">
                                {{ $settings->site_title ?? 'Cloudi Shop' }}
                            </div>
                            <h1 style="margin:10px 0 0;font-size:24px;line-height:1.25;">
                                {{ $copy['title'] ?? 'Reset your password' }}
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 14px;font-size:16px;line-height:1.5;">
                                {{ $copy['greeting'] ?? '' }}
                            </p>
                            <p style="margin:0 0 22px;font-size:15px;line-height:1.5;color:#4b5563;">
                                {{ $copy['body'] ?? '' }}
                            </p>

                            <div style="text-align:center;margin:28px 0 18px;">
                                <a href="{{ $resetUrl }}" style="display:inline-block;background:#2563eb;color:#ffffff;padding:13px 22px;text-decoration:none;border-radius:6px;font-size:15px;font-weight:bold;">
                                    {{ $copy['button'] ?? 'Change password' }}
                                </a>
                            </div>

                            <p style="margin:0;font-size:12px;line-height:1.5;color:#6b7280;text-align:center;">
                                {{ $copy['footer'] ?? '' }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
