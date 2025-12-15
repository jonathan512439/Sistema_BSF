@extends('layouts.guest')
@section('title', 'Activar cuenta')

@section('content')
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header">Activar cuenta BSF</div>
        <div class="card-body">
          <p>Estás activando la cuenta asociada al correo: <strong>{{ $email }}</strong></p>

          @if ($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <form method="POST" action="{{ route('invitation.accept') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="mb-3">
              <label for="password" class="form-label">Define tu contraseña</label>
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
                Activar cuenta
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
