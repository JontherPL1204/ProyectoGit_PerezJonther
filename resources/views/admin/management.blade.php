@extends('layouts.app')

@section('title', 'Gestion')

@section('content')
    <section class="content-grid">
        <article class="panel wide">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Invitaciones</p>
                    <h2>Invitar al equipo</h2>
                </div>
            </div>

            @if (session('invite_link'))
                <div class="copy-box">
                    <label>
                        <span>Enlace seguro</span>
                        <input type="text" value="{{ session('invite_link') }}" readonly data-copy-source>
                    </label>
                    <button class="ghost-button compact" type="button" data-copy-button>
                        <i data-lucide="copy"></i>
                        <span>Copiar enlace</span>
                    </button>
                </div>
            @endif

            <form class="admin-filter-form" method="POST" action="{{ route('admin.management.invitations.store') }}" data-role-permissions>
                @csrf
                <label>
                    <span>Correo</span>
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="persona@empresa.com" required>
                </label>

                <label>
                    <span>Permiso inicial</span>
                    <select name="initial_role" data-role-select>
                        <option value="user" @selected(old('initial_role', 'user') === 'user')>Empleado</option>
                        <option value="admin" @selected(old('initial_role') === 'admin')>Administrador</option>
                        <option value="developer" @selected(old('initial_role') === 'developer')>Desarrollador</option>
                    </select>
                </label>

                <label class="check-row">
                    <input type="checkbox" name="can_manage_company_rules" value="1" @checked(old('can_manage_company_rules')) data-admin-only-permission>
                    <span>Gestiona reglas y equipo</span>
                </label>

                <label class="check-row">
                    <input type="checkbox" name="can_view_medical_attachments" value="1" @checked(old('can_view_medical_attachments')) data-admin-only-permission>
                    <span>Puede ver adjuntos medicos</span>
                </label>

                <div class="filter-actions">
                    <button class="primary-button compact" type="submit">
                        <i data-lucide="user-plus"></i>
                        <span>Crear invitacion</span>
                    </button>
                </div>
            </form>
        </article>

        <article class="panel">
            <p class="eyebrow">Seguridad</p>
            <h2>Invitaciones</h2>
            <dl class="rule-list">
                <div><dt>Caducidad</dt><dd>7 dias desde el ultimo envio</dd></div>
                <div><dt>Uso</dt><dd>El enlace queda bloqueado al aceptarse</dd></div>
                <div><dt>Token</dt><dd>Solo se guarda una huella segura</dd></div>
            </dl>
        </article>
    </section>

    <section class="content-grid">
        <article class="panel wide">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Estado</p>
                    <h2>Invitaciones enviadas</h2>
                </div>
            </div>

            <div class="admin-list">
                @forelse ($invitations as $invitation)
                    @php
                        $label = $invitation->statusLabel();
                        $statusClass = match ($label) {
                            'Aceptada' => 'approved',
                            'Revocada', 'Caducada' => 'rejected',
                            default => 'pending',
                        };
                    @endphp
                    <article class="admin-request notification-row">
                        <div>
                            <span class="status status-{{ $statusClass }}">{{ $label }}</span>
                            <h3>{{ $invitation->email }}</h3>
                            <p>
                                @if ($invitation->initial_role === \App\Models\User::ROLE_ADMIN)
                                    Administrador
                                @elseif ($invitation->initial_role === \App\Models\User::ROLE_DEVELOPER)
                                    Desarrollador
                                @else
                                    Empleado
                                @endif
                                &middot;
                                vence {{ auth()->user()->formatDateTime($invitation->expires_at) }}
                            </p>
                            @if ($invitation->last_sent_at)
                                <p>Ultimo envio {{ auth()->user()->formatDateTime($invitation->last_sent_at) }}</p>
                            @endif
                            @if ($invitation->last_error)
                                <div class="overlap-alert">
                                    <strong>No se pudo enviar por correo</strong>
                                    <span>{{ $invitation->last_error }}</span>
                                </div>
                            @endif
                        </div>

                        @if ($invitation->status === \App\Models\TeamInvitation::STATUS_PENDING)
                            <div class="admin-actions readonly">
                                <form method="POST" action="{{ route('admin.management.invitations.resend', $invitation) }}">
                                    @csrf
                                    <button class="ghost-button compact" type="submit">
                                        <i data-lucide="mail"></i>
                                        <span>Reenviar invitacion</span>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.management.invitations.revoke', $invitation) }}">
                                    @csrf
                                    <button class="danger-button compact" type="submit">
                                        <i data-lucide="x-circle"></i>
                                        <span>Revocar</span>
                                    </button>
                                </form>
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="empty-state">
                        <i data-lucide="user-plus"></i>
                        <p>Aun no hay invitaciones.</p>
                    </div>
                @endforelse
            </div>

            <div class="pagination-wrap">
                {{ $invitations->links() }}
            </div>
        </article>

        <article class="panel">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Puestos</p>
                    <h2>Puestos de trabajo</h2>
                </div>
            </div>

            <form class="stacked-form" method="POST" action="{{ route('admin.management.positions.store') }}">
                @csrf
                <label>
                    <span>Nuevo puesto</span>
                    <input type="text" name="name" maxlength="120" placeholder="Ej. QA Analyst">
                </label>
                <button class="primary-button compact" type="submit">
                    <i data-lucide="briefcase"></i>
                    <span>Crear puesto</span>
                </button>
            </form>

            <div class="mini-list position-list">
                @foreach ($positions as $position)
                    <div class="position-list-row">
                        @if ($position->is_system)
                            <div class="position-row-summary">
                                <strong>{{ $position->name }}</strong>
                                <span>Inicial &middot; {{ $position->employee_profiles_count }} integrante(s)</span>
                            </div>
                            <span class="position-lock">
                                <i data-lucide="lock"></i>
                                Base
                            </span>
                        @else
                            <form class="position-row-form" method="POST" action="{{ route('admin.management.positions.update', $position->id) }}">
                                @csrf
                                <label>
                                    <span>Puesto personalizado &middot; {{ $position->employee_profiles_count }} integrante(s)</span>
                                    <input type="text" name="name" value="{{ $position->name }}" maxlength="120" required>
                                </label>
                                <button class="ghost-button compact" type="submit">
                                    <i data-lucide="save"></i>
                                    <span>Guardar</span>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.management.positions.destroy', $position->id) }}" data-confirm="Eliminar este puesto lo quitara de {{ $position->employee_profiles_count }} integrante(s).">
                                @csrf
                                <button class="danger-button compact" type="submit">
                                    <i data-lucide="trash-2"></i>
                                    <span>Eliminar puesto</span>
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </article>
    </section>
@endsection
