@extends('layouts.app')

@section('title', 'Equipo')

@section('content')
    <section class="content-grid">
        <article class="panel wide">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Permisos</p>
                    <h2>Dar acceso de administrador</h2>
                </div>
            </div>

            <form class="admin-filter-form team-promote-form" method="POST" action="{{ route('admin.users.promote') }}">
                @csrf
                <label>
                    <span>Correo</span>
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="persona@empresa.com" required>
                </label>

                <label class="check-row">
                    <input type="checkbox" name="can_manage_company_rules" value="1" checked>
                    <span>Gestiona reglas</span>
                </label>

                <label class="check-row">
                    <input type="checkbox" name="can_view_medical_attachments" value="1" checked>
                    <span>Ve documentos medicos</span>
                </label>

                <div class="filter-actions">
                    <button class="primary-button compact" type="submit">
                        <i data-lucide="users"></i>
                        <span>Dar permisos</span>
                    </button>
                </div>
            </form>
        </article>

        <article class="panel">
            <p class="eyebrow">Seguridad</p>
            <h2>Base</h2>
            <dl class="rule-list">
                <div><dt>Admin</dt><dd>Puede revisar solicitudes, reportes y correos</dd></div>
                <div><dt>Reglas</dt><dd>Puede cambiar reglas y permisos</dd></div>
                <div><dt>Medicos</dt><dd>Puede abrir justificantes privados</dd></div>
            </dl>
        </article>
    </section>

    <section class="panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Usuarios</p>
                <h2>Equipo registrado</h2>
            </div>
        </div>

        <form class="admin-filter-form team-filter-form" method="GET" action="{{ route('admin.users.index') }}">
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

        <div class="admin-list">
            @forelse ($users as $member)
                @php
                    $isCurrentUser = $member->is(auth()->user());
                @endphp
                <article class="admin-request team-row">
                    <div>
                        <span class="status {{ $member->isAdmin() ? 'status-approved' : 'status-cancelled' }}">{{ $member->isAdmin() ? 'Administrador' : 'Empleado' }}</span>
                        <h3>{{ $member->name }}</h3>
                        <p>{{ $member->email }}</p>
                        <p>{{ $member->employeeProfile?->department?->name ?? 'Sin departamento' }}</p>
                    </div>

                    <form class="user-permission-form" method="POST" action="{{ route('admin.users.update', $member) }}">
                        @csrf

                        @if ($isCurrentUser)
                            <input type="hidden" name="is_admin" value="1">
                            <input type="hidden" name="can_manage_company_rules" value="1">
                        @endif

                        <div class="permission-switches">
                            <label class="check-row">
                                <input type="checkbox" name="is_admin" value="1" @checked($member->isAdmin()) @disabled($isCurrentUser)>
                                <span>Admin</span>
                            </label>

                            <label class="check-row">
                                <input type="checkbox" name="can_manage_company_rules" value="1" @checked($member->can_manage_company_rules) @disabled($isCurrentUser)>
                                <span>Reglas</span>
                            </label>

                            <label class="check-row">
                                <input type="checkbox" name="can_view_medical_attachments" value="1" @checked($member->can_view_medical_attachments)>
                                <span>Medicos</span>
                            </label>
                        </div>

                        <button class="ghost-button compact" type="submit">
                            <i data-lucide="save"></i>
                            <span>Guardar</span>
                        </button>
                    </form>
                </article>
            @empty
                <div class="empty-state">
                    <i data-lucide="users"></i>
                    <p>No hay usuarios con ese filtro.</p>
                </div>
            @endforelse
        </div>
    </section>
@endsection
