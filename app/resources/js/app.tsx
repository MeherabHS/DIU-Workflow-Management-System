import '../css/app.css';
import './bootstrap';

import AppErrorBoundary from '@/Components/WorkManagement/AppErrorBoundary';
import AppLoadingFallback from '@/Components/WorkManagement/AppLoadingFallback';
import AppNavigationIndicator from '@/Components/WorkManagement/AppNavigationIndicator';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        const pageProps = props.initialPage.props as { auth?: { user?: unknown } };
        const isAuthenticated = Boolean(pageProps.auth?.user);

        root.render(
            <AppErrorBoundary homeHref={isAuthenticated ? '/dashboard' : '/login'} homeLabel={isAuthenticated ? 'Go to Dashboard' : 'Go to Login'}>
                <AppNavigationIndicator />
                <App {...props} />
            </AppErrorBoundary>,
        );
    },
    progress: {
        color: '#1D4ED8',
        showSpinner: false,
    },
}).catch((error) => {
    if (import.meta.env.DEV) {
        console.error('DIUS app failed to mount.', error);
    }

    const el = document.getElementById('app');
    if (el) {
        createRoot(el).render(
            <AppLoadingFallback
                title="Something needs a quick refresh."
                message="We're wiring things together. Please refresh the page or try again in a moment."
            />,
        );
    }
});