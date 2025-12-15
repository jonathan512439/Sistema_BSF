<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitación al Sistema BSF</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f3f4f6;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 20px;
            text-align: center;
        }
        
        .header h1 {
            color: white;
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .greeting {
            font-size: 18px;
            color: #111827;
            margin-bottom: 20px;
        }
        
        .message {
            font-size: 16px;
            color: #374151;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .button-container {
            text-align: center;
            margin: 40px 0;
        }
        
        .button {
            display: inline-block;
            padding: 16px 32px;
           background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s;
        }
        
        .button:hover {
            transform: translateY(-2px);
        }
        
        .info-box {
            background-color: #f3f4f6;
            padding: 20px;
            border-radius: 8px;
            margin: 30px 0;
        }
        
        .info-box h3 {
            margin: 0 0 10px 0;
            color: #111827;
            font-size: 16px;
        }
        
        .info-box p {
            margin: 5px 0;
            color: #374151;
            font-size: 14px;
        }
        
        .expiration {
            background-color: #fef3c7;
            padding: 15px;
            border-left: 4px solid #f59e0b;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        
        .expiration p {
            margin: 0;
            color: #92400e;
            font-size: 14px;
        }
        
        .footer {
            background-color: #f9fafb;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        
        .footer p {
            margin: 5px 0;
            color: #6b7280;
            font-size: 14px;
        }
        
        .important {
            color: #dc2626;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Invitación al Sistema BSF</h1>
        </div>
        
        <div class="content">
            <p class="greeting">Hola <strong>{{ $user->name }}</strong>,</p>
            
            <p class="message">
                Se te ha creado una cuenta en el <strong>Sistema de Gestión Documental BSF</strong> 
                con el rol de <strong>{{ ucfirst($user->role) }}</strong>.
            </p>
            
            <p class="message">
                Para activar tu cuenta, por favor haz clic en el siguiente botón para establecer tu contraseña:
            </p>
            
            <div class="button-container">
                <a href="{{ $invitationUrl }}" class="button">
                    Establecer Contraseña
                </a>
            </div>
            
            <div class="expiration">
                <p>
                    ⏰ <span class="important">Este enlace expira el {{ $expiresAt->format('d/m/Y') }} a las {{ $expiresAt->format('H:i') }}</span>
                </p>
            </div>
            
            <div class="info-box">
                <h3>📋 Tus credenciales de acceso:</h3>
                <p><strong>Usuario:</strong> {{ $user->username }}</p>
                <p><strong>Email:</strong> {{ $user->email }}</p>
                <p><strong>Rol:</strong> {{ ucfirst($user->role) }}</p>
            </div>
            
            <p class="message">
                Una vez que establezcas tu contraseña, podrás acceder al sistema con tu usuario y contraseña.
            </p>
            
            @if($user->role === 'superadmin')
                <p class="message">
                    Como <strong>Superadministrador</strong>, tendrás acceso completo a la gestión de usuarios del sistema.
                </p>
            @elseif($user->role === 'archivist')
                <p class="message">
                    Como <strong>Archivista</strong>, tendrás acceso completo a la gestión de documentos: 
                    subir, procesar OCR, validar, sellar custodia y gestionar ubicaciones físicas.
                </p>
            @elseif($user->role === 'reader')
                <p class="message">
                    Como <strong>Lector</strong>, tendrás acceso de solo lectura a los documentos no confidenciales 
                    y a los registros de auditoría.
                </p>
            @endif
            
            <p class="message" style="font-size: 14px; color: #6b7280;">
                Si no solicitaste esta cuenta o crees que este correo te llegó por error, 
                puedes ignorarlo de forma segura.
            </p>
        </div>
        
        <div class="footer">
            <p><strong>Sistema BSF</strong></p>
            <p>Sistema de Gestión Documental y Custodia</p>
            <p style="margin-top: 15px;">
                Este es un correo automático, por favor no respondas a este mensaje.
            </p>
        </div>
    </div>
</body>
</html>
