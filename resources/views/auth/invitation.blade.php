@extends('layouts.app')

@section('title', 'Invitacion')

@section('content')
    <section class="login-screen">
        <article class="login-panel">
            <div class="brand login-brand">
                <span class="brand-mark">N</span>
                <span>N-Woffu Prime</span>
            </div>

            <p class="eyebrow">Invitacion al equipo</p>

            @if (! $invitation)
                <h1>Este enlace no es valido.</h1>
                <p class="login-copy">Pide a la persona responsable que genere una invitacion nueva.</p>
            @elseif ($invitation->status === \App\Models\TeamInvitation::STATUS_ACCEPTED)
                <h1>Esta invitacion ya fue utilizada.</h1>
                <p class="login-copy">Puedes iniciar sesion con la cuenta creada para este correo.</p>
                <a class="primary-button" href="{{ route('login') }}">
                    <i data-lucide="log-in"></i>
                    <span>Ir al inicio de sesion</span>
                </a>
            @elseif ($invitation->status === \App\Models\TeamInvitation::STATUS_REVOKED)
                <h1>Esta invitacion fue revocada.</h1>
                <p class="login-copy">Solicita un nuevo enlace si todavia debes unirte al equipo.</p>
            @elseif ($invitation->isExpired())
                <h1>Esta invitacion caduco.</h1>
                <p class="login-copy">Pide que te reenvien una invitacion para continuar.</p>
            @else
                <h1>Completa tu cuenta.</h1>
                <p class="login-copy">{{ $invitation->organization->name }} te invito con el correo {{ $invitation->email }}.</p>

                <form method="POST" action="{{ route('invitations.accept', $token) }}" class="login-form">
                    @csrf
                    <label>
                        <span>Nombre</span>
                        <input type="text" name="name" value="{{ old('name') }}" required autofocus>
                    </label>

                    <input type="hidden" name="timezone" data-detected-timezone value="{{ old('timezone') }}">

                    <label>
                        <span>Contrasena</span>
                        <input type="password" name="password" required>
                    </label>

                    <label>
                        <span>Confirmar contrasena</span>
                        <input type="password" name="password_confirmation" required>
                    </label>

                    <button class="primary-button" type="submit">
                        <i data-lucide="check-circle-2"></i>
                        <span>Aceptar invitacion</span>
                    </button>
                </form>
            @endif
        </article>
    </section>
@endsection
