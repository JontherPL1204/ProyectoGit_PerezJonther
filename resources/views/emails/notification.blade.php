<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $notification->subject }}</title>
</head>
<body style="margin:0; padding:0; background:#f5f6f2; color:#111318; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f6f2; margin:0; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%; max-width:640px; background:#ffffff; border:2px solid #16181d; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="padding:24px 28px; border-bottom:2px solid #16181d;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <span style="display:inline-block; width:34px; height:34px; line-height:34px; text-align:center; background:#b8ff4d; border:2px solid #16181d; border-radius:8px; font-weight:900; color:#111318;">N</span>
                                        <span style="display:inline-block; margin-left:10px; font-size:18px; font-weight:800; color:#111318;">{{ $appName }}</span>
                                    </td>
                                    <td align="right" style="vertical-align:middle;">
                                        <span style="display:inline-block; padding:8px 12px; border-radius:999px; background:{{ $presentation['badgeBackground'] }}; color:{{ $presentation['badgeColor'] }}; font-size:12px; font-weight:800; text-transform:uppercase;">
                                            {{ $presentation['badge'] }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:30px 28px 18px;">
                            <p style="margin:0 0 8px; color:#696f7a; font-size:12px; font-weight:900; text-transform:uppercase; letter-spacing:.04em;">{{ $presentation['eyebrow'] }}</p>
                            <h1 style="margin:0; color:#111318; font-size:30px; line-height:1.12; font-weight:800;">{{ $presentation['headline'] }}</h1>
                            <div style="width:72px; height:6px; margin:20px 0 0; background:{{ $presentation['accent'] }}; border-radius:999px;"></div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 28px 22px;">
                            <p style="margin:0; color:#343842; font-size:15px; line-height:1.55;">{{ $messageBody }}</p>
                        </td>
                    </tr>

                    @if ($leaveRequest)
                        <tr>
                            <td style="padding:0 28px 24px;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #d9dcd4; border-radius:8px; overflow:hidden;">
                                    @foreach ($details as $label => $value)
                                        <tr>
                                            <td style="width:38%; padding:13px 14px; background:#f5f6f2; border-bottom:1px solid #e7e9e2; color:#696f7a; font-size:12px; font-weight:900; text-transform:uppercase;">{{ $label }}</td>
                                            <td style="padding:13px 14px; border-bottom:1px solid #e7e9e2; color:#111318; font-size:14px; font-weight:700;">{{ $value }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>

                        @if ($comments)
                            <tr>
                                <td style="padding:0 28px 24px;">
                                    @foreach ($comments as $label => $comment)
                                        <div style="margin-bottom:12px; padding:14px 16px; background:#f5f6f2; border-left:5px solid {{ $presentation['accent'] }}; border-radius:6px;">
                                            <p style="margin:0 0 6px; color:#696f7a; font-size:12px; font-weight:900; text-transform:uppercase;">{{ $label }}</p>
                                            <p style="margin:0; color:#111318; font-size:14px; line-height:1.5;">{{ $comment }}</p>
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        @endif

                        <tr>
                            <td align="center" style="padding:0 28px 30px;">
                                <a href="{{ $requestUrl }}" style="display:inline-block; min-width:180px; padding:14px 20px; background:#111318; border:2px solid #111318; border-radius:8px; color:#ffffff; font-size:15px; font-weight:900; text-align:center; text-decoration:none;">
                                    {{ $presentation['cta'] }}
                                </a>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:18px 28px; background:#111318; color:#ffffff;">
                            <p style="margin:0 0 6px; font-size:13px; font-weight:800;">Nota de seguridad</p>
                            <p style="margin:0; color:#d9dcd4; font-size:12px; line-height:1.5;">{{ $securityNote }}</p>
                        </td>
                    </tr>
                </table>

                <p style="max-width:640px; margin:14px auto 0; color:#696f7a; font-size:12px; line-height:1.5; text-align:center;">
                    Este correo fue generado automaticamente por {{ $appName }}. Si no esperabas esta notificacion, revisa el historial dentro de la aplicacion.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
