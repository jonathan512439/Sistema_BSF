<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'BSF')</title>

    {{-- CSRF Token for JavaScript --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- 1) Carga CSS global de tu aplicación (Tailwind/lo que tengas) --}}
    @vite(['resources/css/app.css'])

    {{-- 2) Bootstrap opcional (si lo sigues usando) --}}
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

    @stack('styles')
</head>
<body class="bg-light">

    <div id="app">
        <AppTopbar />
    </div>
    <main>
        @yield('content')
    </main>

    {{-- 3) Carga JS de Vue DESPUÉS del contenido, para que #app ya exista --}}
    @vite(['resources/js/app.js'])

    {{-- 4) Bootstrap JS opcional --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    @stack('scripts')
</body>
</html>
