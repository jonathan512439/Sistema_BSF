public function destroy(Documento $documento, Request $request)
{
// 1. Verificar estado permitido
$estadosPermitidos = ['capturado', 'pendiente'];
if (!in_array($documento->estado, $estadosPermitidos)) {
return response()->json([
'ok' => false,
'error' => 'ESTADO_INVALIDO',
'message' => "No se puede eliminar un documento en estado '{$documento->estado}'. Solo se permiten documentos en estado
'capturado' o 'pendiente'."
], 422);
}

// 2. Validar razón de eliminación
$validated = $request->validate([
'razon' => 'required|string|min:10|max:255'
], [
'razon.required' => 'Debe proporcionar una razón para eliminar el documento',
'razon.min' => 'La razón debe tener al menos :min caracteres',
'razon.max' => 'La razón no puede exceder :max caracteres'
]);

try {
DB::transaction(function () use ($documento, $validated, $request) {
$actor = $this->resolveActor($request);

// Marcar documento como eliminado (soft delete)
$documento->deleted_at = now();
$documento->deleted_by = $actor?->id;
$documento->delete_reason = $validated['razon'];
$documento->save();

// También marcar archivos asociados como eliminados
$documento->archivos()->update([
'deleted_at' => now(),
'deleted_by' => $actor?->id,
'delete_reason' => $validated['razon']
]);

// Auditoría
try {
$this->audit->append(
evento: 'documento.delete',
actorId: $actor?->id,
objetoTipo: 'documento',
objetoId: $documento->id,
payload: [
'estado' => $documento->estado,
'razon' => $validated['razon'],
'titulo' => $documento->titulo,
'tipo' => $documento->tipo_documento_id
],
ip: $request->ip(),
userAgent: (string) $request->userAgent()
);
} catch (\Throwable $e) {
\Log::warning('Audit log failed: ' . $e->getMessage());
}
});

return response()->json([
'ok' => true,
'message' => 'Documento eliminado correctamente'
]);

} catch (\Illuminate\Validation\ValidationException $e) {
return response()->json([
'ok' => false,
'error' => 'VALIDATION',
'message' => 'Datos inválidos',
'errors' => $e->errors()
], 422);
} catch (\Throwable $e) {
\Log::error('Error al eliminar documento: ' . $e->getMessage());
return response()->json([
'ok' => false,
'error' => 'DELETE_FAIL',
'message' => 'Error al eliminar el documento: ' . $e->getMessage()
], 500);
}
}