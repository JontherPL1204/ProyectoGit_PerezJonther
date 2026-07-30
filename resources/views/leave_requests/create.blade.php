@extends('layouts.app')

@section('title', 'Nueva Solicitud')

@section('content')
    <section class="panel form-panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Solicitud</p>
                <h2>Crear ausencia</h2>
            </div>
        </div>

        <form method="POST" action="{{ route('leave-requests.store') }}" class="form-grid" enctype="multipart/form-data">
            @csrf
            @php
                $typeHelp = [
                    'VACATIONS' => 'Vacaciones descuenta saldo y debe pedirse con 30 dias naturales de anticipacion.',
                    'MEDICAL' => 'Permiso medico puede pedirse por dias completos o por horas. Adjunta soporte si lo tienes.',
                    'PERSONAL' => 'Asuntos personales no descuenta vacaciones y requiere revision de la persona responsable.',
                    'TRAINING' => 'Formacion sirve para cursos o capacitaciones y requiere aprobacion.',
                ];
            @endphp
            <label class="full">
                <span class="field-title">
                    Motivo
                    <span class="field-help" tabindex="0" data-leave-type-help title="">
                        <i data-lucide="info"></i>
                        <span class="tooltip" role="tooltip"></span>
                    </span>
                </span>
                <select name="leave_type_id" required data-leave-type-select>
                    @foreach ($types as $type)
                        @php
                            $hints = [];

                            if ($type->auto_approve || ! $type->requires_approval) {
                                $hints[] = 'Se aprueba automaticamente si cumple las reglas.';
                            } elseif ($type->approval_level_count > 1) {
                                $hints[] = 'Requiere '.$type->approval_level_count.' niveles de aprobacion configurados.';
                            }

                            if ($type->attachment_requirement === 'required') {
                                $hints[] = 'El justificante es obligatorio.';
                            } elseif ($type->attachment_requirement === 'optional') {
                                $hints[] = 'Puedes adjuntar justificante si aplica.';
                            }

                            if ($type->monthly_limit_units) {
                                $hints[] = 'Limite mensual: '.$type->monthly_limit_units.' '.($type->unit === 'DAYS' ? 'dias' : 'minutos').'.';
                            }

                            if ($type->yearly_limit_units) {
                                $hints[] = 'Limite anual: '.$type->yearly_limit_units.' '.($type->unit === 'DAYS' ? 'dias' : 'minutos').'.';
                            }

                            $helpText = trim(($typeHelp[$type->code] ?? 'Este motivo requiere revision de la persona responsable.').' '.implode(' ', $hints));
                        @endphp
                        <option
                            value="{{ $type->id }}"
                            data-code="{{ $type->code }}"
                            data-unit="{{ $type->unit }}"
                            data-is-medical="{{ $type->is_medical ? '1' : '0' }}"
                            data-help="{{ $helpText }}"
                            @selected(old('leave_type_id') == $type->id)
                        >
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <div class="full segmented-field" data-medical-duration-field hidden>
                <span>Como sera el permiso medico</span>
                <div class="segmented-control">
                    <label>
                        <input type="radio" name="duration_unit" value="DAYS" @checked(old('duration_unit', 'DAYS') === 'DAYS')>
                        <span>Por dias</span>
                    </label>
                    <label>
                        <input type="radio" name="duration_unit" value="MINUTES" @checked(old('duration_unit') === 'MINUTES')>
                        <span>Por horas</span>
                    </label>
                </div>
            </div>

            <label data-start-date-field>
                <span data-start-date-label>Fecha inicio</span>
                <input type="date" name="start_date" value="{{ old('start_date') }}" required data-start-date>
            </label>

            <label data-end-date-field>
                <span>Fecha fin</span>
                <input type="date" name="end_date" value="{{ old('end_date') }}" required data-end-date>
            </label>

            <label data-time-field hidden>
                <span>Hora inicio</span>
                <input type="time" name="start_time" value="{{ old('start_time') }}">
            </label>

            <label data-time-field hidden>
                <span>Hora fin</span>
                <input type="time" name="end_time" value="{{ old('end_time') }}">
            </label>

            <label class="full">
                <span>Comentario</span>
                <textarea name="user_comment" rows="4" maxlength="1000">{{ old('user_comment') }}</textarea>
            </label>

            <label class="full">
                <span>Adjuntos privados</span>
                <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf">
            </label>

            <div class="form-note full">
                <i data-lucide="info"></i>
                <span data-request-guidance>Selecciona un motivo para ver como se calcula la solicitud.</span>
            </div>

            <div class="form-actions full">
                <a class="ghost-button" href="{{ route('dashboard') }}">
                    <i data-lucide="arrow-left"></i>
                    <span>Volver</span>
                </a>
                <button class="primary-button" type="submit">
                    <i data-lucide="send"></i>
                    <span>Enviar</span>
                </button>
            </div>
        </form>
    </section>
@endsection
