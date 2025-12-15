<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\SpaController;

/*
|--------------------------------------------------------------------------
| Web Routes - RBAC Implementado
|--------------------------------------------------------------------------
|
| Rutas organizadas para:
| - Invitaciones (guest)
| - Login/Logout
| - SPA protegida con roles
|
*/

// ============================================================================
// INVITACIÓN - Sin autenticación (token en URL)
// ============================================================================

Route::middleware('guest')->group(function () {
    // Mostrar formulario de cambio de contraseña para usuarios invitados
    Route::get('/invitation/{token}', [InvitationController::class, 'show'])
        ->name('invitation.show');

    // Procesar cambio de contraseña
    Route::post('/invitation/{token}', [InvitationController::class, 'process'])
        ->name('invitation.process');
});

// ============================================================================
// LOGIN / LOGOUT
// ============================================================================

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])
        ->name('login');

    Route::post('/login', [LoginController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.attempt');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ============================================================================
// SPA - Protegida por autenticación
// ============================================================================

Route::middleware(['auth', 'force.password', 'superadmin.check'])->group(function () {
    // Shell del SPA Vue (Dashboard dinámico por rol)
    Route::get('/', [SpaController::class, 'index'])->name('spa');
    Route::get('/app', [SpaController::class, 'index'])->name('app');

    // Alias para compatibilidad
    Route::get('/dashboard', function () {
        return redirect()->route('spa');
    })->name('dashboard');

    // Endpoint para obtener datos del usuario actual
    Route::get('/api/me', [SpaController::class, 'me'])->name('api.me');

    // Catálogos - Necesarios para todos los roles
    Route::get('/api/catalogs', [\App\Http\Controllers\CatalogController::class, 'catalogs'])->name('api.catalogs');

    // Health check y contexto
    Route::get('/api/health', [\App\Http\Controllers\DocumentoController::class, 'health'])->name('api.health');
    Route::get('/api/wm-context', [\App\Http\Controllers\DocumentoController::class, 'wmContext'])->name('api.wmcontext');
});

// ====================================================================================
// API ROUTES - RBAC (en web.php para compartir sesión)
// ====================================================================================

// SUPERADMIN - Gestión de usuarios
Route::middleware(['auth', 'force.password', 'role:superadmin'])
    ->prefix('api/admin')
    ->name('api.admin.')
    ->group(function () {
        Route::get('/users', [\App\Http\Controllers\Admin\UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [\App\Http\Controllers\Admin\UserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{id}', [\App\Http\Controllers\Admin\UserManagementController::class, 'show'])->name('users.show');
        Route::put('/users/{id}', [\App\Http\Controllers\Admin\UserManagementController::class, 'update'])->name('users.update');
        Route::delete('/users/{id}', [\App\Http\Controllers\Admin\UserManagementController::class, 'destroy'])->name('users.delete');
        Route::post('/users/{id}/restore', [\App\Http\Controllers\Admin\UserManagementController::class, 'restore'])->name('users.restore');
        Route::post('/users/{id}/resend-invitation', [\App\Http\Controllers\Admin\UserManagementController::class, 'resendInvitation'])->name('users.resend');
    });

// ARCHIVISTA - Gestión completa de documentos
Route::middleware(['auth', 'force.password', 'role:archivist'])
    ->prefix('api')
    ->name('api.docs.')
    ->group(function () {
        // Generador de PDF desde imágenes
        Route::post('/generate-pdf', [\App\Http\Controllers\PDFGeneratorController::class, 'generate'])->name('generate-pdf');
        Route::post('/generate-pdf/cleanup', [\App\Http\Controllers\PDFGeneratorController::class, 'cleanup'])->name('generate-pdf.cleanup');

        // Crear nuevo documento (RESTful POST /api/documentos)
        Route::post('/documentos', [\App\Http\Controllers\DocumentoController::class, 'upload'])->name('create');
        Route::post('/documentos/upload', [\App\Http\Controllers\DocumentoController::class, 'upload'])->name('upload'); // Compatibilidad
        Route::post('/documentos/{documento}/validar', [\App\Http\Controllers\DocumentoController::class, 'validar'])->name('validar');
        Route::post('/documentos/{documento}/sellar', [\App\Http\Controllers\DocumentoController::class, 'sellar'])->name('sellar');
        Route::delete('/documentos/{documento}', [\App\Http\Controllers\DocumentoController::class, 'eliminar'])->name('delete');
        Route::post('/documentos/{documento}/restaurar', [\App\Http\Controllers\DocumentoController::class, 'restaurar'])->name('restore');
        Route::post('/documentos/{documento}/ocr', [\App\Http\Controllers\DocumentoController::class, 'procesarOcr'])->name('ocr');
        Route::post('/documentos/{documento}/ubicacion', [\App\Http\Controllers\DocumentoController::class, 'moverUbicacion'])->name('ubicacion');

        // OCR Preview (antes de subir documento)
        Route::post('/ocr/preview', [\App\Http\Controllers\DocumentoController::class, 'ocrPreview'])->name('ocr.preview');

        // Certificaciones
        Route::post('/certificaciones', [\App\Http\Controllers\CertificacionController::class, 'store'])->name('certificaciones.store');
        Route::put('/certificaciones/{id}', [\App\Http\Controllers\CertificacionController::class, 'update'])->name('certificaciones.update');
        Route::get('/certificaciones/all', [\App\Http\Controllers\CertificacionController::class, 'listAll'])->name('certificaciones.all');
        Route::get('/documentos/{documento}/certificaciones', [\App\Http\Controllers\CertificacionController::class, 'index'])->name('certificaciones.index');

        // Legal Holds
        Route::post('/documentos/{documento}/hold', [\App\Http\Controllers\LegalHoldController::class, 'store'])->name('hold.store');
        Route::delete('/documentos/{documento}/hold', [\App\Http\Controllers\LegalHoldController::class, 'destroy'])->name('hold.destroy');

        // Categories for Dashboard Navigation
        Route::get('/categories/secciones', [\App\Http\Controllers\CategoryController::class, 'getSecciones'])->name('categories.secciones');
        Route::get('/categories/secciones/{seccionId}/subsecciones', [\App\Http\Controllers\CategoryController::class, 'getSubsecciones'])->name('categories.subsecciones');
        Route::get('/categories/tipos-documento', [\App\Http\Controllers\CategoryController::class, 'getTiposDocumento'])->name('categories.tipos');
        Route::get('/categories/stats', [\App\Http\Controllers\CategoryController::class, 'getStats'])->name('categories.stats');
    });

// READER + ARCHIVISTA - Visualización de documentos
Route::middleware(['auth', 'force.password', 'role:reader,archivist'])
    ->prefix('api')
    ->name('api.docs.')
    ->group(function () {
        // Estadísticas para dashboard
        Route::get('/documentos/statistics', [\App\Http\Controllers\DocumentoController::class, 'getStatistics'])->name('statistics');

        // Listado de documentos (con filtros avanzados)
        Route::get('/documentos', [\App\Http\Controllers\DocumentoController::class, 'index'])->name('index');

        // Thumbnail DISABLED - thumbnails no funcionan correctamente
        // Route::get('/documentos/{documento}/thumbnail', [\App\Http\Controllers\DocumentoController::class, 'getThumbnail'])->name('thumbnail');
    
        // Ver detalle de documento (filtrado automático por confidencialidad)
        Route::get('/documentos/{documento}', [\App\Http\Controllers\DocumentoController::class, 'show'])
            ->middleware(\App\Http\Middleware\AccessLog::class)
            ->name('show');

        // Actualizar campos del documento
        Route::put('/documentos/{documento}', [\App\Http\Controllers\DocumentoController::class, 'update'])
            ->name('update');

        // Streaming (view/download/print) - filtrado automático por confidencialidad
        Route::get('/stream/{documento}/{accion?}', [\App\Http\Controllers\DocumentoController::class, 'stream'])
            ->where('accion', 'view|download|print')
            ->middleware(\App\Http\Middleware\AccessLog::class)
            ->name('stream');

        // Auditoría
        Route::get('/audit/logs', [\App\Http\Controllers\AuditController::class, 'index'])->name('audit.logs');
        Route::get('/audit/logs/{id}', [\App\Http\Controllers\AuditController::class, 'show'])->name('audit.show');
        Route::get('/audit/export', [\App\Http\Controllers\AuditController::class, 'export'])->name('audit.export');

        // Legacy audit routes
        Route::get('/documentos/{documento}/ledger', [\App\Http\Controllers\AuditController::class, 'ledger'])->name('ledger');
        Route::get('/documentos/{documento}/access-log', [\App\Http\Controllers\AuditController::class, 'accessLog'])->name('access_log');
    });

// Audit - Para superadmin y archivist
Route::middleware(['auth', 'force.password', 'role:superadmin,archivist'])
    ->prefix('api/audit')
    ->name('api.audit.')
    ->group(function () {
        Route::get('/logs', [\App\Http\Controllers\AuditController::class, 'logs'])->name('logs');
        Route::get('/comprehensive', [\App\Http\Controllers\AuditController::class, 'comprehensive'])->name('comprehensive');
        Route::get('/stats', [\App\Http\Controllers\AuditController::class, 'stats'])->name('stats');
        Route::get('/export', [\App\Http\Controllers\AuditController::class, 'export'])->name('export');
    });

// Blockchain Anchors - Para superadmin y archivist
Route::middleware(['auth', 'force.password', 'role:superadmin,archivist'])
    ->prefix('api/anchors')
    ->name('api.anchors.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\AnchorController::class, 'index'])->name('index');
        Route::get('/stats', [\App\Http\Controllers\AnchorController::class, 'stats'])->name('stats');
        Route::get('/config', [\App\Http\Controllers\AnchorController::class, 'getConfig'])->name('config.get');
        Route::get('/{id}', [\App\Http\Controllers\AnchorController::class, 'show'])->name('show');
        Route::post('/verify', [\App\Http\Controllers\AnchorController::class, 'verify'])->name('verify');

        // Solo superadmin puede crear y configurar
        Route::middleware('role:superadmin')->group(function () {
            Route::post('/create', [\App\Http\Controllers\AnchorController::class, 'create'])->name('create');
            Route::put('/config', [\App\Http\Controllers\AnchorController::class, 'updateConfig'])->name('config.update');
        });
    });


// Ubicaciones Físicas - Para archivist
Route::middleware(['auth', 'force.password', 'role:archivist'])
    ->prefix('api/ubicaciones')
    ->name('api.ubicaciones.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\UbicacionFisicaController::class, 'index'])->name('index');
        Route::get('/tree', [\App\Http\Controllers\UbicacionFisicaController::class, 'tree'])->name('tree');
        Route::get('/stats', [\App\Http\Controllers\UbicacionFisicaController::class, 'stats'])->name('stats');
        Route::get('/{id}', [\App\Http\Controllers\UbicacionFisicaController::class, 'show'])->name('show');
        Route::get('/{id}/documentos', [\App\Http\Controllers\UbicacionFisicaController::class, 'documentos'])->name('documentos');
        Route::post('/', [\App\Http\Controllers\UbicacionFisicaController::class, 'store'])->name('store');
        Route::put('/{id}', [\App\Http\Controllers\UbicacionFisicaController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\UbicacionFisicaController::class, 'destroy'])->name('destroy');
    });


// READER + ARCHIVISTA - Reportes (moved from previous block)
Route::middleware(['auth', 'force.password', 'role:reader,archivist'])
    ->prefix('api')
    ->name('api.docs.')
    ->group(function () {
        Route::get('/reports/types', [\App\Http\Controllers\ReportController::class, 'types'])->name('reports.types');
        Route::post('/reports/generate', [\App\Http\Controllers\ReportController::class, 'generate'])->name('reports.generate');
    });

// ============================================================================
// RUTAS PARA SUPERADMIN (gestión de usuarios vía web si es necesario)
// ============================================================================

Route::middleware(['auth', 'force.password', 'role:superadmin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Si necesitas vistas blade para admin, agrégalas aquí
        // Por ahora todo es API + Vue SPA
    });

// ============================================================================
// CATCH-ALL ROUTE - SPA (Vue Router)
// ============================================================================
// IMPORTANTE: Esta ruta DEBE estar al FINAL
// Sirve el SPA de Vue para todas las rutas no definidas arriba
// Esto permite que Vue Router maneje rutas como /secciones, /tipos, etc.

Route::middleware(['auth', 'force.password'])
    ->get('/{any}', [SpaController::class, 'index'])
    ->where('any', '.*')
    ->name('spa');
