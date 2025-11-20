<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\RbacController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\AuditController;
use App\Http\Middleware\AccessLog;


  // RBAC / Catálogos
  Route::get('/rbac/users', [RbacController::class, 'users']);
  Route::get('/catalogs',    [CatalogController::class, 'catalogs']);

  // Diagnóstico / contexto
  Route::get('/health',      [DocumentoController::class, 'health']);
  Route::get('/wm-context',  [DocumentoController::class, 'wmContext']);

  // Documentos
  Route::get('/documentos',                   [DocumentoController::class, 'index']);
  Route::post('/documentos/upload',           [DocumentoController::class, 'upload']);
  Route::post('/documentos/{documento}/validar', [DocumentoController::class, 'validar']);
  Route::post('/documentos/{documento}/sellar',  [DocumentoController::class, 'sellar']);
  // Mover ubicación física del documento
  Route::post('/documentos/{documento}/ubicacion', [DocumentoController::class, 'moverUbicacion']);
  //OCR
  Route::post('/documentos/{documento}/ocr', [DocumentoController::class, 'procesarOcr']);
  // Detalle de documento
  Route::get('/documentos/{documento}', [DocumentoController::class, 'show'])
    ->middleware(AccessLog::class);

  Route::get('/stream/{documento}/{accion?}', [DocumentoController::class, 'stream'])
    ->where('accion','view|download|print')
    ->middleware(AccessLog::class);
  Route::delete('/documentos/{documento}',           [DocumentoController::class, 'eliminar']);
  Route::post('/documentos/{documento}/restaurar',   [DocumentoController::class, 'restaurar']);


  // Auditoría
  Route::get('/audit/ledger/{documento}', [AuditController::class, 'ledger']);
  Route::get('/audit/access/{documento}', [AuditController::class, 'accessLog']);
