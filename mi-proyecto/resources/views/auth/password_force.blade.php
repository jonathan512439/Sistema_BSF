@extends('layouts.app')
@section('title', 'Cambiar contraseña')

@section('content')
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header">Cambio de contraseña requerido</div>
        <div class="card-body">
          <p>Por seguridad, debes definir una nueva contraseña antes de continuar.</p>

          <form method="POST" action="{{ route('password.force.update') }}">
            @csrf

            <div class="mb-3">
              <label for="password" class="form-label">Nueva contraseña</label>
              <input id="password" type="password" name="password" required
                     class="form-control @error('password') is-invalid @enderror">
              @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
              <input id="password_confirmation" type="password" name="password_confirmation" required
                     class="form-control">
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-primary">
                Guardar contraseña
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
