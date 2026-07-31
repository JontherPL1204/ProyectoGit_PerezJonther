@extends('layouts.app')

@section('content')
    <section class="login-screen">
        <div class="login-panel">
            <div class="brand login-brand">
                <span class="brand-mark">N</span>
                <span>N-Woffu Prime</span>
            </div>

            <div>
                <p class="eyebrow">Nueva cuenta</p>
                <h1>Crea tu acceso para gestionar solicitudes.</h1>
            </div>

            <form method="POST" action="{{ route('register.store') }}" class="form-stack">
                @csrf
                <label>
                    <span>Nombre</span>
                    <input type="text" name="name" value="{{ old('name') }}" autocomplete="name" required autofocus>
                </label>

                <label>
                    <span>Correo</span>
                    <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                </label>

                <label>
                    <span>Contrasena</span>
                    <input type="password" name="password" autocomplete="new-password" required>
                </label>

                <label>
                    <span>Confirmar contrasena</span>
                    <input type="password" name="password_confirmation" autocomplete="new-password" required>
                </label>

                <button class="primary-button" type="submit">
                    <i data-lucide="user-plus"></i>
                    <span>Crear cuenta</span>
                </button>

                <div class="auth-switch">
                    <span>Ya tienes cuenta?</span>
                    <a href="{{ route('login') }}">Entrar</a>
                </div>
            </form>
        </div>
    </section>
@endsection
