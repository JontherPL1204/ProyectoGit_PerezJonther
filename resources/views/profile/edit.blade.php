@extends('layouts.app')

@section('title', 'Perfil')

@section('content')
    <section class="content-grid">
        <article class="panel wide">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Preferencias</p>
                    <h2>Zona horaria</h2>
                </div>
            </div>

            <form method="POST" action="{{ route('profile.timezone.update') }}" class="form-grid">
                @csrf
                <label class="full">
                    <span>Mostrar fechas y horas en</span>
                    <select name="timezone" required>
                        @foreach ($timezones as $timezone)
                            <option value="{{ $timezone }}" @selected($currentTimezone === $timezone)>{{ $timezone }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="form-note full">
                    <i data-lucide="info"></i>
                    <span>La aplicacion guarda los momentos del sistema en UTC y los muestra segun esta zona horaria.</span>
                </div>

                <div class="form-actions full">
                    <a class="ghost-button" href="{{ route('dashboard') }}">
                        <i data-lucide="arrow-left"></i>
                        <span>Volver</span>
                    </a>
                    <button class="primary-button" type="submit">
                        <i data-lucide="save"></i>
                        <span>Guardar zona horaria</span>
                    </button>
                </div>
            </form>
        </article>

        <article class="panel">
            <p class="eyebrow">Actual</p>
            <h2>{{ $currentTimezone }}</h2>
            <dl class="rule-list">
                <div><dt>Ahora</dt><dd>{{ auth()->user()->formatDateTime(now()) }}</dd></div>
                <div><dt>Formato</dt><dd>Identificador IANA</dd></div>
            </dl>
        </article>
    </section>
@endsection
