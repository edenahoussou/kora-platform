import { KoraShell } from '@/components/kora-shell';

export default function AppSidebarLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    return <KoraShell>{children}</KoraShell>;
}
