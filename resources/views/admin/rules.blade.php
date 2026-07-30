@extends('layouts.app')

@section('title', 'Configuracion De Reglas')

@section('content')
    <section class="content-grid">
        <article class="panel wide">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Configuracion</p>
                    <h2>Reglas configurables</h2>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.rules.update') }}" class="form-grid">
                @csrf
                <label>
                    <span>Dias de vacaciones</span>
                    <input type="number" name="annual_vacation_days" min="0" max="365" value="{{ old('annual_vacation_days', $settings->annual_vacation_days) }}" required>
                </label>

                <label>
                    <span>Anticipacion vacaciones</span>
                    <input type="number" name="vacation_notice_days" min="0" max="365" value="{{ old('vacation_notice_days', $settings->vacation_notice_days) }}" required>
                </label>

                <label>
                    <span>Conservacion medica</span>
                    <select name="medical_documents_retention_policy">
                        <option value="retain" @selected(old('medical_documents_retention_policy', $settings->medical_documents_retention_policy) === 'retain')>Se conserva</option>
                        <option value="days" @selected(old('medical_documents_retention_policy', $settings->medical_documents_retention_policy) === 'days')>Por dias</option>
                    </select>
                </label>

                <label>
                    <span>Dias conservacion</span>
                    <input type="number" name="medical_documents_retention_days" min="1" max="3650" value="{{ old('medical_documents_retention_days', $settings->medical_documents_retention_days) }}">
                </label>

                <div class="toggle-grid full">
                    <label class="check-row"><input type="checkbox" name="pending_requests_reserve_balance" value="1" @checked(old('pending_requests_reserve_balance', $settings->pending_requests_reserve_balance))><span>Pendientes reservan saldo</span></label>
                    <label class="check-row"><input type="checkbox" name="allow_negative_balance" value="1" @checked(old('allow_negative_balance', $settings->allow_negative_balance))><span>Permitir saldo negativo</span></label>
                    <label class="check-row"><input type="checkbox" name="admin_can_view_medical_attachments" value="1" @checked(old('admin_can_view_medical_attachments', $settings->admin_can_view_medical_attachments))><span>Responsable ve documentos medicos</span></label>
                    <label class="check-row"><input type="checkbox" name="medical_attachment_audit_required" value="1" @checked(old('medical_attachment_audit_required', $settings->medical_attachment_audit_required))><span>Auditar documentos medicos</span></label>
                    <label class="check-row"><input type="checkbox" name="approved_request_requires_cancel_flow" value="1" @checked(old('approved_request_requires_cancel_flow', $settings->approved_request_requires_cancel_flow))><span>Aprobadas usan flujo de cancelacion</span></label>
                    <label class="check-row"><input type="checkbox" name="prorate_vacations" value="1" @checked(old('prorate_vacations', $settings->prorate_vacations))><span>Prorrateo activo</span></label>
                    <label class="check-row"><input type="checkbox" name="carry_over_unused_balance" value="1" @checked(old('carry_over_unused_balance', $settings->carry_over_unused_balance))><span>Traspaso activo</span></label>
                </div>

                <div class="rule-editor full">
                    <div class="section-head">
                        <div>
                            <p class="eyebrow">Tipos de ausencia</p>
                            <h2>Reglas activas e inactivas</h2>
                        </div>
                    </div>

                    @foreach ($leaveTypes as $type)
                        @php
                            $typeKey = "leave_types.$type->id";
                            $isVacation = $type->code === 'VACATIONS';
                        @endphp
                        <fieldset class="rule-card">
                            <legend>
                                <span>{{ $type->name }}</span>
                                <strong class="status {{ $type->is_active ? 'status-approved' : 'status-cancelled' }}">{{ $type->is_active ? 'Activa' : 'Inactiva' }}</strong>
                            </legend>

                            <div class="rule-form-grid">
                                <label>
                                    <span>Nombre</span>
                                    <input type="text" name="leave_types[{{ $type->id }}][name]" value="{{ old($typeKey.'.name', $type->name) }}" required>
                                </label>

                                <label>
                                    <span>Adjuntos</span>
                                    <select name="leave_types[{{ $type->id }}][attachment_requirement]" required>
                                        <option value="none" @selected(old($typeKey.'.attachment_requirement', $type->attachment_requirement) === 'none')>No requiere</option>
                                        <option value="optional" @selected(old($typeKey.'.attachment_requirement', $type->attachment_requirement) === 'optional')>Opcional</option>
                                        <option value="required" @selected(old($typeKey.'.attachment_requirement', $type->attachment_requirement) === 'required')>Obligatorio</option>
                                    </select>
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
                                    <span>Limite mensual</span>
                                    <input type="number" name="leave_types[{{ $type->id }}][monthly_limit_units]" min="0" max="100000" value="{{ old($typeKey.'.monthly_limit_units', $type->monthly_limit_units) }}">
                                </label>

                                <label>
                                    <span>Limite anual</span>
                                    <input type="number" name="leave_types[{{ $type->id }}][yearly_limit_units]" min="0" max="100000" value="{{ old($typeKey.'.yearly_limit_units', $type->yearly_limit_units) }}">
                                </label>

                                <label>
                                    <span>Niveles aprobacion</span>
                                    <input type="number" name="leave_types[{{ $type->id }}][approval_level_count]" min="1" max="3" value="{{ old($typeKey.'.approval_level_count', $type->approval_level_count) }}" required>
                                </label>

                                <div class="rule-switches">
                                    <label class="check-row"><input type="checkbox" name="leave_types[{{ $type->id }}][is_active]" value="1" @checked(old($typeKey.'.is_active', $type->is_active))><span>Regla activa</span></label>
                                    <label class="check-row"><input type="checkbox" name="leave_types[{{ $type->id }}][visible_to_employees]" value="1" @checked(old($typeKey.'.visible_to_employees', $type->visible_to_employees))><span>Visible al empleado</span></label>
                                    <label class="check-row"><input type="checkbox" name="leave_types[{{ $type->id }}][requires_approval]" value="1" @checked(old($typeKey.'.requires_approval', $type->requires_approval))><span>Requiere aprobacion</span></label>
                                    <label class="check-row"><input type="checkbox" name="leave_types[{{ $type->id }}][auto_approve]" value="1" @checked(old($typeKey.'.auto_approve', $type->auto_approve))><span>Autoaprobar</span></label>
                                    <label class="check-row"><input type="checkbox" name="leave_types[{{ $type->id }}][allow_half_day]" value="1" @checked(old($typeKey.'.allow_half_day', $type->allow_half_day))><span>Permite medio dia</span></label>
                                    <label class="check-row"><input type="checkbox" name="leave_types[{{ $type->id }}][allow_retroactive]" value="1" @checked(old($typeKey.'.allow_retroactive', $type->allow_retroactive))><span>Permite retroactivo</span></label>
                                </div>
                            </div>
                        </fieldset>
                    @endforeach
                </div>

                <label class="full">
                    <span>Comentario de cambio sensible</span>
                    <textarea name="change_comment" rows="3" maxlength="1000">{{ old('change_comment') }}</textarea>
                </label>

                <div class="form-actions full">
                    <button class="primary-button" type="submit">
                        <i data-lucide="save"></i>
                        <span>Guardar reglas</span>
                    </button>
                </div>
            </form>
        </article>

        <article class="panel">
            <p class="eyebrow">Tipos</p>
            <h2>Estado actual</h2>
            <div class="mini-list">
                @foreach ($leaveTypes as $type)
                    <div>
                        <strong>{{ $type->name }}</strong>
                        <span>{{ $type->is_active ? 'Activa' : 'Inactiva' }} &middot; {{ $type->visible_to_employees ? 'visible' : 'oculta' }} &middot; {{ $type->department?->name ?? 'todos' }} &middot; {{ $type->auto_approve ? 'auto' : $type->approval_level_count.' nivel(es)' }}</span>
                    </div>
                @endforeach
            </div>
        </article>
    </section>
@endsection
