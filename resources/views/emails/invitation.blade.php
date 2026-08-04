<!DOCTYPE html>
<html lang="es">
<body style="margin:0; background:#f5f6f2; font-family:Arial, Helvetica, sans-serif; color:#111318;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f6f2; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px; background:#ffffff; border:2px solid #16181d; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="padding:26px 28px; border-bottom:2px solid #16181d;">
                            <div style="font-size:14px; font-weight:700;">{{ $appName }}</div>
                            <h1 style="margin:14px 0 0; font-size:28px; line-height:1.15;">Te invitaron a {{ $organization->name }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 16px; color:#343840; font-size:16px; line-height:1.6;">
                                Usa este enlace para crear tu cuenta y entrar al equipo. El enlace caduca el
                                <strong>{{ $invitation->expires_at->format('d/m/Y H:i') }} UTC</strong>.
                            </p>

                            <p style="margin:24px 0;">
                                <a href="{{ $inviteUrl }}" style="display:inline-block; background:#111318; color:#ffffff; border:2px solid #111318; border-radius:8px; padding:13px 18px; font-weight:800; text-decoration:none;">
                                    Aceptar invitacion
                                </a>
                            </p>

                            <p style="margin:0; color:#696f7a; font-size:13px; line-height:1.6;">
                                Si no esperabas esta invitacion, puedes ignorar este correo. No compartas este enlace en lugares publicos.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
