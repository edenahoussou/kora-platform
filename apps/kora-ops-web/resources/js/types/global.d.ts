import type { Auth } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            companyPortal: {
                uuid: string;
                name: string;
                reference: string;
            } | null;
            portalOnly: boolean;
            sidebarOpen: boolean;
            mapConfig: {
                provider: 'openstreetmap' | 'google';
                osm: { tileUrl: string; attribution: string };
                google: { apiKey: string | null };
            };
            [key: string]: unknown;
        };
    }
}
