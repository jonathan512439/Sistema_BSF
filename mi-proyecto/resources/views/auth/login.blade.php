@extends('layouts.guest')
@section('title', 'Login - Sistema BSF')

@section('content')
<style>
body {
  margin: 0;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
  background: linear-gradient(135deg, #2d3e2b 0%, #1f2b1e 100%);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
}

.login-container {
  width: 100%;
  max-width: 640px;
  padding: 2rem;
  margin: 2rem;
}

.login-card {
  background: white;
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
  overflow: hidden;
}

.login-header {
  padding: 3rem 2rem 2rem;
  text-align: center;
  background: linear-gradient(180deg, #f9fafb 0%, #ffffff 100%);
}

.logo {
  width: 120px;
  height: 120px;
  margin: 0 auto 1.5rem;
  object-fit: contain;
  filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.1));
}

.login-title {
  margin: 0 0 0.5rem;
  font-size: 1.75rem;
  font-weight: 700;
  color: #111827;
  letter-spacing: -0.025em;
}

.login-subtitle {
  margin: 0;
  font-size: 0.9375rem;
  color: #6B7280;
}

.login-body {
  padding: 2rem;
}

.alert {
  padding: 0.75rem 1rem;
  margin-bottom: 1.5rem;
  border-radius: 8px;
  font-size: 0.875rem;
}

.alert-success {
  background: #D1FAE5;
  color: #065F46;
  border: 1px solid #6EE7B7;
}

.alert-danger {
  background: #FEE2E2;
  color: #991B1B;
  border: 1px solid #FCA5A5;
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-label {
  display: block;
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
}

.form-control {
  width: 100%;
  padding: 0.75rem 1rem;
  font-size: 0.9375rem;
  border: 2px solid #E5E7EB;
  border-radius: 8px;
  transition: all 0.2s;
  font-family: inherit;
}

.form-control:focus {
  outline: none;
  border-color: #556b2f;
  box-shadow: 0 0 0 3px rgba(85, 107, 47, 0.1);
}

.form-control.is-invalid {
  border-color: #EF4444;
}

.invalid-feedback {
  display: block;
  margin-top: 0.5rem;
  font-size: 0.8125rem;
  color: #DC2626;
}

.form-check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
}

.form-check-input {
  width: 18px;
  height: 18px;
  margin: 0;
  cursor: pointer;
}

.form-check-label {
  font-size: 0.875rem;
  color: #6B7280;
  cursor: pointer;
  user-select: none;
}

.btn-primary {
  width: 100%;
  padding: 0.875rem 1.5rem;
  font-size: 1rem;
  font-weight: 600;
  color: white;
  background: linear-gradient(135deg, #556b2f 0%, #6B8E23 100%);
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 4px 12px rgba(85, 107, 47, 0.3);
}

.btn-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(85, 107, 47, 0.4);
  background: linear-gradient(135deg, #6B8E23 0%, #556b2f 100%);
}

.btn-primary:active {
  transform: translateY(0);
  box-shadow: 0 2px 8px rgba(85, 107, 47, 0.3);
}

.login-footer {
  padding: 1.5rem 2rem;
  text-align: center;
  background: #F9FAFB;
  border-top: 1px solid #E5E7EB;
}

.footer-text {
  margin: 0;
  font-size: 0.8125rem;
  color: #9CA3AF;
}

.footer-text svg {
  vertical-align: text-bottom;
  margin-right: 0.25rem;
}

/* Floating particles effect */
.particles {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  overflow: hidden;
  z-index: 0;
  pointer-events: none;
}

.particle {
  position: absolute;
  width: 4px;
  height: 4px;
  background: rgba(255, 255, 255, 0.15);
  border-radius: 50%;
  animation: float 15s infinite;
}

.particle:nth-child(1) { left: 10%; animation-delay: 0s; top: -10%; }
.particle:nth-child(2) { left: 30%; animation-delay: 2s; top: -20%; }
.particle:nth-child(3) { left: 50%; animation-delay: 4s; top: -15%; }
.particle:nth-child(4) { left: 70%; animation-delay: 6s; top: -25%; }
.particle:nth-child(5) { left: 90%; animation-delay: 8s; top: -10%; }

@keyframes float {
  to {
    transform: translateY(120vh) translateX(50px);
    opacity: 0;
  }
}
</style>

<!-- Floating particles background -->
<div class="particles">
  <div class="particle"></div>
  <div class="particle"></div>
  <div class="particle"></div>
  <div class="particle"></div>
  <div class="particle"></div>
</div>

<div class="login-container">
  <div class="login-card">
    <div class="login-header">
      <img src="{{ asset('assets/logo.png') }}" alt="Logo BSF" class="logo">
      <h1 class="login-title">Sistema de Archivos Policiales</h1>
      <p class="login-subtitle">Gestión documental</p>
    </div>

    <div class="login-body">
      @if (session('status'))
        <div class="alert alert-success">
          {{ session('status') }}
        </div>
      @endif

      @if ($errors->has('email'))
        <div class="alert alert-danger">
          {{ $errors->first('email') }}
        </div>
      @endif

      <form method="POST" action="{{ route('login.attempt') }}">
        @csrf

        <div class="form-group">
          <label for="email" class="form-label">Correo electrónico</label>
          <input 
            id="email" 
            type="email" 
            name="email"
            value="{{ old('email') }}" 
            required 
            autofocus
            placeholder="usuario@ejemplo.com"
            class="form-control @error('email') is-invalid @enderror"
          >
          @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        <div class="form-group">
          <label for="password" class="form-label">Contraseña</label>
          <input 
            id="password" 
            type="password" 
            name="password" 
            required
            placeholder="••••••••"
            class="form-control @error('password') is-invalid @enderror"
          >
          @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        <div class="form-check">
          <input 
            type="checkbox" 
            name="remember" 
            id="remember" 
            class="form-check-input"
            {{ old('remember') ? 'checked' : '' }}
          >
          <label class="form-check-label" for="remember">
            Mantener sesión iniciada
          </label>
        </div>

        <button type="submit" class="btn-primary">
          Iniciar sesión
        </button>
      </form>
    </div>

    <div class="login-footer">
      <p class="footer-text">
        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
          <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
        </svg>
        Acceso restringido · &copy; {{ date('Y') }} BSF
      </p>
    </div>
  </div>
</div>
@endsection
