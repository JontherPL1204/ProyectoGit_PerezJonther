@extends('layouts.app')

@section('title', 'Calendario')

@section('content')
    <section class="panel calendar-panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Ausencias</p>
                <h2>{{ $monthLabel }}</h2>
            </div>
            <div class="calendar-nav">
                <a class="ghost-button compact" href="{{ route('calendar', ['mes' => $previousMonth]) }}">
                    <i data-lucide="arrow-left"></i>
                    <span>Anterior</span>
                </a>
                <a class="ghost-button compact" href="{{ route('calendar') }}">
                    <i data-lucide="calendar-days"></i>
                    <span>Hoy</span>
                </a>
                <a class="ghost-button compact" href="{{ route('calendar', ['mes' => $nextMonth]) }}">
                    <span>Siguiente</span>
                    <i data-lucide="arrow-right"></i>
                </a>
            </div>
        </div>

        <div class="calendar-legend">
            <span><i class="legend-dot approved"></i>Aprobada</span>
            <span><i class="legend-dot pending"></i>Pendiente</span>
            <span><i class="legend-dot holiday"></i>Festivo</span>
        </div>

        <div class="calendar-grid">
            @foreach ($weekdayLabels as $weekday)
                <div class="calendar-weekday">{{ $weekday }}</div>
            @endforeach

            @foreach ($days as $day)
                <article class="calendar-day {{ $day['is_current_month'] ? '' : 'muted' }}">
                    <div class="calendar-day-number">{{ $day['date']->day }}</div>

                    <div class="calendar-items">
                        @foreach ($day['holidays'] as $holiday)
                            <div class="calendar-item holiday">
                                <span>{{ $holiday->name }}</span>
                            </div>
                        @endforeach

                        @foreach ($day['requests'] as $leaveRequest)
                            <a class="calendar-item {{ strtolower($leaveRequest->status) }}" href="{{ route('leave-requests.show', $leaveRequest->id) }}">
                                <strong>{{ $isAdmin ? $leaveRequest->employee_name : $leaveRequest->leave_type_name }}</strong>
                                <span>{{ $isAdmin ? $leaveRequest->leave_type_name : $leaveRequest->status_label }}</span>
                            </a>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endsection
