import { createInertiaApp } from '@inertiajs/react';
import * as Sentry from '@sentry/react';
import { KoraToaster } from '@/components/ui/kora-toast';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AdministrationSettingsLayout from '@/layouts/administration-settings-layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PublicLegalLayout from '@/layouts/public-legal-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

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
