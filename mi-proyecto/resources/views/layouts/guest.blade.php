<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'BSF')</title>

    {{-- Bootstrap CSS (ajusta según tu stack) --}}
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

    {{-- Aquí puedes agregar tu CSS propio --}}
    @stack('styles')
</head>
<body class="bg-light">

    <main>
        @yield('content')
    </main>

    {{-- Bootstrap JS (opcional) --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    @stack('scripts')
</body>
</html>
