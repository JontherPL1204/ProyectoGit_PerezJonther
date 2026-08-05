@extends('layouts.app')

@section('title', 'Inicio')

@section('content')
    <section class="metric-grid">
        <article class="metric-card accent">
            <p>Saldo disponible</p>
            <strong>{{ $vacationBalance }}</strong>
            <span>dias de vacaciones</span>
        </article>
        <article class="metric-card">
            <p>Consumido</p>
            <strong>{{ $vacationAllowance ? max(0, $vacationAllowance->assigned_units - $vacationBalance) : 0 }}</strong>
            <span>dias aprobados</span>
        </article>
        <article class="metric-card">
            <p>Proxima ausencia</p>
            <strong>{{ $nextApproved ? $nextApproved->start_date->format('d/m') : '--' }}</strong>
            <span>{{ $nextApproved?->leave_type_name ?? 'sin ausencias aprobadas' }}</span>
        </article>
        @if (auth()->user()->isAdmin())
            <article class="metric-card dark">
                <p>Pendientes</p>
                <strong>{{ $pendingCount }}</strong>
                <span>requieren decision</span>
            </article>
        @endif
    </section>

    @if (auth()->user()->isAdmin())
        <section class="panel">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Revision</p>
                    <h2>Solicitudes pendientes</h2>
                </div>
                <a class="primary-button compact" href="{{ route('admin.dashboard') }}">
                    <i data-lucide="inbox"></i>
                    <span>Abrir</span>
                </a>
            </div>

            <div class="request-list">
                @forelse ($pendingReviewRequests as $reviewRequest)
                    <a class="request-row" href="{{ route('admin.dashboard') }}">
                        <div>
                            <strong>{{ $reviewRequest->employee_name }}</strong>
                            <span>{{ $reviewRequest->leave_type_name }} &middot; {{ $reviewRequest->start_date->format('d/m/Y') }} - {{ $reviewRequest->end_date->format('d/m/Y') }}</span>
                        </div>
                        <div class="row-meta">
                            <span class="status status-{{ strtolower($reviewRequest->status) }}">{{ $reviewRequest->status_label }}</span>
                            <span>{{ $reviewRequest->requested_units }} {{ $reviewRequest->unit === 'DAYS' ? 'dias' : 'min' }}</span>
                        </div>
                    </a>
                @empty
                    <div class="empty-state">
                        <i data-lucide="check-circle-2"></i>
                        <p>No hay solicitudes pendientes.</p>
                    </div>
                @endforelse
            </div>
        </section>
    @endif

    <section class="content-grid">
        <article class="panel wide">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Mis solicitudes</p>
                    <h2>Historial reciente</h2>
                </div>
                <a class="primary-button compact" href="{{ route('leave-requests.create') }}">
                    <i data-lucide="plus"></i>
                    <span>Nueva</span>
                </a>
                <a class="ghost-button compact" href="{{ route('history') }}">
                    <i data-lucide="history"></i>
                    <span>Historial</span>
                </a>
            </div>

            <div class="request-list">
                @forelse ($requests as $leaveRequest)
                    @php
                        $canCancelPending = $leaveRequest->status === \App\Models\LeaveRequest::STATUS_PENDING;
                        $canRequestCancellation = $leaveRequest->status === \App\Models\LeaveRequest::STATUS_APPROVED;
                    @endphp
                    <article class="request-row">
                        <div>
                            <strong>{{ $leaveRequest->leave_type_name }}</strong>
                            <span>{{ $leaveRequest->start_date->format('d/m/Y') }} - {{ $leaveRequest->end_date->format('d/m/Y') }}</span>
                        </div>
                        <div class="request-row-side">
                            <div class="row-meta">
                                <span class="status status-{{ strtolower($leaveRequest->status) }}">{{ $leaveRequest->status_label }}</span>
                                <span>{{ $leaveRequest->requested_units }} {{ $leaveRequest->unit === 'DAYS' ? 'dias' : 'min' }}</span>
                            </div>
                            <div class="request-row-actions">
                                <a class="ghost-button compact" href="{{ route('leave-requests.show', $leaveRequest->id) }}">
                                    <i data-lucide="eye"></i>
                                    <span>Ver detalle</span>
                                </a>

                                @if ($canCancelPending)
                                    <form method="POST" action="{{ route('leave-requests.cancel', $leaveRequest->id) }}" data-confirm="Cancelar esta solicitud pendiente.">
                                        @csrf
                                        <button class="danger-button compact" type="submit">
                                            <i data-lucide="x-circle"></i>
                                            <span>Cancelar</span>
                                        </button>
                                    </form>
                                @endif

                                @if ($canRequestCancellation)
                                    <form method="POST" action="{{ route('leave-requests.request-cancellation', $leaveRequest->id) }}" data-confirm="Enviar esta solicitud de cancelacion al administrador.">
                                        @csrf
                                        <button class="ghost-button compact" type="submit">
                                            <i data-lucide="undo-2"></i>
                                            <span>Solicitar cancelacion</span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="empty-state">
                        <i data-lucide="calendar-plus"></i>
                        <p>No hay solicitudes todavia.</p>
                    </div>
                @endforelse
            </div>
        </article>

        <article class="panel">
            <p class="eyebrow">Reglas activas</p>
            <h2>Base interna</h2>
            <dl class="rule-list">
                <div><dt>Vacaciones</dt><dd>{{ $settings->annual_vacation_days }} dias anuales</dd></div>
                <div><dt>Anticipacion</dt><dd>{{ $settings->vacation_notice_days }} dias naturales</dd></div>
                <div><dt>Pendientes</dt><dd>{{ $settings->pending_requests_reserve_balance ? 'Reservan saldo' : 'No reservan saldo' }}</dd></div>
                <div><dt>Correo</dt><dd>Canal oficial</dd></div>
            </dl>
        </article>
    </section>
@endsection
