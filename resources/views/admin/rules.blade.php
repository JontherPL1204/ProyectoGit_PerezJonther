@extends('layouts.app')

@section('title', 'Configuracion De Reglas')

@section('content')
    @php
        $activeLeaveTypes = $leaveTypes->where('is_active', true)->count();
        $inactiveLeaveTypes = $leaveTypes->count() - $activeLeaveTypes;
        $activeNotificationRules = $notificationRules->where('is_active', true)->count();
        $inactiveNotificationRules = $notificationRules->count() - $activeNotificationRules;
    @endphp

    <section class="rules-layout">
        <article class="panel wide rules-workspace">
            <div class="rules-page-head">
                <div>
                    <p class="eyebrow">Configuracion</p>
                    <h2>Reglas configurables</h2>
                </div>

                <button class="primary-button compact" type="submit" form="rules-update-form">
                    <i data-lucide="save"></i>
                    <span>Guardar reglas</span>
                </button>
            </div>

            <div class="rules-stat-strip" aria-label="Resumen de reglas">
                <div class="rules-stat accent">
                    <span>Vacaciones</span>
                    <strong>{{ $settings->annual_vacation_days }}</strong>
                    <em>dias anuales</em>
                </div>
                <div class="rules-stat">
                    <span>Anticipacion</span>
                    <strong>{{ $settings->vacation_notice_days }}</strong>
                    <em>dias base</em>
                </div>
                <div class="rules-stat">
                    <span>Ausencias</span>
                    <strong>{{ $activeLeaveTypes }}</strong>
                    <em>{{ $inactiveLeaveTypes }} inactivas</em>
                </div>
                <div class="rules-stat">
                    <span>Correos</span>
                    <strong>{{ $activeNotificationRules }}</strong>
                    <em>{{ $inactiveNotificationRules }} inactivos</em>
                </div>
            </div>

            <section class="rules-section">
                <div class="rules-section-head">
                    <div>
                        <p class="eyebrow">Nueva regla</p>
                        <h3>Agregar tipo de ausencia</h3>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.rules.leave-types.store') }}" class="rules-add-form">
                    @csrf
                    <label>
                        <span>Nombre</span>
                        <input type="text" name="name" maxlength="255" placeholder="Ej. Permiso por mudanza">
                    </label>

                    <label>
                        <span>Unidad</span>
                        <select name="unit">
                            <option value="DAYS">Dias</option>
                            <option value="MINUTES">Horas</option>
                        </select>
                    </label>

                    <label>
                        <span>Adjuntos</span>
                        <select name="attachment_requirement">
                            <option value="none">No requiere</option>
                            <option value="optional">Opcional</option>
                            <option value="required">Obligatorio</option>
                        </select>
                    </label>

                    <label>
                        <span>Departamento</span>
                        <select name="department_id">
                            <option value="">Todos</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <button class="primary-button compact" type="submit">
                        <i data-lucide="plus-circle"></i>
                        <span>Agregar regla</span>
                    </button>
                </form>
            </section>

            <form id="rules-update-form" method="POST" action="{{ route('admin.rules.update') }}" class="rules-update-form">
                @csrf

                <section class="rules-section">
                    <div class="rules-section-head">
                        <div>
                            <p class="eyebrow">Base</p>
                            <h3>Politica general</h3>
                        </div>
                    </div>

                    <div class="settings-grid">
                        <label>
                            <span>Dias anuales de vacaciones</span>
                            <input type="number" name="annual_vacation_days" min="0" max="365" value="{{ old('annual_vacation_days', $settings->annual_vacation_days) }}" required>
                        </label>

                        <label>
                            <span>Anticipacion para vacaciones</span>
                            <input type="number" name="vacation_notice_days" min="0" max="365" value="{{ old('vacation_notice_days', $settings->vacation_notice_days) }}" required>
                        </label>

                        <label>
                            <span>Conservacion medica</span>
                            <select name="medical_documents_retention_policy">
                                <option value="retain" @selected(old('medical_documents_retention_policy', $settings->medical_documents_retention_policy) === 'retain')>Conservar sin fecha limite</option>
                                <option value="days" @selected(old('medical_documents_retention_policy', $settings->medical_documents_retention_policy) === 'days')>Conservar por cantidad de dias</option>
                            </select>
                        </label>

                        <label>
                            <span>Dias de conservacion</span>
                            <input type="number" name="medical_documents_retention_days" min="1" max="3650" value="{{ old('medical_documents_retention_days', $settings->medical_documents_retention_days) }}">
                        </label>
                    </div>

                    <div class="rules-switch-board">
                        <label class="check-row"><input type="checkbox" name="pending_requests_reserve_balance" value="1" @checked(old('pending_requests_reserve_balance', $settings->pending_requests_reserve_balance))><span>Reservar saldo pendiente</span></label>
                        <label class="check-row"><input type="checkbox" name="allow_negative_balance" value="1" @checked(old('allow_negative_balance', $settings->allow_negative_balance))><span>Permitir saldo negativo</span></label>
                        <label class="check-row"><input type="checkbox" name="admin_can_view_medical_attachments" value="1" @checked(old('admin_can_view_medical_attachments', $settings->admin_can_view_medical_attachments))><span>Ver justificantes medicos</span></label>
                        <label class="check-row"><input type="checkbox" name="medical_attachment_audit_required" value="1" @checked(old('medical_attachment_audit_required', $settings->medical_attachment_audit_required))><span>Auditar accesos medicos</span></label>
                        <label class="check-row"><input type="checkbox" name="approved_request_requires_cancel_flow" value="1" @checked(old('approved_request_requires_cancel_flow', $settings->approved_request_requires_cancel_flow))><span>Revisar cancelaciones</span></label>
                        <label class="check-row"><input type="checkbox" name="prorate_vacations" value="1" @checked(old('prorate_vacations', $settings->prorate_vacations))><span>Prorrateo activo</span></label>
                        <label class="check-row"><input type="checkbox" name="carry_over_unused_balance" value="1" @checked(old('carry_over_unused_balance', $settings->carry_over_unused_balance))><span>Traspaso activo</span></label>
                    </div>
                </section>

                <section class="rules-section">
                    <div class="rules-section-head">
                        <div>
                            <p class="eyebrow">Tipos de ausencia</p>
                            <h3>Reglas de ausencia</h3>
                        </div>
                    </div>

                    <div class="rule-accordion-list">
                        @foreach ($leaveTypes as $type)
                            @php
                                $typeKey = "leave_types.$type->id";
                                $isVacation = $type->code === 'VACATIONS';
                                $typeStatusValue = (string) old($typeKey.'.is_active', $type->is_active ? '1' : '0');
                                $unitLabel = $type->unit === 'DAYS' ? 'dias' : 'minutos';
                                $requirementLabel = match ($type->attachment_requirement) {
                                    'required' => 'adjunto obligatorio',
                                    'optional' => 'adjunto opcional',
                                    default => 'sin adjunto',
                                };
                                $departmentLabel = $type->department?->name ?? 'todos los departamentos';
                                $approvalLabel = $type->auto_approve ? 'autoaprobable' : $type->approval_level_count.' nivel'.($type->approval_level_count === 1 ? '' : 'es');
                            @endphp

                            <details class="rule-card rule-accordion" @if ($loop->first || $typeStatusValue === '0') open @endif>
                                <summary class="rule-accordion-summary">
                                    <span class="status {{ $typeStatusValue === '1' ? 'status-approved' : 'status-cancelled' }}">
                                        {{ $typeStatusValue === '1' ? 'Activa' : 'Inactiva' }}
                                    </span>
                                    <span class="rule-summary-main">
                                        <strong>{{ $type->name }}</strong>
                                        <span>{{ $unitLabel }} · {{ $departmentLabel }} · {{ $requirementLabel }}</span>
                                    </span>
                                    <span class="rule-summary-pill">{{ $approvalLabel }}</span>
                                    <span class="rule-chevron" aria-hidden="true">
                                        <i data-lucide="chevron-down"></i>
                                    </span>
                                </summary>

                                <div class="rule-card-body">
                                    <div class="rule-card-toolbar">
                                        <label class="rule-status-field">
                                            <span>Estado</span>
                                            <select
                                                class="status-select {{ $typeStatusValue === '1' ? 'status-approved' : 'status-cancelled' }}"
                                                name="leave_types[{{ $type->id }}][is_active]"
                                                aria-label="Estado de {{ $type->name }}"
                                                data-status-select
                                            >
                                                <option value="1" @selected($typeStatusValue === '1')>Activa</option>
                                                <option value="0" @selected($typeStatusValue === '0')>Inactiva</option>
                                            </select>
                                        </label>

                                        @if (! $type->is_system)
                                            <button class="danger-button compact" type="submit" form="delete-leave-type-{{ $type->id }}">
                                                <i data-lucide="trash-2"></i>
                                                <span>Eliminar</span>
                                            </button>
                                        @else
                                            <span class="status">Base</span>
                                        @endif
                                    </div>

                                    <div class="rule-subsection">
                                        <div class="rule-subsection-head">
                                            <p class="eyebrow">Identidad</p>
                                            <h4>Nombre y visibilidad</h4>
                                        </div>

                                        <div class="rule-form-grid">
                                            <label>
                                                <span>Nombre</span>
                                                <input type="text" name="leave_types[{{ $type->id }}][name]" value="{{ old($typeKey.'.name', $type->name) }}" required>
                                            </label>

                                            <label>
                                                <span>Departamento</span>
                                                <select name="leave_types[{{ $type->id }}][department_id]">
                                                    <option value="">Todos</option>
                                                    @foreach ($departments as $department)
                                                        <option value="{{ $department->id }}" @selected((string) old($typeKey.'.department_id', $type->department_id) === (string) $department->id)>{{ $department->name }}</option>
                                                    @endforeach
                                                </select>
                                            </label>

                                            <label class="check-row rule-check">
                                                <input type="checkbox" name="leave_types[{{ $type->id }}][visible_to_employees]" value="1" @checked(old($typeKey.'.visible_to_employees', $type->visible_to_employees))>
                                                <span>Visible al empleado</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="rule-subsection">
                                        <div class="rule-subsection-head">
                                            <p class="eyebrow">Limites</p>
                                            <h4>Tiempo permitido</h4>
                                        </div>

                                        <div class="rule-form-grid">
                                            <label>
                                                <span>Anticipacion</span>
                                                <input type="number" name="leave_types[{{ $type->id }}][notice_value]" min="0" max="365" value="{{ old($typeKey.'.notice_value', $isVacation ? $settings->vacation_notice_days : $type->notice_value) }}" required>
                                            </label>

                                            <label>
                                                <span>Minimo</span>
                                                <input type="number" name="leave_types[{{ $type->id }}][min_units]" min="0" max="3650" value="{{ old($typeKey.'.min_units', $type->min_units) }}">
                                            </label>

                                            <label>
                                                <span>Maximo</span>
                                                <input type="number" name="leave_types[{{ $type->id }}][max_units]" min="0" max="100000" value="{{ old($typeKey.'.max_units', $isVacation ? $settings->annual_vacation_days : $type->max_units) }}">
                                            </label>

                                            <label>
                                                <span>Tope mensual</span>
                                                <input type="number" name="leave_types[{{ $type->id }}][monthly_limit_units]" min="0" max="100000" value="{{ old($typeKey.'.monthly_limit_units', $type->monthly_limit_units) }}">
                                            </label>

                                            <label>
                                                <span>Tope anual</span>
                                                <input type="number" name="leave_types[{{ $type->id }}][yearly_limit_units]" min="0" max="100000" value="{{ old($typeKey.'.yearly_limit_units', $type->yearly_limit_units) }}">
                                            </label>
                                        </div>
                                    </div>

                                    <div class="rule-subsection">
                                        <div class="rule-subsection-head">
                                            <p class="eyebrow">Aprobacion</p>
                                            <h4>Revision y documentos</h4>
                                        </div>

                                        <div class="rule-form-grid">
                                            <label>
                                                <span>Adjuntos</span>
                                                <select name="leave_types[{{ $type->id }}][attachment_requirement]" required>
                                                    <option value="none" @selected(old($typeKey.'.attachment_requirement', $type->attachment_requirement) === 'none')>No requiere</option>
                                                    <option value="optional" @selected(old($typeKey.'.attachment_requirement', $type->attachment_requirement) === 'optional')>Opcional</option>
                                                    <option value="required" @selected(old($typeKey.'.attachment_requirement', $type->attachment_requirement) === 'required')>Obligatorio</option>
                                                </select>
                                            </label>

                                            <label>
                                                <span>Aprobaciones</span>
                                                <select name="leave_types[{{ $type->id }}][approval_level_count]">
                                                    @for ($level = 1; $level <= 3; $level++)
                                                        <option value="{{ $level }}" @selected((int) old($typeKey.'.approval_level_count', $type->approval_level_count) === $level)>{{ $level }} nivel{{ $level === 1 ? '' : 'es' }}</option>
                                                    @endfor
                                                </select>
                                            </label>

                                            <div class="rule-switches compact-switches">
                                                <label class="check-row"><input type="checkbox" name="leave_types[{{ $type->id }}][requires_approval]" value="1" @checked(old($typeKey.'.requires_approval', $type->requires_approval))><span>Requiere aprobacion</span></label>
                                                <label class="check-row"><input type="checkbox" name="leave_types[{{ $type->id }}][auto_approve]" value="1" @checked(old($typeKey.'.auto_approve', $type->auto_approve))><span>Autoaprobar</span></label>
                                                <label class="check-row"><input type="checkbox" name="leave_types[{{ $type->id }}][allow_half_day]" value="1" @checked(old($typeKey.'.allow_half_day', $type->allow_half_day))><span>Permite medio dia</span></label>
                                                <label class="check-row"><input type="checkbox" name="leave_types[{{ $type->id }}][allow_retroactive]" value="1" @checked(old($typeKey.'.allow_retroactive', $type->allow_retroactive))><span>Permite fechas pasadas</span></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </details>
                        @endforeach
                    </div>
                </section>

                <section class="rules-section">
                    <div class="rules-section-head">
                        <div>
                            <p class="eyebrow">Correos</p>
                            <h3>Reglas de notificacion</h3>
                        </div>
                    </div>

                    <div class="notification-rule-list">
                        @foreach ($notificationRules as $rule)
                            @php
                                $ruleKey = "notification_rules.$rule->id";
                                $ruleStatusValue = (string) old($ruleKey.'.is_active', $rule->is_active ? '1' : '0');
                                $eventLabel = \App\Support\NotificationLabels::event($rule->event);
                                $recipientLabel = $rule->recipient_type === 'admin' ? 'Administradores' : 'Empleado solicitante';
                            @endphp

                            <div class="notification-rule-row">
                                <div class="notification-rule-main">
                                    <strong>{{ $eventLabel }}</strong>
                                    <span>{{ $recipientLabel }} · {{ $rule->subject_template }}</span>
                                </div>

                                <label class="notification-status-field">
                                    <span>Estado</span>
                                    <select
                                        class="status-select {{ $ruleStatusValue === '1' ? 'status-approved' : 'status-cancelled' }}"
                                        name="notification_rules[{{ $rule->id }}][is_active]"
                                        aria-label="Estado del correo {{ $eventLabel }}"
                                        data-status-select
                                    >
                                        <option value="1" @selected($ruleStatusValue === '1')>Activa</option>
                                        <option value="0" @selected($ruleStatusValue === '0')>Inactiva</option>
                                    </select>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="rules-section">
                    <div class="rules-section-head">
                        <div>
                            <p class="eyebrow">Auditoria</p>
                            <h3>Comentario de cambio sensible</h3>
                        </div>
                    </div>

                    <label>
                        <span>Comentario</span>
                        <textarea name="change_comment" rows="3" maxlength="1000" placeholder="Ej. Ajuste aprobado por direccion">{{ old('change_comment') }}</textarea>
                    </label>
                </section>

                <div class="form-actions">
                    <button class="primary-button" type="submit">
                        <i data-lucide="save"></i>
                        <span>Guardar reglas</span>
                    </button>
                </div>
            </form>

            @foreach ($leaveTypes as $type)
                @if (! $type->is_system)
                    <form
                        id="delete-leave-type-{{ $type->id }}"
                        method="POST"
                        action="{{ route('admin.rules.leave-types.destroy', $type->id) }}"
                        data-confirm="Eliminar esta regla solo sera posible si no tiene solicitudes asociadas."
                    >
                        @csrf
                    </form>
                @endif
            @endforeach
        </article>

        <aside class="rules-aside">
            <article class="panel rules-summary-panel">
                <p class="eyebrow">Estado actual</p>
                <h2>Ausencias</h2>
                <div class="rules-summary-list">
                    @foreach ($leaveTypes as $type)
                        <div>
                            <strong>{{ $type->name }}</strong>
                            <span>{{ $type->is_active ? 'Activa' : 'Inactiva' }} · {{ $type->visible_to_employees ? 'visible' : 'oculta' }}</span>
                            <span>{{ $type->department?->name ?? 'Todos' }} · {{ $type->auto_approve ? 'auto' : $type->approval_level_count.' nivel(es)' }}</span>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="panel rules-summary-panel">
                <p class="eyebrow">Estado actual</p>
                <h2>Correos</h2>
                <div class="rules-summary-list">
                    @foreach ($notificationRules as $rule)
                        <div>
                            <strong>{{ \App\Support\NotificationLabels::event($rule->event) }}</strong>
                            <span>{{ $rule->is_active ? 'Activa' : 'Inactiva' }} · {{ $rule->recipient_type === 'admin' ? 'administradores' : 'empleado' }}</span>
                        </div>
                    @endforeach
                </div>
            </article>
        </aside>
    </section>
@endsection
