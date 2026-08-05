@extends('layouts.app')

@section('title', 'Detalle')

@section('content')
    <section class="content-grid">
        <article class="panel wide">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Solicitud #{{ $leaveRequest->id }}</p>
                    <h2>{{ $leaveRequest->leaveType->name }}</h2>
                </div>
                <span class="status status-{{ strtolower($leaveRequest->status) }}">{{ $leaveRequest->statusLabel() }}</span>
            </div>

            <dl class="detail-grid">
                <div><dt>Empleado</dt><dd>{{ $leaveRequest->employeeProfile->user->name }}</dd></div>
                <div><dt>Periodo</dt><dd>{{ $leaveRequest->start_date->format('d/m/Y') }} - {{ $leaveRequest->end_date->format('d/m/Y') }}</dd></div>
                <div><dt>Duracion</dt><dd>{{ $leaveRequest->requested_units }} {{ $leaveRequest->unit === 'DAYS' ? 'dias' : 'minutos' }}</dd></div>
            </dl>

            @if ($leaveRequest->user_comment)
                <div class="comment-box">
                    <strong>Comentario del empleado</strong>
                    <p>{{ $leaveRequest->user_comment }}</p>
                </div>
            @endif

            @if ($leaveRequest->admin_comment)
                <div class="comment-box">
                    <strong>Comentario de revision</strong>
                    <p>{{ $leaveRequest->admin_comment }}</p>
                </div>
            @endif

            <div class="form-actions">
                <a class="ghost-button" href="{{ route('dashboard') }}">
                    <i data-lucide="arrow-left"></i>
                    <span>Inicio</span>
                </a>

                @if ($leaveRequest->status === \App\Models\LeaveRequest::STATUS_PENDING && $leaveRequest->employee_profile_id === auth()->user()->employeeProfile?->id)
                    <form method="POST" action="{{ route('leave-requests.cancel', $leaveRequest) }}">
                        @csrf
                        <button class="danger-button" type="submit">
                            <i data-lucide="x-circle"></i>
                            <span>Cancelar</span>
                        </button>
                    </form>
                @endif

                @if ($leaveRequest->status === \App\Models\LeaveRequest::STATUS_APPROVED && $leaveRequest->employee_profile_id === auth()->user()->employeeProfile?->id)
                    <form method="POST" action="{{ route('leave-requests.request-cancellation', $leaveRequest) }}">
                        @csrf
                        <button class="ghost-button" type="submit">
                            <i data-lucide="undo-2"></i>
                            <span>Solicitar cancelacion</span>
                        </button>
                    </form>
                @endif
            </div>
        </article>

        <article class="panel">
            <p class="eyebrow">Calculo</p>
            <h2>Detalle computable</h2>
            <div class="mini-list">
                @foreach ($leaveRequest->calculationDays as $day)
                    <div>
                        <strong>{{ $day->work_date->format('d/m/Y') }}</strong>
                        <span>{{ $day->computed_units }} {{ $leaveRequest->unit === 'DAYS' ? 'dia' : 'min' }} - {{ $day->note }}</span>
                    </div>
                @endforeach
            </div>
        </article>

        @if ($leaveRequest->relationLoaded('approvalSteps') && $leaveRequest->approvalSteps->isNotEmpty())
            <article class="panel">
                <p class="eyebrow">Revision</p>
                <h2>Aprobaciones</h2>
                <div class="mini-list approval-steps">
                    @foreach ($leaveRequest->approvalSteps as $step)
                        <div>
                            <strong>Nivel {{ $step->level }} - {{ $step->statusLabel() }}</strong>
                            <span>
                                @if ($step->decidedBy)
                                    {{ $step->decidedBy->name }} &middot; {{ auth()->user()->formatDateTime($step->decided_at) }}
                                @else
                                    Pendiente de revision
                                @endif
                            </span>
                            @if ($step->comment)
                                <span>{{ $step->comment }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </article>
        @endif

        <article class="panel">
            <p class="eyebrow">Adjuntos</p>
            <h2>Archivos privados</h2>
            <div class="mini-list">
                @forelse ($leaveRequest->attachments as $attachment)
                    @php
                        $isOwner = $leaveRequest->employee_profile_id === auth()->user()->employeeProfile?->id;
                        $canSeeMedical = ! $attachment->is_medical || $isOwner || auth()->user()->canViewMedicalAttachments();
                    @endphp
                    @if ($canSeeMedical)
                        <div>
                            <strong>{{ $attachment->original_name }}</strong>
                            <span>{{ strtoupper(pathinfo($attachment->original_name, PATHINFO_EXTENSION)) }} &middot; {{ number_format($attachment->size_bytes / 1024, 1) }} KB &middot; {{ $attachment->justificationLabel() }}</span>
                            <div class="inline-actions">
                                @if (in_array($attachment->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true))
                                    <a class="ghost-button compact" href="{{ route('attachments.preview', $attachment) }}" target="_blank" rel="noopener">
                                        <i data-lucide="eye"></i>
                                        <span>Vista previa</span>
                                    </a>
                                @endif
                                <a class="ghost-button compact" href="{{ route('attachments.download', $attachment) }}">
                                    <i data-lucide="download"></i>
                                    <span>Descargar</span>
                                </a>
                                @if (auth()->user()->isAdmin() && $attachment->justification_status !== 'reviewed')
                                    <form method="POST" action="{{ route('attachments.review', $attachment) }}">
                                        @csrf
                                        <button class="ghost-button compact" type="submit">
                                            <i data-lucide="file-check-2"></i>
                                            <span>Validar justificante</span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endif
                @empty
                    <div>
                        @if ($leaveRequest->leaveType->attachment_requirement === 'required')
                            <strong>Pendiente de justificar</strong>
                            <span>Este motivo requiere justificante y aun no tiene archivo cargado.</span>
                        @else
                            <strong>Sin adjuntos</strong>
                            <span>No se cargaron archivos para esta solicitud.</span>
                        @endif
                    </div>
                @endforelse
            </div>
        </article>

        <article class="panel wide">
            <p class="eyebrow">Auditoria</p>
            <h2>Linea de tiempo</h2>
            <div class="timeline">
                @foreach ($leaveRequest->events as $event)
                    <div>
                        <span>{{ auth()->user()->formatDateTime($event->created_at) }}</span>
                        <strong>{{ $event->actionLabel() }}</strong>
                        <p>{{ $event->actor?->name ?? 'Sistema' }} {{ $event->comment ? '- '.$event->comment : '' }}</p>
                    </div>
                @endforeach
            </div>
        </article>
    </section>
@endsection
