@extends('layouts.app')

@section('content')
    <section class="login-screen">
        <div class="login-panel">
            <div class="brand login-brand">
                <span class="brand-mark">N</span>
                <span>N-Woffu Prime</span>
            </div>

            <div>
                <p class="eyebrow">Acceso seguro</p>
                <h1>Gestiona ausencias sin perder el control del saldo.</h1>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="form-stack">
                @csrf
                <label>
                    <span>Correo</span>
                    <input type="email" name="email" value="{{ old('email', 'javierperezlopez1204@gmail.com') }}" autocomplete="email" required autofocus>
                </label>

                <label>
                    <span>Contraseña</span>
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>

                <label class="check-row">
                    <input type="checkbox" name="remember" value="1">
                    <span>Mantener sesion</span>
                </label>

                <button class="primary-button" type="submit">
                    <i data-lucide="log-in"></i>
                    <span>Entrar</span>
                </button>
            </form>
        </div>

        <div class="login-visual">
            <div class="visual-card accent">
                <span>15</span>
                <small>dias anuales configurables</small>
            </div>
            <div class="visual-card">
                <span>30</span>
                <small>dias de anticipacion base</small>
            </div>
            <div class="visual-card dark">
                <span>Admin</span>
                <small>reglas, permisos medicos y auditoria</small>
            </div>
        </div>
    </section>
@endsection
