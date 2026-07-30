# Contrato API inicial

Este contrato describe la API prevista para una fase posterior o para separar frontend/backend. La primera base usa rutas web Laravel con sesiones.

## Autenticacion

- `POST /login`
- `POST /logout`

El navegador usa sesion segura Laravel. La API futura debera mantener CSRF para web o tokens si se crea cliente externo.

## Solicitudes

### Crear solicitud

`POST /api/v1/leave-requests`

Entrada:

```json
{
  "leave_type_id": 1,
  "start_date": "2026-09-07",
  "end_date": "2026-09-11",
  "start_time": null,
  "end_time": null,
  "user_comment": "Vacaciones familiares"
}
```

Respuesta:

```json
{
  "id": 1,
  "status": "PENDING",
  "requested_units": 5,
  "unit": "DAYS"
}
```

### Ver solicitud

`GET /api/v1/leave-requests/{id}`

Incluye empleado, motivo, fechas, estado, calculo por dia, eventos y adjuntos autorizados.

### Cancelar pendiente

`POST /api/v1/leave-requests/{id}/cancel`

Solo el solicitante puede cancelar una solicitud pendiente.

### Solicitar cancelacion de aprobada

`POST /api/v1/leave-requests/{id}/request-cancellation`

Solo el solicitante puede iniciar este flujo.

## Administracion

### Bandeja de pendientes

`GET /api/v1/admin/leave-requests?status=PENDING`

Requiere rol `admin`.

### Aprobar

`POST /api/v1/admin/leave-requests/{id}/approve`

Entrada:

```json
{
  "admin_comment": "Aprobado"
}
```

Debe ejecutar en transaccion:

- cambio de estado;
- movimiento de saldo si aplica;
- auditoria;
- notificacion en outbox.

### Rechazar

`POST /api/v1/admin/leave-requests/{id}/reject`

Entrada:

```json
{
  "admin_comment": "Motivo obligatorio"
}
```

## Reglas

### Consultar reglas

`GET /api/v1/admin/rules`

Requiere permiso `can_manage_company_rules`.

### Actualizar reglas

`PATCH /api/v1/admin/rules`

Campos principales:

- `annual_vacation_days`
- `vacation_notice_days`
- `allow_negative_balance`
- `pending_requests_reserve_balance`
- `admin_can_view_medical_attachments`
- `medical_attachment_audit_required`
- `approved_request_requires_cancel_flow`
- `prorate_vacations`
- `carry_over_unused_balance`
- `medical_documents_retention_policy`
- `medical_documents_retention_days`

Los cambios sensibles requieren `change_comment`.
