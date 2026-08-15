import { Link, usePage } from '@inertiajs/react';
import {
    Bell,
    Bus,
    FileText,
    LayoutDashboard,
    Receipt,
    Settings,
    Ticket,
    Users,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type NavEntry = {
    title: string;
    href: string;
    icon: typeof LayoutDashboard;
    children?: { title: string; href: string }[];
};

const navItems: NavEntry[] = [
    {
        title: 'Accueil',
        href: dashboard().url,
        icon: LayoutDashboard,
    },
    {
        title: 'Vente guichet',
        href: '/sale',
        icon: Ticket,
    },
    {
        title: 'Caisse',
        href: '/cash',
        icon: Wallet,
    },
    {
        title: 'Exploitation',
        href: '/operations',
        icon: Bus,
        children: [
            { title: 'Départs', href: '/departures' },
            { title: 'Embarquement', href: '/boarding' },
            { title: 'Manifestes', href: '/manifest' },
            { title: 'Lignes & arrêts', href: '/network' },
            { title: 'Tarifs', href: '/fares' },
        ],
    },
    {
        title: 'Passagers',
        href: '/passengers',
        icon: Users,
    },
    {
        title: 'Billets',
        href: '/tickets',
        icon: FileText,
    },
    {
        title: 'Rapports',
        href: '/reports',
        icon: Receipt,
    },
    {
        title: 'Administration',
        href: '/administration',
        icon: Settings,
        children: [
            { title: 'Utilisateurs', href: '/users' },
            { title: 'Rôles', href: '/roles' },
            { title: 'Paramètres', href: '/settings' },
            { title: 'Audit', href: '/audit' },
        ],
    },
];

export function KoraSidebar({
    mobileOpen,
    onMobileClose,
}: {
    mobileOpen: boolean;
    onMobileClose: () => void;
}) {
    const { isCurrentUrl } = useCurrentUrl();
    const { auth } = usePage().props;
    const [openGroups, setOpenGroups] = useState<string[]>([]);

    const toggleGroup = (title: string) => {
        setOpenGroups((prev) =>
            prev.includes(title)
                ? prev.filter((t) => t !== title)
                : [...prev, title],
        );
    };

    const isActive = (href: string) =>
        isCurrentUrl(href) ||
        (href !== dashboard().url && typeof window !== 'undefined' && window.location.pathname.startsWith(href));

    const sidebarContent = (
        <>
            {/* Brand */}
            <div className="kora-side-brand relative mx-1 mb-7 border-b border-white/10 pb-3.5 pt-1.5">
                <Link href={dashboard()} className="block">
                    <img
                        src="/kora-logo-sidebar.png"
                        alt="KORA EXPRESS"
                        className="kora-side-logo block h-[5rem] w-full object-contain object-left drop-shadow-[0_10px_19px_rgba(0,29,78,0.32)]"
                    />
                </Link>
                <span className="mt-1.5 block text-[0.5625rem] font-black uppercase tracking-[0.22em] text-white/60">
                    KORA OPS
                </span>
                <i className="absolute bottom-[-1px] left-0 h-0.5 w-12 rounded bg-gold" />
            </div>

            {/* Workspace label */}
            <div className="mx-2 mb-3 text-[0.625rem] font-black uppercase tracking-[0.13em] text-white/35">
                Espace de travail
            </div>

            {/* Navigation */}
            <nav
                className="flex flex-1 flex-col gap-[3px] overflow-y-auto"
                aria-label="Navigation principale"
            >
                {navItems.map((item) => {
                    const active = isActive(item.href);

                    if (item.children) {
                        const isOpen = openGroups.includes(item.title);
                        const hasActiveChild = item.children.some((c) =>
                            isActive(c.href),
                        );

                        return (
                            <div key={item.title} className="mt-1.5">
                                <button
                                    type="button"
                                    onClick={() => toggleGroup(item.title)}
                                    className={cn(
                                        'flex min-h-[2.6875rem] w-full items-center gap-3 rounded-[0.6875rem] border-0 px-3 text-left text-[0.8125rem] font-bold transition-colors',
                                        isOpen || hasActiveChild
                                            ? 'bg-white/10 text-white'
                                            : 'text-white/75 hover:bg-white/10 hover:text-white',
                                    )}
                                >
                                    <span className="grid size-[1.3125rem] shrink-0 place-items-center rounded-[0.4375rem] bg-gold/10 text-[0.6875rem] text-gold">
                                        <item.icon className="size-3.5" />
                                    </span>
                                    {item.title}
                                    <span
                                        className={cn(
                                            'ml-auto text-white/35 transition-transform',
                                            isOpen && 'rotate-180',
                                        )}
                                    >
                                        ⌄
                                    </span>
                                </button>
                                {isOpen && (
                                    <div className="mx-0 my-[3px] mb-1.5 ml-8 flex flex-col gap-0.5">
                                        {item.children.map((child) => (
                                            <Link
                                                key={child.href}
                                                href={child.href}
                                                className={cn(
                                                    'flex min-h-[2.1875rem] items-center rounded-[0.6875rem] px-3 text-[0.75rem] font-bold transition-colors',
                                                    isActive(child.href)
                                                        ? 'bg-white/10 text-white shadow-[inset_3px_0_var(--gold)]'
                                                        : 'text-white/70 hover:bg-white/10 hover:text-white',
                                                )}
                                            >
                                                {child.title}
                                            </Link>
                                        ))}
                                    </div>
                                )}
                            </div>
                        );
                    }

                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={cn(
                                'flex min-h-[2.6875rem] items-center gap-3 rounded-[0.6875rem] border-0 px-3 text-[0.8125rem] font-bold transition-colors',
                                active
                                    ? 'bg-white/10 text-white shadow-[inset_3px_0_var(--gold)]'
                                    : 'text-white/75 hover:bg-white/10 hover:text-white',
                            )}
                        >
                            <span className="grid size-[1.3125rem] shrink-0 place-items-center rounded-[0.4375rem] bg-gold/10 text-[0.6875rem] text-gold">
                                <item.icon className="size-3.5" />
                            </span>
                            {item.title}
                        </Link>
                    );
                })}
            </nav>

            {/* Bottom: site pill + alerts */}
            <div className="mt-auto border-t border-white/10 pt-3.5">
                <button className="mb-2 flex min-h-[2.6875rem] w-full items-center gap-3 rounded-[0.6875rem] px-3 text-[0.8125rem] font-bold text-white/75 transition-colors hover:bg-white/10 hover:text-white">
                    <span className="grid size-[1.3125rem] shrink-0 place-items-center rounded-[0.4375rem] bg-gold/10 text-gold">
                        <Bell className="size-3.5" />
                    </span>
                    Alertes
                </button>
                <div className="mx-1 rounded-[0.75rem] bg-white/[0.07] p-2.75">
                    <small className="block text-[0.625rem] text-white/45">
                        Périmètre actif
                    </small>
                    <strong className="mt-1 block text-[0.75rem] text-white">
                        {auth?.user?.name ?? 'Siège Cotonou'}
                    </strong>
                </div>
            </div>
        </>
    );

    return (
        <>
            {/* Desktop sidebar */}
            <aside className="kora-sidebar sticky top-0 hidden h-screen flex-col overflow-y-auto border-r border-white/10 bg-[var(--kora)] px-4 py-5.5 text-white shadow-[4px_0_24px_rgba(0,0,0,0.25)] lg:flex">
                {sidebarContent}
            </aside>

            {/* Mobile sidebar overlay with slide animation */}
            <div
                className={cn(
                    'fixed inset-0 z-50 lg:hidden transition-opacity duration-300',
                    mobileOpen
                        ? 'opacity-100'
                        : 'pointer-events-none opacity-0',
                )}
                onClick={onMobileClose}
            >
                <div className="absolute inset-0 bg-black/50" />
                <aside
                    className={cn(
                        'absolute left-0 top-0 flex h-full w-[264px] flex-col overflow-y-auto bg-[var(--kora)] px-4 py-5.5 text-white transition-transform duration-300 ease-out',
                        mobileOpen ? 'translate-x-0' : '-translate-x-full',
                    )}
                    onClick={(e) => e.stopPropagation()}
                >
                    {sidebarContent}
                </aside>
            </div>
        </>
    );
}
