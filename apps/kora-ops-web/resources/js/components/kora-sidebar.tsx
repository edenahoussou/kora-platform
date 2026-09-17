import { Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Bell,
    Bus,
    FileText,
    LayoutDashboard,
    PanelLeftClose,
    PanelLeftOpen,
    Store,
    Ticket,
    Users,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { usePermissions } from '@/hooks/use-permissions';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import programming from '@/routes/programming';

type NavPermission = string | string[] | null;

type NavChild = {
    title: string;
    href: string;
    permission: NavPermission;
};

type NavEntry = {
    title: string;
    href: string;
    icon: typeof LayoutDashboard;
    permission: NavPermission;
    children?: NavChild[];
};

const navItems: NavEntry[] = [
    {
        title: 'Accueil',
        href: dashboard().url,
        icon: LayoutDashboard,
        permission: null,
    },
    {
        title: 'Vente guichet',
        href: '/sale',
        icon: Ticket,
        permission: 'counter-sales.create',
    },
    {
        title: 'Caisse',
        href: '/cash',
        icon: Wallet,
        permission: 'cash.view',
    },
    {
        title: 'Exploitation',
        href: '/operations',
        icon: Bus,
        permission: null,
        children: [
            {
                title: 'Programmation',
                href: programming.index.url(),
                permission: 'departures.manage',
            },
            {
                title: 'Départs',
                href: '/departures',
                permission: 'departures.view',
            },
            {
                title: 'Embarquement',
                href: '/boarding',
                permission: 'departures.view',
            },
            {
                title: 'Manifestes',
                href: '/manifest',
                permission: 'departures.view',
            },
            {
                title: 'Lignes & arrêts',
                href: '/network',
                permission: 'network.view',
            },
            {
                title: 'Carte (prototype)',
                href: '/network/map-prototype',
                permission: 'network.view',
            },
            { title: 'Tarifs', href: '/fares', permission: 'pricing.view' },
            {
                title: 'Taux fiscaux',
                href: '/tax-rules',
                permission: 'pricing.view',
            },
        ],
    },
    {
        title: 'Réseau commercial',
        href: '/dynamic-pricing',
        icon: Store,
        permission: [
            'pricing.view',
            'pricing.manage',
            'company-accounts.view',
            'company-accounts.manage',
        ],
        children: [
            {
                title: 'Tarification dynamique',
                href: '/dynamic-pricing',
                permission: ['pricing.view', 'pricing.manage'],
            },
            {
                title: 'Comptes entreprises',
                href: '/companies',
                permission: 'company-accounts.view',
            },
        ],
    },
    {
        title: 'Passagers',
        href: '/passengers',
        icon: Users,
        permission: 'bookings.view',
    },
    {
        title: 'Billets',
        href: '/tickets',
        icon: FileText,
        permission: 'tickets.view',
    },
    {
        title: 'Ressources',
        href: '/resources',
        icon: Store,
        permission: null,
        children: [
            {
                title: 'Agences',
                href: '/agencies',
                permission: 'agencies.view',
            },
            { title: 'Flotte', href: '/fleet', permission: 'fleet.view' },
            {
                title: 'Commodités',
                href: '/amenities',
                permission: 'fleet.view',
            },
        ],
    },
    {
        title: 'Notifications',
        href: '/notifications',
        icon: Bell,
        permission: null,
    },
    {
        title: 'Alertes',
        href: '/alerts',
        icon: AlertTriangle,
        permission: null,
    },
];

export function KoraSidebar({
    collapsed,
    mobileOpen,
    onMobileClose,
    onToggleCollapse,
}: {
    collapsed: boolean;
    mobileOpen: boolean;
    onMobileClose: () => void;
    onToggleCollapse: () => void;
}) {
    const { isCurrentUrl } = useCurrentUrl();
    const { auth } = usePage().props;
    const { can, canAny } = usePermissions();
    const [openGroups, setOpenGroups] = useState<string[]>([]);

    const canAccess = (permission: NavPermission): boolean => {
        if (Array.isArray(permission)) {
            return canAny(permission);
        }

        return can(permission);
    };

    const toggleGroup = (title: string) => {
        setOpenGroups((prev) =>
            prev.includes(title)
                ? prev.filter((t) => t !== title)
                : [...prev, title],
        );
    };

    const isActive = (href: string) =>
        isCurrentUrl(href) ||
        (href !== dashboard().url &&
            typeof window !== 'undefined' &&
            window.location.pathname.startsWith(href));

    const renderSidebarContent = (
        isCollapsed: boolean,
        showCollapseButton = true,
    ) => (
        <>
            {/* Brand */}
            <div
                className={cn(
                    'kora-side-brand relative mb-7 border-b border-white/10 pt-1.5 pb-3.5',
                    isCollapsed
                        ? 'mx-0 flex justify-center border-b-0 pb-0'
                        : 'mx-1',
                )}
            >
                <Link
                    href={dashboard()}
                    className={cn(isCollapsed && 'flex justify-center')}
                >
                    {isCollapsed ? (
                        <span className="grid size-10 place-items-center rounded-[0.75rem] bg-gold text-[var(--kora)] shadow-[0_10px_22px_rgba(246,176,0,0.22)]">
                            <AppLogoIcon className="size-5 fill-current" />
                        </span>
                    ) : (
                        <img
                            src="/kora-logo-sidebar.png"
                            alt="KORA EXPRESS"
                            className="kora-side-logo block h-[5rem] w-full object-contain object-left drop-shadow-[0_10px_19px_rgba(0,29,78,0.32)]"
                        />
                    )}
                </Link>
                {!isCollapsed && (
                    <>
                        <span className="mt-1.5 block text-[0.5625rem] font-black tracking-[0.22em] text-white/60 uppercase">
                            KORA OPS
                        </span>
                        <i className="absolute bottom-[-1px] left-0 h-0.5 w-12 rounded bg-gold" />
                    </>
                )}
                {showCollapseButton && (
                    <button
                        type="button"
                        onClick={onToggleCollapse}
                        className="absolute top-0 right-0 grid size-8 place-items-center rounded-[0.625rem] text-white/55 transition-colors hover:bg-white/10 hover:text-white"
                        aria-label={
                            isCollapsed
                                ? 'Afficher la sidebar'
                                : 'Masquer la sidebar'
                        }
                        title={
                            isCollapsed
                                ? 'Afficher la sidebar'
                                : 'Masquer la sidebar'
                        }
                    >
                        {isCollapsed ? (
                            <PanelLeftOpen className="size-4" />
                        ) : (
                            <PanelLeftClose className="size-4" />
                        )}
                    </button>
                )}
            </div>

            {/* Workspace label */}
            {!isCollapsed && (
                <div className="mx-2 mb-3 text-[0.625rem] font-black tracking-[0.13em] text-white/35 uppercase">
                    Espace de travail
                </div>
            )}

            {/* Navigation */}
            <nav
                className="flex flex-1 flex-col gap-[3px] overflow-y-auto"
                aria-label="Navigation principale"
            >
                {navItems.map((item) => {
                    const visibleChildren = item.children?.filter((child) =>
                        canAccess(child.permission),
                    );

                    if (
                        !canAccess(item.permission) ||
                        (item.href === '/cash' && !auth?.user?.canViewCash) ||
                        (item.href === '/tickets' &&
                            !auth?.user?.canViewTickets) ||
                        (item.children && visibleChildren?.length === 0)
                    ) {
                        return null;
                    }

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
                                        isCollapsed && 'justify-center px-2',
                                        isOpen || hasActiveChild
                                            ? 'bg-white/10 text-white'
                                            : 'text-white/75 hover:bg-white/10 hover:text-white',
                                    )}
                                    title={isCollapsed ? item.title : undefined}
                                    aria-label={
                                        isCollapsed ? item.title : undefined
                                    }
                                >
                                    <span className="grid size-[1.3125rem] shrink-0 place-items-center rounded-[0.4375rem] bg-gold/10 text-[0.6875rem] text-gold">
                                        <item.icon className="size-3.5" />
                                    </span>
                                    {!isCollapsed && item.title}
                                    {!isCollapsed && (
                                        <span
                                            className={cn(
                                                'ml-auto text-white/35 transition-transform',
                                                isOpen && 'rotate-180',
                                            )}
                                        >
                                            ⌄
                                        </span>
                                    )}
                                </button>
                                {!isCollapsed && isOpen && (
                                    <div className="mx-0 my-[3px] mb-1.5 ml-8 flex flex-col gap-0.5">
                                        {visibleChildren?.map((child) => (
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
                                isCollapsed && 'justify-center px-2',
                                active
                                    ? 'bg-white/10 text-white shadow-[inset_3px_0_var(--gold)]'
                                    : 'text-white/75 hover:bg-white/10 hover:text-white',
                            )}
                            title={isCollapsed ? item.title : undefined}
                            aria-label={isCollapsed ? item.title : undefined}
                        >
                            <span className="grid size-[1.3125rem] shrink-0 place-items-center rounded-[0.4375rem] bg-gold/10 text-[0.6875rem] text-gold">
                                <item.icon className="size-3.5" />
                            </span>
                            {!isCollapsed && item.title}
                        </Link>
                    );
                })}
            </nav>

            {/* Bottom: site pill */}
            <div
                className={cn(
                    'mt-auto border-t border-white/10 pt-3.5',
                    isCollapsed && 'border-t-0 pt-0',
                )}
            >
                <div
                    className={cn(
                        'mx-1 rounded-[0.75rem] bg-white/[0.07] p-2.75',
                        isCollapsed && 'mx-0 bg-transparent p-0',
                    )}
                >
                    {isCollapsed ? (
                        <span
                            className="mx-auto grid size-9 place-items-center rounded-[0.6875rem] bg-white/[0.08] text-[0.6875rem] font-black text-gold"
                            title={auth?.user?.name ?? 'Siège Cotonou'}
                        >
                            {(auth?.user?.name ?? 'SC')
                                .split(' ')
                                .map((part) => part[0])
                                .join('')
                                .slice(0, 2)
                                .toUpperCase()}
                        </span>
                    ) : (
                        <>
                            <small className="block text-[0.625rem] text-white/45">
                                Périmètre actif
                            </small>
                            <strong className="mt-1 block text-[0.75rem] text-white">
                                {auth?.user?.name ?? 'Siège Cotonou'}
                            </strong>
                        </>
                    )}
                </div>
            </div>
        </>
    );

    return (
        <>
            {/* Desktop sidebar */}
            <aside
                className={cn(
                    'kora-sidebar sticky top-0 hidden h-screen flex-col overflow-y-auto border-r border-white/10 bg-[var(--kora)] px-4 py-5.5 text-white shadow-[4px_0_24px_rgba(0,0,0,0.25)] transition-[padding] duration-300 lg:flex',
                    collapsed && 'px-3',
                )}
            >
                {renderSidebarContent(collapsed)}
            </aside>

            {/* Mobile sidebar overlay with slide animation */}
            <div
                className={cn(
                    'fixed inset-0 z-50 transition-opacity duration-300 lg:hidden',
                    mobileOpen
                        ? 'opacity-100'
                        : 'pointer-events-none opacity-0',
                )}
                onClick={onMobileClose}
            >
                <div className="absolute inset-0 bg-black/50" />
                <aside
                    className={cn(
                        'absolute top-0 left-0 flex h-full w-[264px] flex-col overflow-y-auto bg-[var(--kora)] px-4 py-5.5 text-white transition-transform duration-300 ease-out',
                        mobileOpen ? 'translate-x-0' : '-translate-x-full',
                    )}
                    onClick={(e) => e.stopPropagation()}
                >
                    {renderSidebarContent(false, false)}
                </aside>
            </div>
        </>
    );
}
