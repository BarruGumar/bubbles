import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

function updateBodyBackground(component) {
    const isProfilePage = component?.startsWith('Profile/');
    document.body.classList.toggle('no-profile-background', isProfilePage);
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);

        app.mixin({
            mounted() {
                updateBodyBackground(this.$page?.component);
            },
            updated() {
                updateBodyBackground(this.$page?.component);
            },
        });

        updateBodyBackground(props.initialPage?.component ?? props.page?.component);

        return app.mount(el);
    },
    progress: {
        color: '#009ac7',
    },
});
