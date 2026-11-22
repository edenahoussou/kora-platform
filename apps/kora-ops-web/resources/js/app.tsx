import { createInertiaApp } from '@inertiajs/react';
import * as Sentry from '@sentry/react';
import { KoraToaster } from '@/components/ui/kora-toast';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AdministrationSettingsLayout from '@/layouts/administration-settings-layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PublicLegalLayout from '@/layouts/public-legal-layout';

const appName =
    document.querySelector<HTMLMetaElement>('meta[name="kora-brand"]')
        ?.content || 'Kora Express';

// Keep the retry marker across reloads of the same Vite entry.
window.addEventListener('vite:preloadError', (event) => {
    const storageKey = `kora_vite_preload_reloaded:${import.meta.url}`;

    try {
        if (window.sessionStorage.getItem(storageKey) === '1') {
            return;
        }

        window.sessionStorage.setItem(storageKey, '1');
    } catch {
        // Reloading without a persistent guard could create a loop.
        return;
    }

    event.preventDefault();
    window.location.reload();
});

if (import.meta.env.VITE_SENTRY_DSN) {
    Sentry.init({
        dsn: import.meta.env.VITE_SENTRY_DSN,
        environment: import.meta.env.VITE_APP_ENV,
        release: import.meta.env.VITE_SENTRY_RELEASE,
        dataCollection: {
            userInfo: false,
            cookies: false,
            httpHeaders: false,
            urlQueryParams: false,
            httpBodies: [],
        },
        integrations: [Sentry.browserTracingIntegration()],
        tracesSampleRate: 0.1,
    });
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name === 'kora-points/preview':
                return null;
            case name.startsWith('public/'):
                return PublicLegalLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('administration/settings'):
                return AdministrationSettingsLayout;
            case name.startsWith('settings/'):
                return AppLayout;
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <KoraToaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
