import '../css/app.css'
import './bootstrap'

import { createInertiaApp, router } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { createApp, h } from 'vue'
import { ZiggyVue } from 'ziggy-js'
import { applyTheme } from './Composables/useTheme.js'

const appName = import.meta.env.VITE_APP_NAME || 'Bubbles'

function updateBodyBackground(component) {
    const isProfilePage = component?.startsWith('Profile/')
    document.body.classList.toggle('no-profile-background', isProfilePage)
}

function getStoredTheme() {
    try { return localStorage.getItem('bubbles_theme'); } catch (_) { return null; }
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)

        updateBodyBackground(props.initialPage?.component ?? props.page?.component)

        // Apply theme: server value takes priority, fallback to localStorage
        const serverTheme = props.initialPage?.props?.auth?.user?.theme
        applyTheme(serverTheme ?? getStoredTheme() ?? 'light')

        // Keep in sync on every Inertia navigation (e.g. after profile update)
        router.on('navigate', (e) => {
            updateBodyBackground(e.detail.page.component)
            const theme = e.detail.page.props?.auth?.user?.theme
            if (theme) applyTheme(theme)
        })

        return app.mount(el)
    },
    progress: {
        color: '#009ac7',
    },
})
