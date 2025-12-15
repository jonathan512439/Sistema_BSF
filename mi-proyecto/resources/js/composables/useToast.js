// Composable para gestión global de notificaciones Toast
// Permite mostrar notificaciones desde cualquier componente

import { ref } from 'vue'

// Estado global de notificaciones (compartido entre todos los componentes)
const toasts = ref([])
let nextId = 1

/**
 * Composable useToast
 * 
 * Uso en componentes:
 * ```js
 * import { useToast } from '@/composables/useToast'
 * const { success, error, warning, info } = useToast()
 * 
 * success('Documento subido correctamente')
 * error('No se pudo procesar OCR', 'Archivo PDF corrupto')
 * ```
 */
export function useToast() {
    /**
     * Muestra una notificación toast
     * @param {string} type - Tipo: 'success', 'error', 'warning', 'info'
     * @param {string} message - Mensaje principal
     * @param {string} detail - Detalle opcional (para errores técnicos)
     * @param {number} duration - Duración en ms (default: 5000)
     */
    function show(type, message, detail = '', duration = 5000) {
        const id = nextId++

        // Agregar toast al array global
        toasts.value.push({
            id,
            type,
            message,
            detail,
            createdAt: Date.now()
        })

        // Auto-eliminar después de la duración especificada
        if (duration > 0) {
            setTimeout(() => {
                remove(id)
            }, duration)
        }

        return id
    }

    /**
     * Elimina una notificación por ID
     */
    function remove(id) {
        const index = toasts.value.findIndex(t => t.id === id)
        if (index !== -1) {
            toasts.value.splice(index, 1)
        }
    }

    /**
     * Elimina todas las notificaciones
     */
    function clear() {
        toasts.value = []
    }

    // Métodos de conveniencia para cada tipo
    function success(message, detail = '') {
        return show('success', message, detail)
    }

    function error(message, detail = '') {
        return show('error', message, detail, 7000) // Errores duran más
    }

    function warning(message, detail = '') {
        return show('warning', message, detail)
    }

    function info(message, detail = '') {
        return show('info', message, detail)
    }

    return {
        toasts,      // Array reactivo de todas las notificaciones
        show,        // Función genérica
        success,     // Notificación de éxito (verde)
        error,       // Notificación de error (rojo)
        warning,     // Notificación de advertencia (amarillo)
        info,        // Notificación informativa (azul)
        remove,      // Eliminar una notificación específica
        clear        // Eliminar todas
    }
}
