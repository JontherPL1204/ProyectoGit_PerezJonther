@extends('layouts.app')

@section('title', 'Gestion')

@section('content')
    @php
        $visibleInvitations = collect($invitations->items());
        $pendingInvitationCount = $visibleInvitations->where('status', \App\Models\TeamInvitation::STATUS_PENDING)->count();
        $customPositionCount = $positions->where('is_system', false)->count();
        $assignedPositionCount = $positions->sum('employee_profiles_count');
    @endphp

    <section class="admin-console-layout">
        <article class="panel wide admin-console-main">
            <div class="admin-console-head">
                <div>
                    <p class="eyebrow">Gestion interna</p>
                    <h2>Invitaciones y puestos</h2>
                </div>
            </div>

            <div class="admin-stat-strip" aria-label="Resumen de gestion">
                <div class="admin-stat accent">
                    <span>Invitaciones</span>
                    <strong>{{ $pendingInvitationCount }}</strong>
                    <em>pendientes visibles</em>
                </div>
                <div class="admin-stat">
                    <span>Puestos</span>
                    <strong>{{ $positions->count() }}</strong>
                    <em>{{ $customPositionCount }} personalizados</em>
                </div>
                <div class="admin-stat">
                    <span>Asignaciones</span>
                    <strong>{{ $assignedPositionCount }}</strong>
                    <em>puestos vinculados</em>
                </div>
                <div class="admin-stat">
                    <span>Pagina</span>
                    <strong>{{ $visibleInvitations->count() }}</strong>
                    <em>invitaciones listadas</em>
                </div>
            </div>

            @if (session('invite_link'))
                <div class="copy-box invite-link-box">
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

            <section class="admin-console-section">
                <div class="admin-section-head">
                    <div>
                        <p class="eyebrow">Invitaciones</p>
                        <h3>Invitar al equipo</h3>
                    </div>
                </div>

                <form class="invite-form" method="POST" action="{{ route('admin.management.invitations.store') }}" data-role-permissions>
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

                    <div class="permission-card">
                        <label class="check-row">
                            <input type="checkbox" name="can_manage_company_rules" value="1" @checked(old('can_manage_company_rules')) data-admin-only-permission>
                            <span>Gestiona reglas y equipo</span>
                        </label>

                        <label class="check-row">
                            <input type="checkbox" name="can_view_medical_attachments" value="1" @checked(old('can_view_medical_attachments')) data-admin-only-permission>
                            <span>Puede ver adjuntos medicos</span>
                        </label>
                    </div>

                    <button class="primary-button compact" type="submit">
                        <i data-lucide="user-plus"></i>
                        <span>Crear invitacion</span>
                    </button>
                </form>
            </section>

            <section class="admin-console-section">
                <div class="admin-section-head">
                    <div>
                        <p class="eyebrow">Estado</p>
                        <h3>Invitaciones enviadas</h3>
                    </div>
                </div>

                <div class="invitation-list">
                    @forelse ($invitations as $invitation)
                        @php
                            $label = $invitation->statusLabel();
                            $statusClass = match ($label) {
                                'Aceptada' => 'approved',
                                'Revocada', 'Caducada' => 'rejected',
                                default => 'pending',
                            };
                            $roleLabel = match ($invitation->initial_role) {
                                \App\Models\User::ROLE_ADMIN => 'Administrador',
                                \App\Models\User::ROLE_DEVELOPER => 'Desarrollador',
                                default => 'Empleado',
                            };
                        @endphp
                        <article class="invitation-card">
                            <div class="invitation-main">
                                <span class="status status-{{ $statusClass }}">{{ $label }}</span>
                                <div>
                                    <h3>{{ $invitation->email }}</h3>
                                    <p>{{ $roleLabel }} &middot; vence {{ auth()->user()->formatDateTime($invitation->expires_at) }}</p>
                                    @if ($invitation->last_sent_at)
                                        <p>Ultimo envio {{ auth()->user()->formatDateTime($invitation->last_sent_at) }}</p>
                                    @endif
                                </div>
                            </div>

                            @if ($invitation->last_error)
                                <div class="overlap-alert invitation-alert">
                                    <strong>No se pudo enviar por correo</strong>
                                    <span>{{ $invitation->last_error }}</span>
                                </div>
                            @endif

                            @if ($invitation->status === \App\Models\TeamInvitation::STATUS_PENDING)
                                <div class="invitation-actions">
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
            </section>
        </article>

        <aside class="admin-console-aside">
            <article class="panel admin-side-panel">
                <p class="eyebrow">Seguridad</p>
                <h2>Invitaciones</h2>
                <dl class="rule-list">
                    <div><dt>Caducidad</dt><dd>7 dias desde el ultimo envio</dd></div>
                    <div><dt>Uso</dt><dd>El enlace queda bloqueado al aceptarse</dd></div>
                    <div><dt>Token</dt><dd>Solo se guarda una huella segura</dd></div>
                </dl>
            </article>

            <article class="panel admin-side-panel">
                <div class="admin-section-head">
                    <div>
                        <p class="eyebrow">Puestos</p>
                        <h2>Puestos de trabajo</h2>
                    </div>
                </div>

                <form class="position-create-panel" method="POST" action="{{ route('admin.management.positions.store') }}">
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

                <div class="position-admin-list">
                    @foreach ($positions as $position)
                        <div class="position-admin-card">
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
                                        <span>Personalizado &middot; {{ $position->employee_profiles_count }} integrante(s)</span>
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
        </aside>
    </section>
@endsection
