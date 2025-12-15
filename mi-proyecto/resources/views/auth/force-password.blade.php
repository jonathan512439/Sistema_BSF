@extends('layouts.guest')

@section('title', 'Cambiar contraseña inicial')

@section('content')
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header">
          <h5 class="mb-0">Cambiar contraseña</h5>
        </div>
        <div class="card-body">
          <p class="text-muted">
            Debes definir una nueva contraseña antes de acceder al sistema.
          </p>

          @if ($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <form method="POST" action="{{ route('password.force.update') }}">
            @csrf

            <div class="mb-3">
              <label for="password" class="form-label">Nueva contraseña</label>
              <input id="password" type="password" name="password" class="form-control" required autofocus>
            </div>

            <div class="mb-3">
              <label for="password_confirmation" class="form-label">Confirmar nueva contraseña</label>
              <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">
              Guardar y continuar
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
