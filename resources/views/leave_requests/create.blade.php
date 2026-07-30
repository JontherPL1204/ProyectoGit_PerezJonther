@extends('layouts.app')

@section('title', 'Nueva Solicitud')

@section('content')
    <section class="panel form-panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Solicitud</p>
                <h2>Crear ausencia</h2>
            </div>
        </div>

        <form method="POST" action="{{ route('leave-requests.store') }}" class="form-grid" enctype="multipart/form-data">
            @csrf
            <label>
                <span>Motivo</span>
                <select name="leave_type_id" required>
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}" @selected(old('leave_type_id') == $type->id)>
                            {{ $type->name }} - {{ $type->unit === 'DAYS' ? 'dias' : 'horas/minutos' }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Fecha inicio</span>
                <input type="date" name="start_date" value="{{ old('start_date') }}" required>
            </label>

            <label>
                <span>Fecha fin</span>
                <input type="date" name="end_date" value="{{ old('end_date') }}" required>
            </label>

            <label>
                <span>Hora inicio</span>
                <input type="time" name="start_time" value="{{ old('start_time') }}">
            </label>

            <label>
                <span>Hora fin</span>
                <input type="time" name="end_time" value="{{ old('end_time') }}">
            </label>

            <label class="full">
                <span>Comentario</span>
                <textarea name="user_comment" rows="4" maxlength="1000">{{ old('user_comment') }}</textarea>
            </label>

            <label class="full">
                <span>Adjuntos privados</span>
                <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf">
            </label>

            <div class="form-note full">
                <i data-lucide="info"></i>
                <span>Vacaciones usa 30 dias naturales de anticipacion. Adjuntos permitidos: JPG, PNG y PDF hasta 5 MB.</span>
            </div>

            <div class="form-actions full">
                <a class="ghost-button" href="{{ route('dashboard') }}">
                    <i data-lucide="arrow-left"></i>
                    <span>Volver</span>
                </a>
                <button class="primary-button" type="submit">
                    <i data-lucide="send"></i>
                    <span>Enviar</span>
                </button>
            </div>
        </form>
    </section>
@endsection
