<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'N-Woffu Prime') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell {{ auth()->check() ? '' : 'guest-shell' }}">
        @auth
            <aside class="sidebar">
                <a class="brand" href="{{ route('dashboard') }}" aria-label="N-Woffu Prime">
                    <span class="brand-mark">N</span>
                    <span>N-Woffu Prime</span>
                </a>

                <nav class="nav-list" aria-label="Principal">
                    <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                        <i data-lucide="layout-dashboard"></i>
                        <span>Inicio</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('leave-requests.create') ? 'active' : '' }}" href="{{ route('leave-requests.create') }}">
                        <i data-lucide="plus-circle"></i>
                        <span>Nueva solicitud</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('calendar') ? 'active' : '' }}" href="{{ route('calendar') }}">
                        <i data-lucide="calendar-days"></i>
                        <span>Calendario</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('history') ? 'active' : '' }}" href="{{ route('history') }}">
                        <i data-lucide="history"></i>
                        <span>Historial</span>
                    </a>
                    @if (auth()->user()->isAdmin())
                        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                            <i data-lucide="inbox"></i>
                            <span>Revision</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('admin.reports') ? 'active' : '' }}" href="{{ route('admin.reports') }}">
                            <i data-lucide="bar-chart-3"></i>
                            <span>Reportes</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}" href="{{ route('admin.notifications.index') }}">
                            <i data-lucide="mail"></i>
                            <span>Correos</span>
                        </a>
                    @endif
                    @if (auth()->user()->canManageCompanyRules())
                        <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                            <i data-lucide="users"></i>
                            <span>Equipo</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('admin.management.*') ? 'active' : '' }}" href="{{ route('admin.management.index') }}">
                            <i data-lucide="user-plus"></i>
                            <span>Gestion</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('admin.rules.*') ? 'active' : '' }}" href="{{ route('admin.rules.edit') }}">
                            <i data-lucide="sliders-horizontal"></i>
                            <span>Reglas</span>
                        </a>
                    @endif
                </nav>

                <form method="POST" action="{{ route('logout') }}" class="sidebar-footer">
                    @csrf
                    <button class="ghost-button" type="submit">
                        <i data-lucide="log-out"></i>
                        <span>Salir</span>
                    </button>
                </form>
            </aside>
        @endauth

        <main class="main-panel">
            @auth
                <header class="topbar">
                    <div>
                        <p class="eyebrow">Equipo interno</p>
                        <h1>@yield('title', 'N-Woffu Prime')</h1>
                    </div>
                    <a
                        class="user-chip"
                        href="{{ route('profile.edit') }}"
                        data-timezone-sync-url="{{ route('profile.timezone.detect') }}"
                        data-current-timezone="{{ auth()->user()->timezoneName() }}"
                    >
                        <span>{{ auth()->user()->name }}</span>
                    </a>
                </header>
            @endauth

            @if (session('status'))
                <div class="flash success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="flash danger">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
