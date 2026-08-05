@extends('layouts.app')

@section('title', 'Equipo')

@section('content')
    <section class="admin-console-layout">
        <article class="panel wide admin-console-main">
            <div class="admin-console-head">
                <div>
                    <p class="eyebrow">Usuarios</p>
                    <h2>Equipo registrado</h2>
                </div>
            </div>

            <div class="admin-stat-strip" aria-label="Resumen de equipo">
                <div class="admin-stat accent">
                    <span>Personas</span>
                    <strong>{{ $teamCounts['visible'] }}</strong>
                    <em>visibles</em>
                </div>
                <div class="admin-stat">
                    <span>Admins</span>
                    <strong>{{ $teamCounts['admins'] }}</strong>
                    <em>con acceso elevado</em>
                </div>
                <div class="admin-stat">
                    <span>Desarrollo</span>
                    <strong>{{ $teamCounts['developers'] }}</strong>
                    <em>soporte tecnico</em>
                </div>
                <div class="admin-stat">
                    <span>Ocultos</span>
                    <strong>{{ $teamCounts['inactive'] }}</strong>
                    <em>con historial guardado</em>
                </div>
            </div>

            <section class="admin-console-section">
                <div class="admin-section-head">
                    <div>
                        <p class="eyebrow">Permisos</p>
                        <h3>Dar acceso de administrador</h3>
                    </div>
                </div>

                <form class="team-promote-panel" method="POST" action="{{ route('admin.users.promote') }}" data-role-permissions>
                    @csrf
                    <label>
                        <span>Correo</span>
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="persona@empresa.com" required>
                    </label>

                    <label>
                        <span>Permiso</span>
                        <select name="role" data-role-select>
                            <option value="admin" @selected(old('role', 'admin') === 'admin')>Administrador</option>
                            <option value="developer" @selected(old('role') === 'developer')>Desarrollador</option>
                            <option value="user" @selected(old('role') === 'user')>Empleado</option>
                        </select>
                    </label>

                    <details class="permission-dropdown">
                        <summary class="permission-dropdown-summary">
                            <span>
                                <small>Permisos avanzados</small>
                                <strong>Reglas y medicos</strong>
                            </span>
                            <i data-lucide="chevron-down"></i>
                        </summary>

                        <div class="permission-dropdown-body">
                            <label class="check-row">
                                <input type="checkbox" name="can_manage_company_rules" value="1" checked data-admin-only-permission>
                                <span>Gestiona reglas</span>
                            </label>

                            <label class="check-row">
                                <input type="checkbox" name="can_view_medical_attachments" value="1" checked data-admin-only-permission>
                                <span>Ve documentos medicos</span>
                            </label>
                        </div>
                    </details>

                    <button class="primary-button compact" type="submit">
                        <i data-lucide="users"></i>
                        <span>Dar permisos</span>
                    </button>
                </form>
            </section>

            <section class="admin-console-section">
                <div class="admin-section-head">
                    <div>
                        <p class="eyebrow">Listado</p>
                        <h3>Personas del equipo</h3>
                    </div>
                </div>

                <form class="team-search-panel" method="GET" action="{{ route('admin.users.index') }}">
                    <label>
                        <span>Buscar</span>
                        <input type="search" name="q" value="{{ $search }}" placeholder="Nombre o correo">
                    </label>

                    <div class="filter-actions">
                        <button class="primary-button compact" type="submit">
                            <i data-lucide="search"></i>
                            <span>Filtrar</span>
                        </button>
                        <a class="ghost-button compact" href="{{ route('admin.users.index') }}">
                            <i data-lucide="rotate-ccw"></i>
                            <span>Limpiar</span>
                        </a>
                    </div>
                </form>

                <div class="team-list">
                    @forelse ($users as $member)
                        @php
                            $isCurrentUser = (int) $member->id === (int) auth()->id();
                            $isAdmin = $member->role === \App\Models\User::ROLE_ADMIN;
                            $roleLabel = match ($member->role) {
                                \App\Models\User::ROLE_ADMIN => 'Administrador',
                                \App\Models\User::ROLE_DEVELOPER => 'Desarrollador',
                                default => 'Empleado',
                            };
                            $roleStatusClass = match ($member->role) {
                                \App\Models\User::ROLE_ADMIN => 'status-approved',
                                \App\Models\User::ROLE_DEVELOPER => 'status-pending_cancellation',
                                default => 'status-cancelled',
                            };
                            $assignedPositions = collect($member->employeeProfile?->jobPositions ?? []);
                            $assignedPositionIds = $assignedPositions->pluck('id')->map(fn ($id) => (int) $id)->all();
                            $shouldOpen = (int) old('open_user_id', session('open_user_id')) === (int) $member->id;
                        @endphp
                        <details class="team-member-card" @if ($shouldOpen) open @endif>
                            <summary class="team-member-summary">
                                <div class="team-member-identity">
                                    <span class="status {{ $roleStatusClass }}">{{ $roleLabel }}</span>
                                    <div>
                                        <h3>{{ $member->name }}</h3>
                                        <p>{{ $member->email }}</p>
                                        <p>{{ $member->employeeProfile?->department?->name ?? 'Sin departamento' }}</p>
                                    </div>
                                </div>

                                <div class="team-member-side">
                                    <div class="chip-list team-summary-chips">
                                        @forelse ($assignedPositions as $position)
                                            <span>{{ $position->name }}</span>
                                        @empty
                                            <span>Sin puesto asignado</span>
                                        @endforelse
                                    </div>
                                    <span class="team-toggle" aria-hidden="true">
                                        <i data-lucide="chevron-down"></i>
                                    </span>
                                </div>
                            </summary>

                            <div class="team-member-body">
                                <section class="team-editor-block">
                                    <div class="team-editor-head">
                                        <p class="eyebrow">Puestos</p>
                                        <h3>Puestos de trabajo</h3>
                                    </div>

                                    <form class="user-position-form" method="POST" action="{{ route('admin.users.positions.update', $member->id) }}">
                                        @csrf
                                        <input type="hidden" name="open_user_id" value="{{ $member->id }}">

                                        <details class="position-dropdown">
                                            <summary class="position-dropdown-summary">
                                                <span>
                                                    <small>Puestos existentes</small>
                                                    <strong>{{ $assignedPositions->count() }} asignado(s)</strong>
                                                </span>
                                                <i data-lucide="chevron-down"></i>
                                            </summary>

                                            <fieldset class="position-picker">
                                                <legend>Seleccionar puestos</legend>
                                                <div class="position-options position-options-menu">
                                                    @foreach ($positions as $position)
                                                        <label class="position-option">
                                                            <input type="checkbox" name="job_position_ids[]" value="{{ $position->id }}" @checked(in_array((int) $position->id, $assignedPositionIds, true))>
                                                            <span>{{ $position->name }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>

                                                @if ($positions->isEmpty())
                                                    <p class="empty-inline">No hay puestos creados todavia. Crea puestos desde Gestion.</p>
                                                @endif
                                            </fieldset>
                                        </details>

                                        <button class="ghost-button compact" type="submit">
                                            <i data-lucide="briefcase"></i>
                                            <span>Guardar puestos</span>
                                        </button>
                                    </form>
                                </section>

                                <section class="team-editor-block">
                                    <div class="team-editor-head">
                                        <p class="eyebrow">Acceso</p>
                                        <h3>Permisos</h3>
                                    </div>

                                    <form class="user-permission-form" method="POST" action="{{ route('admin.users.update', $member->id) }}" data-role-permissions>
                                        @csrf
                                        <input type="hidden" name="open_user_id" value="{{ $member->id }}">

                                        @if ($isCurrentUser)
                                            <input type="hidden" name="role" value="admin">
                                            <input type="hidden" name="can_manage_company_rules" value="1">
                                        @endif

                                        <div class="permission-switches">
                                            <label>
                                                <span>Rol</span>
                                                <select name="role" data-role-select @disabled($isCurrentUser)>
                                                    <option value="user" @selected($member->role === \App\Models\User::ROLE_USER)>Empleado</option>
                                                    <option value="admin" @selected($member->role === \App\Models\User::ROLE_ADMIN)>Administrador</option>
                                                    <option value="developer" @selected($member->role === \App\Models\User::ROLE_DEVELOPER)>Desarrollador</option>
                                                </select>
                                            </label>

                                            <label class="check-row">
                                                <input type="checkbox" name="can_manage_company_rules" value="1" @checked($member->can_manage_company_rules) @disabled($isCurrentUser) data-admin-only-permission data-locked="{{ $isCurrentUser ? '1' : '0' }}">
                                                <span>Reglas</span>
                                            </label>

                                            <label class="check-row">
                                                <input type="checkbox" name="can_view_medical_attachments" value="1" @checked($member->can_view_medical_attachments) data-admin-only-permission>
                                                <span>Medicos</span>
                                            </label>
                                        </div>

                                        <button class="ghost-button compact" type="submit">
                                            <i data-lucide="save"></i>
                                            <span>Guardar permisos</span>
                                        </button>
                                    </form>
                                </section>

                                <section class="team-editor-block team-danger-block">
                                    <div class="team-editor-head">
                                        <p class="eyebrow">Visibilidad</p>
                                        <h3>Ocultar trabajador</h3>
                                    </div>

                                    <p>Deja de aparecer en el equipo y en los filtros activos. Su historial, solicitudes y documentos se conservan.</p>

                                    @if ($isCurrentUser)
                                        <span class="status status-cancelled">No disponible para tu propia cuenta</span>
                                    @else
                                        <form method="POST" action="{{ route('admin.users.deactivate', $member->id) }}" data-confirm="Ocultar a {{ $member->name }} del equipo visible? Sus solicitudes, documentos e historial se conservaran.">
                                            @csrf
                                            <button class="danger-button compact" type="submit">
                                                <i data-lucide="user-x"></i>
                                                <span>Ocultar trabajador</span>
                                            </button>
                                        </form>
                                    @endif
                                </section>
                            </div>
                        </details>
                    @empty
                        <div class="empty-state">
                            <i data-lucide="users"></i>
                            <p>No hay usuarios con ese filtro.</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </article>

        <aside class="admin-console-aside">
            <article class="panel admin-side-panel">
                <p class="eyebrow">Seguridad</p>
                <h2>Base</h2>
                <dl class="rule-list">
                    <div><dt>Admin</dt><dd>Puede revisar solicitudes, reportes y correos</dd></div>
                    <div><dt>Reglas</dt><dd>Puede cambiar reglas y permisos</dd></div>
                    <div><dt>Medicos</dt><dd>Puede abrir justificantes privados</dd></div>
                    <div><dt>Desarrollador</dt><dd>Ve soporte tecnico sin datos medicos ni aprobaciones</dd></div>
                </dl>
            </article>

            <article class="panel admin-side-panel">
                <p class="eyebrow">Puestos</p>
                <h2>Disponibles</h2>
                <div class="position-chip-panel">
                    @forelse ($positions as $position)
                        <span>{{ $position->name }}</span>
                    @empty
                        <p class="empty-inline">No hay puestos creados todavia.</p>
                    @endforelse
                </div>
            </article>
        </aside>
    </section>
@endsection
