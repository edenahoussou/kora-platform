import { KoraShell } from '@/components/kora-shell';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return <KoraShell breadcrumbs={breadcrumbs}>{children}</KoraShell>;
}
