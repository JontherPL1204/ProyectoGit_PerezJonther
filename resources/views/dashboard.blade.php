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
            <span>{{ $nextApproved?->leaveType?->name ?? 'sin ausencias aprobadas' }}</span>
        </article>
        @if (auth()->user()->isAdmin())
            <article class="metric-card dark">
                <p>Pendientes admin</p>
                <strong>{{ $pendingCount }}</strong>
                <span>requieren decision</span>
            </article>
        @endif
    </section>

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
            </div>

            <div class="request-list">
                @forelse ($requests as $leaveRequest)
                    <a class="request-row" href="{{ route('leave-requests.show', $leaveRequest) }}">
                        <div>
                            <strong>{{ $leaveRequest->leaveType->name }}</strong>
                            <span>{{ $leaveRequest->start_date->format('d/m/Y') }} - {{ $leaveRequest->end_date->format('d/m/Y') }}</span>
                        </div>
                        <div class="row-meta">
                            <span class="status status-{{ strtolower($leaveRequest->status) }}">{{ $leaveRequest->statusLabel() }}</span>
                            <span>{{ $leaveRequest->requested_units }} {{ $leaveRequest->unit === 'DAYS' ? 'dias' : 'min' }}</span>
                        </div>
                    </a>
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
                <div><dt>Vacaciones</dt><dd>15 dias anuales</dd></div>
                <div><dt>Anticipacion</dt><dd>30 dias naturales</dd></div>
                <div><dt>Pendientes</dt><dd>Reservan saldo</dd></div>
                <div><dt>Correo</dt><dd>Canal oficial</dd></div>
            </dl>
        </article>
    </section>
@endsection
