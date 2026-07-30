@extends('layouts.app')

@section('title', 'Correos')

@section('content')
    <section class="panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Notificaciones</p>
                <h2>Correos del sistema</h2>
            </div>
        </div>

        <nav class="filter-tabs" aria-label="Filtrar correos">
            @foreach ($statusOptions as $key => $label)
                <a class="{{ $currentStatus === $key ? 'active' : '' }}" href="{{ route('admin.notifications.index', array_merge(request()->except('estado'), ['estado' => $key])) }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <form class="admin-filter-form notifications-filter-form" method="GET" action="{{ route('admin.notifications.index') }}">
            <input type="hidden" name="estado" value="{{ $currentStatus }}">

            <label>
                <span>Evento</span>
                <select name="evento">
                    <option value="">Todos</option>
                    @foreach ($events as $event)
                        <option value="{{ $event }}" @selected($currentEvent === $event)>{{ $event }}</option>
                    @endforeach
                </select>
            </label>

            <div class="filter-actions">
                <button class="primary-button compact" type="submit">
                    <i data-lucide="search"></i>
                    <span>Filtrar</span>
                </button>
                <a class="ghost-button compact" href="{{ route('admin.notifications.index') }}">
                    <i data-lucide="rotate-ccw"></i>
                    <span>Limpiar</span>
                </a>
            </div>
        </form>

        <div class="admin-list">
            @forelse ($notifications as $notification)
                @php
                    $statusClass = match ($notification->status) {
                        'sent' => 'approved',
                        'failed' => 'rejected',
                        default => 'pending',
                    };
                @endphp
                <article class="admin-request notification-row">
                    <div>
                        <span class="status status-{{ $statusClass }}">{{ $notification->statusLabel() }}</span>
                        <h3>{{ $notification->eventLabel() }}</h3>
                        <p>{{ $notification->recipient_email }} &middot; {{ $notification->created_at->format('d/m/Y H:i') }}</p>
                        <p>{{ $notification->subject }}</p>
                        @if ($notification->leaveRequest)
                            <p>
                                {{ $notification->leaveRequest->employeeProfile->user->name }}
                                &middot;
                                {{ $notification->leaveRequest->leaveType->name }}
                            </p>
                        @endif
                        @if ($notification->last_error)
                            <div class="overlap-alert">
                                <strong>Ultimo error</strong>
                                <span>{{ $notification->last_error }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="admin-actions readonly">
                        <form method="POST" action="{{ route('admin.notifications.resend', $notification) }}">
                            @csrf
                            <button class="ghost-button compact" type="submit">
                                <i data-lucide="mail"></i>
                                <span>Reenviar</span>
                            </button>
                        </form>
                        @if ($notification->leaveRequest)
                            <a class="ghost-button compact" href="{{ route('leave-requests.show', $notification->leaveRequest) }}">
                                <i data-lucide="eye"></i>
                                <span>Solicitud</span>
                            </a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="empty-state">
                    <i data-lucide="mail"></i>
                    <p>No hay correos con esos filtros.</p>
                </div>
            @endforelse
        </div>

        <div class="pagination-wrap">
            {{ $notifications->links() }}
        </div>
    </section>
@endsection
