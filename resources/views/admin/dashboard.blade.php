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
                <a class="{{ $currentFilter === $key ? 'active' : '' }}" href="{{ route('admin.dashboard', array_merge(request()->except('estado'), ['estado' => $key])) }}">
                    {{ $filter['label'] }}
                </a>
            @endforeach
        </nav>

        <form class="admin-filter-form" method="GET" action="{{ route('admin.dashboard') }}">
            <input type="hidden" name="estado" value="{{ $currentFilter }}">

            <label>
                <span>Empleado</span>
                <select name="empleado">
                    <option value="">Todos</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected($advancedFilters['employee_profile_id'] === $employee->id)>{{ $employee->user?->name }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Tipo</span>
                <select name="tipo">
                    <option value="">Todos</option>
                    @foreach ($leaveTypes as $type)
                        <option value="{{ $type->id }}" @selected($advancedFilters['leave_type_id'] === $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Desde</span>
                <input type="date" name="desde" value="{{ $advancedFilters['date_from'] }}">
            </label>

            <label>
                <span>Hasta</span>
                <input type="date" name="hasta" value="{{ $advancedFilters['date_to'] }}">
            </label>

            <div class="filter-actions">
                <button class="primary-button compact" type="submit">
                    <i data-lucide="search"></i>
                    <span>Filtrar</span>
                </button>
                <a class="ghost-button compact" href="{{ route('admin.dashboard', ['estado' => $currentFilter]) }}">
                    <i data-lucide="rotate-ccw"></i>
                    <span>Limpiar</span>
                </a>
            </div>
        </form>

        <div class="admin-list admin-review-list">
            @forelse ($requests as $leaveRequest)
                <article class="admin-request">
                    <div>
                        <span class="status status-{{ strtolower($leaveRequest->status) }}">{{ $leaveRequest->status_label }}</span>
                        <h3>{{ $leaveRequest->employee_name }}</h3>
                        <p>Solicitada {{ auth()->user()->formatDateTime($leaveRequest->created_at) }}</p>
                        <p>{{ $leaveRequest->leave_type_name }} &middot; {{ $leaveRequest->start_date->format('d/m/Y') }} - {{ $leaveRequest->end_date->format('d/m/Y') }} &middot; {{ $leaveRequest->requested_units }} {{ $leaveRequest->unit === 'DAYS' ? 'dias' : 'min' }}</p>
                        @if (($leaveRequest->approval_total ?? 1) > 1)
                            <div class="approval-progress">
                                <strong>{{ $leaveRequest->approval_summary }}</strong>
                                @foreach ($leaveRequest->approval_steps as $step)
                                    <span>
                                        Nivel {{ $step->level }}:
                                        {{ $step->status === \App\Models\ApprovalStep::STATUS_APPROVED ? 'aprobado' : ($step->status === \App\Models\ApprovalStep::STATUS_REJECTED ? 'rechazado' : 'pendiente') }}
                                        @if ($step->decided_by_name)
                                            por {{ $step->decided_by_name }}
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        @endif
                        @if ($leaveRequest->overlap_warnings->isNotEmpty())
                            <div class="overlap-alert">
                                <strong>Coincide con otras ausencias</strong>
                                @foreach ($leaveRequest->overlap_warnings as $overlap)
                                    <span>{{ $overlap->employee_name }} &middot; {{ $overlap->leave_type_name }} &middot; {{ $overlap->start_date->format('d/m/Y') }} - {{ $overlap->end_date->format('d/m/Y') }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if ($leaveRequest->status === \App\Models\LeaveRequest::STATUS_PENDING)
                        @if ($leaveRequest->can_resolve_current_level)
                            <div class="admin-actions">
                                <form method="POST" action="{{ route('admin.requests.approve', $leaveRequest->id) }}">
                                    @csrf
                                    <input type="text" name="admin_comment" placeholder="Comentario opcional">
                                    <button class="primary-button compact" type="submit">
                                        <i data-lucide="check"></i>
                                        <span>Aprobar</span>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.requests.reject', $leaveRequest->id) }}">
                                    @csrf
                                    <input type="text" name="admin_comment" placeholder="Motivo obligatorio" required>
                                    <button class="danger-button compact" type="submit">
                                        <i data-lucide="x"></i>
                                        <span>Rechazar</span>
                                    </button>
                                </form>
                            </div>
                        @else
                            <div class="admin-actions readonly">
                                <div class="readonly-note">
                                    <strong>Esperando nivel superior</strong>
                                    <span>Este paso requiere permiso para gestionar reglas y equipo.</span>
                                </div>
                                <a class="ghost-button compact" href="{{ route('leave-requests.show', $leaveRequest->id) }}">
                                    <i data-lucide="eye"></i>
                                    <span>Ver detalle</span>
                                </a>
                            </div>
                        @endif
                    @elseif ($leaveRequest->status === \App\Models\LeaveRequest::STATUS_PENDING_CANCELLATION)
                        <div class="admin-actions">
                            <form method="POST" action="{{ route('admin.requests.accept-cancellation', $leaveRequest->id) }}">
                                @csrf
                                <input type="text" name="admin_comment" placeholder="Comentario opcional">
                                <button class="primary-button compact" type="submit">
                                    <i data-lucide="check"></i>
                                    <span>Aceptar</span>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.requests.reject-cancellation', $leaveRequest->id) }}">
                                @csrf
                                <input type="text" name="admin_comment" placeholder="Motivo obligatorio" required>
                                <button class="danger-button compact" type="submit">
                                    <i data-lucide="x"></i>
                                    <span>Rechazar</span>
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="admin-actions readonly">
                            <a class="ghost-button compact" href="{{ route('leave-requests.show', $leaveRequest->id) }}">
                                <i data-lucide="eye"></i>
                                <span>Ver detalle</span>
                            </a>
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

        <div class="pagination-wrap">
            {{ $requests->links() }}
        </div>
    </section>
@endsection
