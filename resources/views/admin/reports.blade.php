@extends('layouts.app')

@section('title', 'Reportes')

@section('content')
    @php
        $monthNames = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];
    @endphp

    <div class="report-page">
    <section class="panel report-control-panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Resumen</p>
                <h2>{{ $monthNames[$month] }} {{ $year }}</h2>
            </div>
        </div>

        <form class="admin-filter-form report-filter-form" method="GET" action="{{ route('admin.reports') }}">
            <label>
                <span>Mes</span>
                <select name="mes">
                    @foreach ($monthNames as $number => $label)
                        <option value="{{ $number }}" @selected($month === $number)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Anio</span>
                <input type="number" name="anio" min="2020" max="2100" value="{{ $year }}">
            </label>

            <div class="filter-actions">
                <button class="primary-button compact" type="submit">
                    <i data-lucide="search"></i>
                    <span>Ver</span>
                </button>
            </div>
        </form>
    </section>

    <section class="metric-grid report-metric-grid">
        <article class="metric-card accent">
            <p>Vacaciones usadas</p>
            <strong>{{ $monthlyStats['vacation_used'] }}</strong>
            <span>dias aprobados en el mes</span>
        </article>
        <article class="metric-card">
            <p>Pendientes</p>
            <strong>{{ $monthlyStats['pending'] }}</strong>
            <span>requieren revision</span>
        </article>
        <article class="metric-card">
            <p>Rechazadas</p>
            <strong>{{ $monthlyStats['rejected'] }}</strong>
            <span>en el mes</span>
        </article>
        <article class="metric-card dark">
            <p>Medicas</p>
            <strong>{{ $monthlyStats['medical_count'] }}</strong>
            <span>solicitudes registradas</span>
        </article>
    </section>

    <section class="panel report-vacation-panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Equipo</p>
                <h2>Vacaciones por integrante</h2>
            </div>
            <div class="vacation-balance-key" aria-label="Leyenda de vacaciones">
                <span><b class="key-used"></b>Usados</span>
                <span><b class="key-remaining"></b>Disponibles</span>
            </div>
        </div>

        <div class="vacation-balance-compact">
            @forelse ($vacationBalances as $row)
                <details class="vacation-balance-card">
                    <summary class="vacation-balance-summary">
                        <span class="vacation-balance-name">{{ $row['name'] }}</span>
                        <span class="vacation-balance-mini-chart" aria-label="{{ $row['used'] }} dias usados y {{ $row['remaining'] }} dias disponibles">
                            <span class="vacation-balance-mini-bar">
                                <span class="vacation-balance-used" style="width: {{ $row['used_percent'] }}%"></span>
                                <span class="vacation-balance-remaining" style="width: {{ $row['remaining_percent'] }}%"></span>
                            </span>
                            <span class="vacation-balance-pill">{{ $row['remaining'] }} libres</span>
                        </span>
                        <i data-lucide="chevron-down"></i>
                    </summary>

                    <div class="vacation-balance-detail">
                        <div>
                            <span>Asignados</span>
                            <strong>{{ $row['assigned'] }} dias asignados</strong>
                        </div>
                        <div>
                            <span>Usados</span>
                            <strong>{{ $row['used'] }} dias usados</strong>
                        </div>
                        <div>
                            <span>Disponibles</span>
                            <strong>{{ $row['remaining'] }} dias disponibles</strong>
                        </div>
                    </div>
                </details>
            @empty
                <div class="empty-state">
                    <i data-lucide="bar-chart-3"></i>
                    <p>No hay saldos de vacaciones registrados.</p>
                </div>
            @endforelse
        </div>
    </section>

    <section class="content-grid report-secondary-grid">
        <article class="panel report-collapsible-panel wide">
            <details class="report-collapsible">
                <summary class="report-collapsible-summary">
                    <span>
                        <small>Tipos</small>
                        <strong>Balance mensual</strong>
                    </span>
                    <em>{{ $byType->count() }} motivo(s)</em>
                    <i data-lucide="chevron-down"></i>
                </summary>

                <div class="report-collapsible-body">
                    <div class="report-table">
                        <div class="report-table-head">
                            <span>Motivo</span>
                            <span>Total</span>
                            <span>Aprobadas</span>
                            <span>Pendientes</span>
                            <span>Unidades</span>
                        </div>
                        @forelse ($byType as $row)
                            <div class="report-table-row">
                                <strong>{{ $row['name'] }}</strong>
                                <span>{{ $row['total'] }}</span>
                                <span>{{ $row['approved'] }}</span>
                                <span>{{ $row['pending'] }}</span>
                                <span>{{ $row['units'] }}</span>
                            </div>
                        @empty
                            <div class="empty-state">
                                <i data-lucide="bar-chart-3"></i>
                                <p>No hay actividad en este periodo.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </details>
        </article>

        <article class="panel report-collapsible-panel">
            <details class="report-collapsible">
                <summary class="report-collapsible-summary">
                    <span>
                        <small>Anual</small>
                        <strong>Resumen anual</strong>
                    </span>
                    <em>{{ $year }}</em>
                    <i data-lucide="chevron-down"></i>
                </summary>

                <div class="report-collapsible-body">
                    <dl class="rule-list">
                        <div><dt>Vacaciones aprobadas</dt><dd>{{ $yearlyStats['vacation_used'] }} dias</dd></div>
                        <div><dt>Aprobadas</dt><dd>{{ $yearlyStats['approved'] }} solicitudes</dd></div>
                        <div><dt>Pendientes</dt><dd>{{ $yearlyStats['pending'] }} solicitudes</dd></div>
                        <div><dt>Rechazadas</dt><dd>{{ $yearlyStats['rejected'] }} solicitudes</dd></div>
                        <div><dt>Medicas</dt><dd>{{ $yearlyStats['medical_count'] }} solicitudes</dd></div>
                    </dl>
                </div>
            </details>
        </article>

        <article class="panel report-collapsible-panel">
            <details class="report-collapsible">
                <summary class="report-collapsible-summary">
                    <span>
                        <small>Justificantes</small>
                        <strong>Pendientes</strong>
                    </span>
                    <em>{{ $pendingJustifications->count() }} pendiente(s)</em>
                    <i data-lucide="chevron-down"></i>
                </summary>

                <div class="report-collapsible-body">
                    <div class="mini-list">
                        @forelse ($pendingJustifications as $leaveRequest)
                            <a href="{{ route('leave-requests.show', $leaveRequest->id) }}">
                                <strong>{{ $leaveRequest->employee_name }}</strong>
                                <span>{{ $leaveRequest->leave_type_name }} &middot; {{ $leaveRequest->start_date->format('d/m/Y') }}</span>
                            </a>
                        @empty
                            <div>
                                <strong>Sin pendientes</strong>
                                <span>Los justificantes obligatorios estan cubiertos.</span>
                            </div>
                        @endforelse
                    </div>
                </div>
            </details>
        </article>

        <article class="panel report-collapsible-panel">
            <details class="report-collapsible">
                <summary class="report-collapsible-summary">
                    <span>
                        <small>Documentos</small>
                        <strong>Recibidos</strong>
                    </span>
                    <em>{{ $recentAttachments->count() }} documento(s)</em>
                    <i data-lucide="chevron-down"></i>
                </summary>

                <div class="report-collapsible-body">
                    <div class="mini-list">
                        @forelse ($recentAttachments as $attachment)
                            <a href="{{ route('leave-requests.show', $attachment->leave_request_id) }}">
                                <strong>{{ $attachment->original_name }}</strong>
                                <span>{{ $attachment->justification_label }} &middot; {{ $attachment->created_at->format('d/m/Y') }}</span>
                            </a>
                        @empty
                            <div>
                                <strong>Sin documentos</strong>
                                <span>No hay adjuntos cargados todavia.</span>
                            </div>
                        @endforelse
                    </div>
                </div>
            </details>
        </article>
    </section>
    </div>
@endsection
