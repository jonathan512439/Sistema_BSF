<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>BSF — Documentos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  @vite('resources/js/app.js')
  <style>
    :root{
      --olive:#556B2F; --olive-600:#4b5f2a; --olive-200:#cdd6b3;
      --bg:#f7f7f4; --card:#ffffff; --muted:#6b7280;
    }
    body{margin:0;background:var(--bg);color:#111827;font-family:ui-sans-serif,system-ui}
    .container{max-width:1100px;margin:0 auto;padding:16px}
    .card{background:var(--card);border:1px solid #e5e7eb;border-radius:12px;padding:14px}
    .btn{padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:.5rem;background:#f9fafb;cursor:pointer}
    .btn.olive{background:var(--olive);color:#fff;border-color:var(--olive-600)}
    .title{color:var(--olive);font-weight:700}
    .badge{display:inline-block;background:var(--olive-200);color:#1f2937;border-radius:10px;padding:.1rem .5rem;margin-left:.25rem}
    .row{display:flex;gap:.75rem;align-items:center;flex-wrap:wrap}
    .grid{display:grid;gap:.75rem}
    .muted{color:var(--muted)}
  </style>
</head>
<body>
  <div id="app"></div>
</body>
</html>
