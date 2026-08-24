import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { KoraSidebar } from '@/components/kora-sidebar';
import { KoraTopbar } from '@/components/kora-topbar';
import { cn } from '@/lib/utils';

type Props = {
    children: ReactNode;
};

/**
 * KORA OPS application shell — grid layout with a sticky navy sidebar
 * (264px) and a main content area with a sticky topbar.
 *
 * Reproduces the validated maquette at `docs/maquettes/kora-ops-web/`.
 */
export function KoraShell({ children }: Props) {
    const { auth } = usePage().props;
    const user = auth?.user ?? undefined;
    const [mobileOpen, setMobileOpen] = useState(false);
    const [sidebarCollapsed, setSidebarCollapsed] = useState(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        return window.localStorage.getItem('kora-sidebar-collapsed') === 'true';
    });

    useEffect(() => {
        window.localStorage.setItem(
            'kora-sidebar-collapsed',
            String(sidebarCollapsed),
        );
    }, [sidebarCollapsed]);

    return (
        <div
            className={cn(
                'kora-shell grid min-h-screen grid-cols-1 bg-background transition-[grid-template-columns] duration-300 lg:grid-cols-[264px_minmax(0,1fr)]',
                sidebarCollapsed && 'lg:grid-cols-[88px_minmax(0,1fr)]',
            )}
        >
            <KoraSidebar
                collapsed={sidebarCollapsed}
                mobileOpen={mobileOpen}
                onMobileClose={() => setMobileOpen(false)}
                onToggleCollapse={() => setSidebarCollapsed((value) => !value)}
            />
            <div className="kora-main flex min-h-screen flex-col">
                <KoraTopbar
                    user={user}
                    onMenuClick={() => setMobileOpen(true)}
                />
                <main className="flex-1 overflow-x-hidden p-[clamp(1rem,3vw,2rem)]">
                    {children}
                </main>
            </div>
        </div>
    );
}
