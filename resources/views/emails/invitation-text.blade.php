{{ $appName }}

Te invitaron a {{ $organization->name }}.

Acepta la invitacion desde este enlace:
{{ $inviteUrl }}

El enlace caduca el {{ $invitation->expires_at->format('d/m/Y H:i') }} UTC.

Si no esperabas esta invitacion, puedes ignorar este correo.
