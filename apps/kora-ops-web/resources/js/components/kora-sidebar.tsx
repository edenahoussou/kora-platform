import { Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    Bus,
    ChevronUp,
    FileText,
    LayoutDashboard,
    PanelLeftClose,
    PanelLeftOpen,
    ReceiptText,
    ShieldCheck,
    Store,
    Ticket,
    Users,
    Wallet,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { KoraUserMenuContent } from '@/components/kora-user-menu';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { usePermissions } from '@/hooks/use-permissions';
import { cn } from '@/lib/utils';
import { dashboard, logout, revenue } from '@/routes';
import companyPortalRoutes from '@/routes/company-portal';
import programming from '@/routes/programming';
import { update as updatePushPreference } from '@/routes/settings/notifications/push';

type NavPermission = string | string[] | null;

type NavChild = {
    title: string;
    href: string;
    permission: NavPermission;
    role?: string;
    exact?: boolean;
};

type NavEntry = {
    title: string;
    href: string;
    icon: typeof LayoutDashboard;
    permission: NavPermission;
    children?: NavChild[];
};

const companyPortalHref = companyPortalRoutes.index.url();

const navItems: NavEntry[] = [
    {
        title: 'Accueil',
        href: dashboard().url,
        icon: LayoutDashboard,
        permission: null,
    },
    {
        title: 'Espace entreprise',
        href: companyPortalHref,
        icon: Building2,
        permission: null,
        children: [
            {
                title: 'Tableau de bord',
                href: companyPortalRoutes.index.url(),
                permission: null,
                exact: true,
            },
            {
                title: 'Acheter des billets',
                href: companyPortalRoutes.sale.index.url(),
                permission: null,
            },
            {
                title: 'Membres voyageurs',
                href: companyPortalRoutes.travelers.index.url(),
                permission: null,
            },
        ],
    },
    {
        title: 'Vente',
        href: '/sale',
        icon: Ticket,
        permission: [
            'counter-sales.create',
            'assisted-bookings.view-agency',
            'assisted-bookings.view-network',
        ],
        children: [
            {
                title: 'Vente au guichet',
                href: '/sale',
                permission: 'counter-sales.create',
            },
            {
                title: 'Réservations en attente',
                href: '/assisted-bookings',
                permission: [
                    'assisted-bookings.view-agency',
                    'assisted-bookings.view-network',
                ],
            },
        ],
    },
    {
        title: 'Caisse',
        href: '/cash',
        icon: Wallet,
        permission: 'cash.view',
    },
    {
        title: 'Finance',
        href: '/fiscal-invoices',
        icon: ReceiptText,
        permission: [
            'fiscalization.view',
            'expenses.view-own',
            'expenses.view-agency',
            'expenses.view-network',
            'revenue.view',
        ],
        children: [
            {
                title: 'Recettes',
                href: revenue().url,
                permission: 'revenue.view',
            },
            {
                title: 'Facturation',
                href: '/fiscal-invoices',
                permission: 'fiscalization.view',
            },
            {
                title: 'Campagne d’autodéclaration DGI',
                href: '/fiscalization/test-invoices',
                permission: null,
                role: 'system_admin',
            },
            {
                title: 'Dépenses',
                href: '/expenses',
                permission: [
                    'expenses.view-own',
                    'expenses.view-agency',
                    'expenses.view-network',
                ],
            },
        ],
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
                title: 'Suivi de flotte',
                href: '/operations/fleet-tracking',
                permission: ['tracking.view-agency', 'tracking.view-network'],
            },
            {
                title: 'Lignes & arrêts',
                href: '/network',
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
                title: 'Promotions',
                href: '/promotions',
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
        permission: 'tickets.view',
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
                title: 'Modèles',
                href: '/models',
                permission: 'fleet.view',
            },
            {
                title: 'Commodités',
                href: '/amenities',
                permission: 'fleet.view',
            },
        ],
    },
    {
        title: 'Administration',
        href: '/users',
        icon: ShieldCheck,
        permission: [
            'users.view',
            'roles.view',
            'terminals.view',
            'settings.general.view',
            'settings.operations.view',
            'settings.integrations.view',
        ],
        children: [
            {
                title: 'Utilisateurs',
                href: '/users',
                permission: 'users.view',
            },
            {
                title: 'Rôles et permissions',
                href: '/roles',
                permission: 'roles.view',
            },
            {
                title: 'Terminaux P501',
                href: '/terminals',
                permission: 'terminals.view',
            },
            {
                title: 'Paramètres',
                href: '/administration/settings/general',
                permission: [
                    'settings.general.view',
                    'settings.operations.view',
                    'settings.integrations.view',
                ],
            },
        ],
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
    const { auth, companyPortal } = usePage().props;
    const { can, canAny, hasRole } = usePermissions();
    const getInitials = useInitials();
    const [openGroups, setOpenGroups] = useState<string[]>([]);

    const handleLogout = async () => {
        const fingerprint = window.localStorage.getItem(
            'kora-ops-web-push-device',
        );

        if (
            fingerprint &&
            window.localStorage.getItem('kora-ops-web-push-enabled') === 'true'
        ) {
            const xsrf = document.cookie
                .split('; ')
                .find((part) => part.startsWith('XSRF-TOKEN='))
                ?.slice('XSRF-TOKEN='.length);

            try {
                await fetch(updatePushPreference.url(), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': xsrf ? decodeURIComponent(xsrf) : '',
                    },
                    body: JSON.stringify({
                        device_fingerprint: fingerprint,
                        device_name: 'Navigateur Web',
                        push_token: null,
                    }),
                });
            } catch {
                // Logout still proceeds if best-effort token cleanup is offline.
            }
        }

        router.post(
            logout().url,
            {},
            {
                onFinish: () => {
                    window.localStorage.removeItem('kora-ops-web-push-enabled');
                    router.flushAll();
                },
            },
        );
    };

    // La sidebar est remontée à chaque visite Inertia : on restaure les
    // groupes ouverts manuellement. Différé d'un tick pour rester hors du
    // rendu d'hydration (SSR → localStorage = mismatch sinon), et le
    // groupe de la page active est déjà forcé ouvert via `hasActiveChild`.
    useEffect(() => {
        const restore = setTimeout(() => {
            try {
                const saved = JSON.parse(
                    localStorage.getItem('kora-sidebar-groups') ?? '[]',
                );

                if (Array.isArray(saved) && saved.length > 0) {
                    setOpenGroups(saved.filter((t) => typeof t === 'string'));
                }
            } catch {
                // localStorage indisponible ou JSON corrompu : navigation simple.
            }
        }, 0);

        return () => clearTimeout(restore);
    }, []);

    useEffect(() => {
        try {
            localStorage.setItem(
                'kora-sidebar-groups',
                JSON.stringify(openGroups),
            );
        } catch {
            // Stockage indisponible : l'état vit juste en mémoire.
        }
    }, [openGroups]);

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

    const isActive = (href: string, exact = false) =>
        isCurrentUrl(href) ||
        (!exact &&
            href !== dashboard().url &&
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
                    const visibleChildren = item.children?.filter(
                        (child) =>
                            canAccess(child.permission) &&
                            (!child.role || hasRole(child.role)),
                    );

                    if (
                        !canAccess(item.permission) ||
                        (item.href === '/cash' && !auth?.user?.canViewCash) ||
                        (item.href === '/tickets' &&
                            !auth?.user?.canViewTickets) ||
                        (item.href === companyPortalHref && !companyPortal) ||
                        (item.children && visibleChildren?.length === 0)
                    ) {
                        return null;
                    }

                    const active = isActive(item.href);

                    if (item.children) {
                        const hasActiveChild =
                            visibleChildren?.some((child) =>
                                isActive(child.href, child.exact),
                            ) ?? false;
                        // Le groupe contenant la page active reste toujours
                        // déplié — le repère "où suis-je" ne doit jamais
                        // disparaître après un reload ou un remount.
                        const isOpen =
                            openGroups.includes(item.title) || hasActiveChild;

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
                                                    isActive(
                                                        child.href,
                                                        child.exact,
                                                    )
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

            {/* Bottom: utilisateur actif avec dropdown vers le haut (side="top") */}
            <div
                className={cn(
                    'mt-auto border-t border-white/10 pt-3.5',
                    isCollapsed && 'border-t-0 pt-0',
                )}
            >
                {auth?.user ? (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                className={cn(
                                    'group flex w-full items-center rounded-[0.75rem] text-left transition-all duration-200 focus-visible:ring-2 focus-visible:ring-gold/50 focus-visible:outline-none',
                                    isCollapsed
                                        ? 'justify-center p-0'
                                        : 'mx-0 bg-white/[0.07] p-2.5 hover:bg-white/[0.12]',
                                )}
                                aria-label="Menu utilisateur"
                            >
                                {isCollapsed ? (
                                    <Avatar
                                        className="mx-auto size-9 rounded-[0.6875rem] ring-1 ring-white/15 transition-transform duration-200 group-hover:scale-105"
                                        title={auth.user.name}
                                    >
                                        <AvatarImage
                                            src={auth.user.avatar ?? undefined}
                                            alt={auth.user.name}
                                        />
                                        <AvatarFallback className="rounded-[0.6875rem] bg-white/[0.08] text-[0.6875rem] font-black text-gold">
                                            {getInitials(auth.user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                ) : (
                                    <>
                                        <Avatar className="size-8 shrink-0 rounded-[0.5625rem] ring-1 ring-white/20">
                                            <AvatarImage
                                                src={
                                                    auth.user.avatar ??
                                                    undefined
                                                }
                                                alt={auth.user.name}
                                            />
                                            <AvatarFallback className="rounded-[0.5625rem] bg-white/[0.08] text-[0.6875rem] font-black text-gold">
                                                {getInitials(auth.user.name)}
                                            </AvatarFallback>
                                        </Avatar>

                                        <div className="ml-2.5 min-w-0 flex-1">
                                            <strong className="block truncate text-[0.75rem] font-bold text-white">
                                                {auth.user.name}
                                            </strong>
                                            <small className="block truncate text-[0.625rem] text-white/55">
                                                {auth.user.email}
                                            </small>
                                        </div>

                                        <ChevronUp className="size-4 shrink-0 text-white/40 transition-transform duration-200 group-hover:text-white group-data-[state=open]:rotate-180" />
                                    </>
                                )}
                            </button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent
                            side="top"
                            align={isCollapsed ? 'start' : 'center'}
                            sideOffset={12}
                            className="rounded-2xl border-border bg-card p-1.5 shadow-[0_-20px_50px_rgba(0,29,78,0.22)]"
                        >
                            <KoraUserMenuContent
                                user={auth.user}
                                onLogout={handleLogout}
                            />
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : (
                    <div
                        className={cn(
                            'mx-1 rounded-[0.75rem] bg-white/[0.07] p-2.75',
                            isCollapsed && 'mx-0 bg-transparent p-0',
                        )}
                    >
                        <small className="block text-[0.625rem] text-white/45">
                            Périmètre actif
                        </small>
                        <strong className="mt-1 block text-[0.75rem] text-white">
                            Siège Cotonou
                        </strong>
                    </div>
                )}
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
