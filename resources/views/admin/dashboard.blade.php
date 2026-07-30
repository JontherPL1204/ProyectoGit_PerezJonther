@extends('layouts.app')

@section('title', 'Revision')

@section('content')
    <section class="metric-grid">
        <article class="metric-card accent"><p>Pendientes</p><strong>{{ $stats['pending'] }}</strong><span>solicitudes</span></article>
        <article class="metric-card"><p>Aprobadas</p><strong>{{ $stats['approved'] }}</strong><span>historicas</span></article>
        <article class="metric-card"><p>Rechazadas</p><strong>{{ $stats['rejected'] }}</strong><span>historicas</span></article>
        <article class="metric-card dark"><p>Canceladas</p><strong>{{ $stats['cancelled'] }}</strong><span>cerradas</span></article>
    </section>

    <section class="panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Bandeja</p>
                <h2>{{ $statusFilters[$currentFilter]['label'] }}</h2>
            </div>
        </div>

        <nav class="filter-tabs" aria-label="Filtrar solicitudes">
            @foreach ($statusFilters as $key => $filter)
                <a class="{{ $currentFilter === $key ? 'active' : '' }}" href="{{ route('admin.dashboard', ['estado' => $key]) }}">
                    {{ $filter['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="admin-list">
            @forelse ($requests as $leaveRequest)
                <article class="admin-request">
                    <div>
                        <span class="status status-{{ strtolower($leaveRequest->status) }}">{{ $leaveRequest->statusLabel() }}</span>
                        <h3>{{ $leaveRequest->employeeProfile->user->name }}</h3>
                        <p>{{ $leaveRequest->leaveType->name }} &middot; {{ $leaveRequest->start_date->format('d/m/Y') }} - {{ $leaveRequest->end_date->format('d/m/Y') }} &middot; {{ $leaveRequest->requested_units }} {{ $leaveRequest->unit === 'DAYS' ? 'dias' : 'min' }}</p>
                    </div>

                    @if ($leaveRequest->status === \App\Models\LeaveRequest::STATUS_PENDING)
                        <div class="admin-actions">
                            <form method="POST" action="{{ route('admin.requests.approve', $leaveRequest) }}">
                                @csrf
                                <input type="text" name="admin_comment" placeholder="Comentario opcional">
                                <button class="primary-button compact" type="submit">
                                    <i data-lucide="check"></i>
                                    <span>Aprobar</span>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.requests.reject', $leaveRequest) }}">
                                @csrf
                                <input type="text" name="admin_comment" placeholder="Motivo obligatorio" required>
                                <button class="danger-button compact" type="submit">
                                    <i data-lucide="x"></i>
                                    <span>Rechazar</span>
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="admin-actions">
                            <form method="POST" action="{{ route('admin.requests.accept-cancellation', $leaveRequest) }}">
                                @csrf
                                <input type="text" name="admin_comment" placeholder="Comentario opcional">
                                <button class="primary-button compact" type="submit">
                                    <i data-lucide="check"></i>
                                    <span>Aceptar</span>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.requests.reject-cancellation', $leaveRequest) }}">
                                @csrf
                                <input type="text" name="admin_comment" placeholder="Motivo obligatorio" required>
                                <button class="danger-button compact" type="submit">
                                    <i data-lucide="x"></i>
                                    <span>Rechazar</span>
                                </button>
                            </form>
                        </div>
                    @endif
                </article>
            @empty
                <div class="empty-state">
                    <i data-lucide="check-circle-2"></i>
                    <p>No hay solicitudes en esta vista.</p>
                </div>
            @endforelse
        </div>
    </section>
@endsection
