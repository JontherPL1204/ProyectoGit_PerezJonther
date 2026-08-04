@extends('layouts.app')

@section('title', 'Sistema')

@section('content')
    <section class="metric-grid">
        @foreach ($metrics as $label => $value)
            <article class="metric-card {{ $loop->first ? 'accent' : ($label === 'Correos fallidos' ? 'dark' : '') }}">
                <p>{{ $label }}</p>
                <strong>{{ $value }}</strong>
                <span>estado actual</span>
            </article>
        @endforeach
    </section>

    <section class="content-grid">
        <article class="panel wide">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Soporte</p>
                    <h2>Diagnostico tecnico</h2>
                </div>
            </div>

            <div class="mini-list">
                @foreach ($system as $label => $value)
                    <div>
                        <strong>{{ $label }}</strong>
                        <span>{{ filled($value) ? $value : 'No configurado' }}</span>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="panel">
            <p class="eyebrow">Acceso</p>
            <h2>Resumen</h2>
            <dl class="rule-list">
                <div><dt>Desarrollador</dt><dd>Soporte tecnico y correos fallidos</dd></div>
                <div><dt>Sin acceso</dt><dd>Aprobaciones, reglas, equipo y documentos medicos</dd></div>
                <div><dt>Privacidad</dt><dd>No se muestran claves ni contenido de adjuntos</dd></div>
            </dl>
        </article>
    </section>

    <section class="content-grid">
        <article class="panel wide">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Correos</p>
                    <h2>Fallos recientes</h2>
                </div>
            </div>

            <div class="admin-list">
                @forelse ($recentFailures as $failure)
                    <article class="admin-request notification-row">
                        <div>
                            <span class="status status-rejected">No enviado</span>
                            <h3>{{ $failure->event }}</h3>
                            <p>{{ $failure->recipient_email }} &middot; {{ auth()->user()->formatDateTime($failure->updated_at) }}</p>
                            @if ($failure->last_error)
                                <div class="overlap-alert">
                                    <strong>Error tecnico</strong>
                                    <span>{{ $failure->last_error }}</span>
                                </div>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="empty-state">
                        <i data-lucide="check-circle-2"></i>
                        <p>No hay correos fallidos.</p>
                    </div>
                @endforelse
            </div>
        </article>

        <article class="panel">
            <p class="eyebrow">Usuarios</p>
            <h2>Roles</h2>
            <div class="mini-list">
                <div><strong>Administradores</strong><span>{{ (int) ($roleCounts[\App\Models\User::ROLE_ADMIN] ?? 0) }}</span></div>
                <div><strong>Desarrolladores</strong><span>{{ (int) ($roleCounts[\App\Models\User::ROLE_DEVELOPER] ?? 0) }}</span></div>
                <div><strong>Empleados</strong><span>{{ (int) ($roleCounts[\App\Models\User::ROLE_USER] ?? 0) }}</span></div>
            </div>

            <p class="eyebrow support-subhead">Outbox</p>
            <div class="mini-list">
                <div><strong>Pendientes</strong><span>{{ (int) ($notificationCounts['pending'] ?? 0) }}</span></div>
                <div><strong>Enviados</strong><span>{{ (int) ($notificationCounts['sent'] ?? 0) }}</span></div>
                <div><strong>Fallidos</strong><span>{{ (int) ($notificationCounts['failed'] ?? 0) }}</span></div>
            </div>
        </article>
    </section>
@endsection
