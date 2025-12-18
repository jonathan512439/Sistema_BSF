<?php

namespace App\Services;

class EventTranslator
{
    /**
     * Traducir eventos técnicos a español
     */
    public static function translate($event)
    {
        $translations = [
            // Eventos de documentos
            'documento.create' => 'Documento creado',
            'documento.created' => 'Documento creado',
            'documento.upload' => 'Documentos subidos',
            'documento.uploaded' => 'Documentos subidos',
            'documento.update' => 'Documento modificado',
            'documento.updated' => 'Documento modificado',
            'documento.delete' => 'Documento eliminado',
            'documento.deleted' => 'Documento eliminado',
            'documento.view' => 'Documento visualizado',
            'documento.viewed' => 'Documento visualizado',
            'documento.download' => 'Documento descargado',
            'documento.downloaded' => 'Documento descargado',
            'documento.print' => 'Documento impreso',
            'documento.printed' => 'Documento impreso',
            'documento.move' => 'Documento movido',
            'documento.moved' => 'Documento movido',
            'documento.validated' => 'Documento validado',
            'documento.validado' => 'Documento validado',
            'documento.sealed' => 'Documento sellado',
            'documento.sellado' => 'Documento sellado',
            'documento.restored' => 'Documento restaurado',
            'documento.restaurado' => 'Documento restaurado',
            'documento.metadata_updated' => 'Metadatos modificados',
            'documento.version.paginas_agregadas' => 'Páginas agregadas a documento',

            // Eventos de usuarios
            'user.login' => 'Inicio de sesión',
            'user.logout' => 'Cierre de sesión',
            'user.created' => 'Usuario creado',
            'user.updated' => 'Usuario modificado',
            'user.deleted' => 'Usuario eliminado',
            'user.invited' => 'Usuario invitado',
            'user.activated' => 'Usuario activado',
            'user.deactivated' => 'Usuario desactivado',

            // Eventos de certificación
            'certificacion.generated' => 'Certificación generada',
            'certificacion.printed' => 'Certificación impresa',

            // Eventos de legal hold
            'legalhold.activated' => 'Retención legal activada',
            'legalhold.removed' => 'Retención legal removida',

            // Eventos de ubicación
            'ubicacion.changed' => 'Ubicación cambiada',

            // Estados
            'capturado' => 'Capturado',
            'pendiente' => 'Pendiente',
            'revisado' => 'Revisado',
            'aprobado' => 'Aprobado',
            'archivado' => 'Archivado',
            'eliminado' => 'Eliminado',
        ];

        if (isset($translations[$event])) {
            return $translations[$event];
        }

        // Si no hay traducción, capitalizar y reemplazar puntos/guiones
        return ucfirst(str_replace(['.', '_', '-'], ' ', $event));
    }
}
