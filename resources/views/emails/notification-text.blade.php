{{ $appName }}
{{ $presentation['headline'] }}

{{ $messageBody }}

@if ($leaveRequest)
@foreach ($details as $label => $value)
{{ $label }}: {{ $value }}
@endforeach

@foreach ($comments as $label => $comment)
{{ $label }}: {{ $comment }}
@endforeach

Abrir solicitud: {{ $requestUrl }}
@endif

Nota de seguridad:
{{ $securityNote }}

Este correo fue generado automaticamente por {{ $appName }}.
