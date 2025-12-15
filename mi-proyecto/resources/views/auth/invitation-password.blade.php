<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Invitación - Sistema BSF</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
            padding: 2.5rem;
        }
        
        h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            text-align: center;
            margin-bottom: 0.5rem;
        }
        
        .subtitle {
            text-align: center;
            color: #6b7280;
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #374151;
        }
        
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .requirements {
            margin-top: 0.75rem;
            font-size: 0.875rem;
        }
        
        .requirement {
            color: #9ca3af;
            margin: 0.25rem 0;
        }
        
        .error {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
        }
        
        .info-box {
            background-color: #f3f4f6;
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bienvenido al Sistema BSF</h1>
        <p class="subtitle">Hola, <strong>{{ $user->name }}</strong></p>
        <p class="subtitle">Establece tu contraseña para activar tu cuenta</p>
        
        @if ($errors->any())
            <div class="error">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif
        
        <form method="POST" action="{{ route('invitation.process', ['token' => $token]) }}">
            @csrf
            
            <div class="form-group">
                <label for="password">Nueva Contraseña</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    minlength="8"
                    placeholder="Mínimo 8 caracteres"
                >
                <div class="requirements">
                    <div class="requirement">• Mínimo 8 caracteres</div>
                    <div class="requirement">• Al menos una mayúscula</div>
                    <div class="requirement">• Al menos una minúscula</div>
                    <div class="requirement">• Al menos un número</div>
                    <div class="requirement">• Al menos un símbolo (@$!%*#?&)</div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password_confirmation">Confirmar Contraseña</label>
                <input 
                    type="password" 
                    id="password_confirmation" 
                    name="password_confirmation" 
                    required
                    placeholder="Repite tu contraseña"
                >
            </div>
            
            <button type="submit" class="submit-btn">
                Activar Cuenta
            </button>
            
            <div class="info-box">
                <p><strong>Tu rol:</strong> {{ ucfirst($user->role) }}</p>
                <p style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">
                    Después de establecer tu contraseña, serás redirigido automáticamente.
                </p>
            </div>
        </form>
    </div>
</body>
</html>
