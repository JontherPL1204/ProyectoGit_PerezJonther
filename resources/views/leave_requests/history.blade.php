@extends('layouts.app')

@section('title', 'Historial')

@section('content')
    <section class="panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Solicitudes</p>
                <h2>Historial completo</h2>
            </div>
            <a class="primary-button compact" href="{{ route('leave-requests.create') }}">
                <i data-lucide="plus"></i>
                <span>Nueva</span>
            </a>
        </div>

        <form class="admin-filter-form history-filter-form" method="GET" action="{{ route('history') }}">
            <label>
                <span>Buscar</span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Motivo, empleado o comentario">
            </label>

            @if (auth()->user()->isAdmin())
                <label>
                    <span>Empleado</span>
                    <select name="empleado">
                        <option value="">Todos</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected($filters['employee_profile_id'] === $employee->id)>{{ $employee->user?->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label>
                <span>Estado</span>
                <select name="estado">
                    <option value="">Todos</option>
                    @foreach ($statusOptions as $status => $label)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Tipo</span>
                <select name="tipo">
                    <option value="">Todos</option>
                    @foreach ($leaveTypes as $type)
                        <option value="{{ $type->id }}" @selected($filters['leave_type_id'] === $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Desde</span>
                <input type="date" name="desde" value="{{ $filters['date_from'] }}">
            </label>

            <label>
                <span>Hasta</span>
                <input type="date" name="hasta" value="{{ $filters['date_to'] }}">
            </label>

            <div class="filter-actions">
                <button class="primary-button compact" type="submit">
                    <i data-lucide="search"></i>
                    <span>Filtrar</span>
                </button>
                <a class="ghost-button compact" href="{{ route('history') }}">
                    <i data-lucide="rotate-ccw"></i>
                    <span>Limpiar</span>
                </a>
            </div>
        </form>

        <div class="request-list">
            @forelse ($requests as $leaveRequest)
                <a class="request-row" href="{{ route('leave-requests.show', $leaveRequest->id) }}">
                    <div>
                        <strong>{{ $leaveRequest->leave_type_name }}</strong>
                        <span>
                            {{ $leaveRequest->employee_name }}
                            &middot;
                            {{ $leaveRequest->start_date->format('d/m/Y') }} - {{ $leaveRequest->end_date->format('d/m/Y') }}
                        </span>
                        @if ($leaveRequest->user_comment || $leaveRequest->admin_comment)
                            <span>{{ $leaveRequest->user_comment ?: $leaveRequest->admin_comment }}</span>
                        @endif
                    </div>
                    <div class="row-meta">
                        <span class="status status-{{ strtolower($leaveRequest->status) }}">{{ $leaveRequest->status_label }}</span>
                        <span>{{ $leaveRequest->requested_units }} {{ $leaveRequest->unit === 'DAYS' ? 'dias' : 'min' }}</span>
                    </div>
                </a>
            @empty
                <div class="empty-state">
                    <i data-lucide="search"></i>
                    <p>No hay solicitudes con esos filtros.</p>
                </div>
            @endforelse
        </div>

        <div class="pagination-wrap">
            {{ $requests->links() }}
        </div>
    </section>
@endsection
