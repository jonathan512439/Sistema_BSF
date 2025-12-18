import { createRouter, createWebHistory } from 'vue-router'
// Lazy load de componentes
const Dashboard = () => import('../components/Dashboard.vue')
const SeccionesView = () => import('../components/views/SeccionesView.vue')
const SubseccionesView = () => import('../components/views/SubseccionesView.vue')
const TiposDocumentoView = () => import('../components/views/TiposDocumentoView.vue')
const DocumentosFilteredView = () => import('../components/views/DocumentosFilteredView.vue')

const routes = [
    {
        path: '/dashboard',
        name: 'dashboard',
        component: Dashboard,
        meta: { requiresAuth: true }
    },
    {
        path: '/secciones',
        name: 'secciones',
        component: SeccionesView,
        meta: { requiresAuth: true }
    },
    {
        path: '/secciones/:seccionId/subsecciones',
        name: 'subsecciones',
        component: SubseccionesView,
        props: true,
        meta: { requiresAuth: true }
    },
    {
        path: '/tipos',
        name: 'tipos',
        component: TiposDocumentoView,
        meta: { requiresAuth: true }
    },
    {
        path: '/documentos',
        name: 'documentos-filtered',
        component: DocumentosFilteredView,
        meta: { requiresAuth: true }
    },
    {
        path: '/documentos/:id/versiones',
        name: 'document-versions',
        component: () => import('../components/views/DocumentVersionHistoryView.vue'),
        props: true,
        meta: { requiresAuth: true }
    },
    {
        path: '/documentos-eliminados',
        name: 'deleted-documents',
        component: () => import('../components/views/DeletedDocumentsView.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/',
        redirect: '/dashboard'
    }
]
const router = createRouter({
    history: createWebHistory(),
    routes
})
export default router