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
            <h2>Ausencias activas</h2>
            <div class="mini-list">
                @foreach ($leaveTypes as $type)
                    <div>
                        <strong>{{ $type->name }}</strong>
                        <span>{{ $type->unit }} &middot; {{ $type->consumes_balance ? 'consume saldo' : 'no consume saldo' }}</span>
                    </div>
                @endforeach
            </div>
        </article>
    </section>
@endsection
