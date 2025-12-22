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
            background: linear-gradient(135deg, #2d5016 0%, #3d6b1f 100%);
            padding: 40px 20px;
            text-align: center;
        }

        .header-logo {
            margin-bottom: 20px;
        }

        .header-logo img {
            width: 100px;
            height: auto;
            filter: brightness(0) invert(1);
            /* Make logo white */
        }

        .header h1 {
            color: white;
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }

        .header p {
            color: rgba(255, 255, 255, 0.9);
            margin: 10px 0 0 0;
            font-size: 14px;
        }

        .content {
            padding: 40px 30px;
        }

        .greeting {
            font-size: 20px;
            color: #111827;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .message {
            font-size: 16px;
            color: #374151;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .button-container {
            text-align: center;
            margin: 40px 0;
        }

        .button {
            display: inline-block;
            padding: 18px 40px;
            background: linear-gradient(135deg, #2d5016 0%, #3d6b1f 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 12px rgba(45, 80, 22, 0.3);
            transition: all 0.2s;
        }

        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(45, 80, 22, 0.4);
        }

        .info-box {
            background-color: #f3f4f6;
            padding: 24px;
            border-radius: 8px;
            margin: 30px 0;
            border-left: 4px solid #2d5016;
        }

        .info-box h3 {
            margin: 0 0 15px 0;
            color: #2d5016;
            font-size: 16px;
            font-weight: 700;
        }

        .info-box p {
            margin: 8px 0;
            color: #374151;
            font-size: 15px;
        }

        .info-value {
            font-weight: 600;
            color: #111827;
        }

        .expiration {
            background-color: #fef3c7;
            padding: 16px 20px;
            border-left: 4px solid #f59e0b;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }

        .expiration p {
            margin: 0;
            color: #92400e;
            font-size: 14px;
            font-weight: 600;
        }

        .instructions {
            background-color: #e0f2fe;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #0284c7;
        }

        .instructions h3 {
            margin: 0 0 10px 0;
            color: #075985;
            font-size: 16px;
        }

        .instructions ol {
            margin: 10px 0;
            padding-left: 20px;
            color: #0c4a6e;
        }

        .instructions li {
            margin: 6px 0;
            font-size: 14px;
        }

        .role-info {
            background-color: #dcfce7;
            padding: 16px 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #16a34a;
        }

        .role-info p {
            margin: 0;
            color: #166534;
            font-size: 14px;
            line-height: 1.6;
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

        .footer strong {
            color: #2d5016;
        }

        .important {
            color: #dc2626;
            font-weight: 700;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e5e7eb, transparent);
            margin: 30px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="header-logo">
                <img src="{{ asset('assets/logo.png') }}" alt="Logo BSF">
            </div>
            <h1>Bienvenido al Sistema BSF</h1>
            <p>Gestión Documental y Custodia</p>
        </div>

        <div class="content">
            <p class="greeting">Hola <strong>{{ $user->name }}</strong>,</p>

            <p class="message">
                Se te ha creado una cuenta en el <strong>Sistema de Gestión Documental del Batallón de Seguridad
                    Física</strong>.
            </p>

            <div class="info-box">
                <h3>📋 Tus Credenciales de Acceso</h3>
                <p><strong>Usuario:</strong> <span class="info-value">{{ $user->username }}</span></p>
                <p><strong>Email:</strong> <span class="info-value">{{ $user->email }}</span></p>
                <p><strong>Rol Asignado:</strong> <span class="info-value">{{ ucfirst($user->role) }}</span></p>
            </div>

            @if($user->role === 'superadmin')
                <div class="role-info">
                    <p>
                        ✅ Como <strong>Superadministrador</strong>, tendrás acceso completo al sistema,
                        incluyendo la gestión de usuarios, configuración del sistema y todas las funcionalidades de
                        documentos.
                    </p>
                </div>
            @elseif($user->role === 'archivist')
                <div class="role-info">
                    <p>
                        ✅ Como <strong>Archivista</strong>, tendrás acceso completo a la gestión de documentos:
                        subir archivos, procesar OCR, validar, sellar custodia digital y gestionar ubicaciones físicas.
                    </p>
                </div>
            @elseif($user->role === 'reader')
                <div class="role-info">
                    <p>
                        ✅ Como <strong>Lector</strong>, tendrás acceso de consulta a documentos no confidenciales
                        con todas las funcionalidades de búsqueda y filtrado. Todos tus accesos quedan registrados en
                        auditoría.
                    </p>
                </div>
            @endif

            <div class="divider"></div>

            <div class="instructions">
                <h3>🔐 Pasos para Activar tu Cuenta</h3>
                <ol>
                    <li>Haz clic en el botón "Crear Contraseña" a continuación</li>
                    <li>Crea una contraseña segura (mínimo 8 caracteres, con mayúsculas, minúsculas, números y símbolos)
                    </li>
                    <li>Confirma tu contraseña</li>
                    <li>Tu cuenta será activada automáticamente y podrás acceder al sistema</li>
                </ol>
            </div>

            <div class="button-container">
                <a href="{{ $invitationUrl }}" class="button">
                    🔑 Crear Mi Contraseña
                </a>
            </div>

            <div class="expiration">
                <p>
                    ⏰ <span class="important">Este enlace expira el {{ $expiresAt->format('d/m/Y') }} a las
                        {{ $expiresAt->format('H:i') }}</span>
                </p>
            </div>

            <p class="message" style="font-size: 14px; color: #6b7280; margin-top: 30px;">
                Si no solicitaste esta cuenta o crees que este correo te llegó por error,
                puedes ignorarlo de forma segura. Si tienes dudas, contacta al administrador del sistema.
            </p>
        </div>

        <div class="footer">
            <p><strong>Sistema BSF</strong></p>
            <p>Batallón de Seguridad Física - Oruro, Bolivia</p>
            <p>Sistema de Gestión Documental y Custodia Digital</p>
            <p style="margin-top: 15px; font-size: 12px;">
                Este es un correo automático, por favor no respondas a este mensaje.
            </p>
        </div>
    </div>
</body>

</html>