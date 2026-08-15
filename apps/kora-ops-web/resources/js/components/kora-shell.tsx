import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { KoraSidebar } from '@/components/kora-sidebar';
import { KoraTopbar } from '@/components/kora-topbar';
import type { BreadcrumbItem } from '@/types';

type Props = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

/**
 * KORA OPS application shell — grid layout with a sticky navy sidebar
 * (264px) and a main content area with a sticky topbar.
 *
 * Reproduces the validated maquette at `docs/maquettes/kora-ops-web/`.
 */
export function KoraShell({ children, breadcrumbs = [] }: Props) {
    const { auth } = usePage().props;
    const user = auth?.user ?? undefined;
    const [mobileOpen, setMobileOpen] = useState(false);

    return (
        <div className="kora-shell grid min-h-screen grid-cols-1 bg-background lg:grid-cols-[264px_minmax(0,1fr)]">
            <KoraSidebar
                mobileOpen={mobileOpen}
                onMobileClose={() => setMobileOpen(false)}
            />
            <div className="kora-main flex min-h-screen flex-col">
                <KoraTopbar
                    user={user}
                    onMenuClick={() => setMobileOpen(true)}
                />
                <main className="flex-1 overflow-x-hidden p-[clamp(1rem,3vw,2rem)]">
                    {breadcrumbs.length > 0 && (
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            {breadcrumbs.map((crumb, i) => (
                                <span key={i}>
                                    {i > 0 && ' / '}
                                    {crumb.title}
                                </span>
                            ))}
                        </div>
                    )}
                    {children}
                </main>
            </div>
        </div>
    );
}
