import {
    Bell,
    Building2,
    ChevronRight,
    Coins,
    FileText,
    Landmark,
    LayoutDashboard,
    Menu,
    ReceiptText,
    RotateCcw,
    Search,
    Shield,
    Store,
    Ticket,
    TrendingUp,
    Wallet,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import { MOCK_PERSONAS } from '../mock/kora-point-mock-data';
import type {
    FinancialHealth,
    KoraPointProfile,
    KoraPointUserRole,
} from '../mock/kora-point-mock-data';

export type KoraPointActivePage =
    | 'dashboard'
    | 'sale'
    | 'cash'
    | 'tickets'
    | 'my-point'
    | 'commissions'
    | 'settlements'
    | 'refunds'
    | 'notifications'
    | 'finance'
    | 'org-supervision'
    | 'org-treasury'
    | 'admin-network'
    | 'admin-organizations';

type Props = {
    point: KoraPointProfile;
    health: FinancialHealth;
    activePage: KoraPointActivePage;
    onNavigate: (page: KoraPointActivePage) => void;
    children: ReactNode;
    bannerSlot?: ReactNode;
    unreadNotificationsCount?: number;
    onOpenSearch?: () => void;
    currentRole?: KoraPointUserRole;
    standalone?: boolean;
};

function KoraPointStandaloneShell({
    point,
    health,
    activePage,
    onNavigate,
    children,
    bannerSlot,
    unreadNotificationsCount,
    onOpenSearch,
    currentRole = 'AGENT_POINT',
}: Omit<Props, 'standalone'>) {
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const isAutonomous = !point.organization;
    const persona = MOCK_PERSONAS[currentRole] || MOCK_PERSONAS.AGENT_POINT;

    const navGroups = useMemo(() => {
        if (currentRole === 'RESPONSABLE_ORG') {
            return [
                {
                    title: 'Supervision Organisation',
                    items: [
                        {
                            id: 'org-supervision' as KoraPointActivePage,
                            label: 'Supervision réseau',
                            icon: Building2,
                            badge: 'Resp.',
                            badgeTone: 'navy' as const,
                        },
                        {
                            id: 'admin-organizations' as KoraPointActivePage,
                            label: 'Bureaux & Organisation',
                            icon: Store,
                            badge: null,
                        },
                        {
                            id: 'commissions' as KoraPointActivePage,
                            label: 'Commissions réseau',
                            icon: TrendingUp,
                            badge: null,
                        },
                        {
                            id: 'tickets' as KoraPointActivePage,
                            label: 'Ventes & Billets réseau',
                            icon: FileText,
                            badge: null,
                        },
                        {
                            id: 'settlements' as KoraPointActivePage,
                            label: 'Dossiers financiers',
                            icon: ReceiptText,
                            badge: null,
                        },
                        {
                            id: 'notifications' as KoraPointActivePage,
                            label: 'Notifications',
                            icon: Bell,
                            badge: unreadNotificationsCount
                                ? String(unreadNotificationsCount)
                                : null,
                            badgeTone: 'amber' as const,
                        },
                    ],
                },
            ];
        }

        if (currentRole === 'TRESORIER_ORG') {
            return [
                {
                    title: 'Trésorerie Organisation',
                    items: [
                        {
                            id: 'org-treasury' as KoraPointActivePage,
                            label: 'Trésorerie groupée',
                            icon: Coins,
                            badge: 'Trésorier',
                            badgeTone: 'gold' as const,
                        },
                        {
                            id: 'settlements' as KoraPointActivePage,
                            label: 'Suivi des reversements',
                            icon: ReceiptText,
                            badge: null,
                        },
                        {
                            id: 'commissions' as KoraPointActivePage,
                            label: 'Commissions réseau',
                            icon: TrendingUp,
                            badge: null,
                        },
                        {
                            id: 'refunds' as KoraPointActivePage,
                            label: 'Consultation remboursements',
                            icon: RotateCcw,
                            badge: null,
                        },
                        {
                            id: 'admin-organizations' as KoraPointActivePage,
                            label: 'Organisation & Bureaux',
                            icon: Building2,
                            badge: null,
                        },
                        {
                            id: 'notifications' as KoraPointActivePage,
                            label: 'Notifications',
                            icon: Bell,
                            badge: unreadNotificationsCount
                                ? String(unreadNotificationsCount)
                                : null,
                            badgeTone: 'amber' as const,
                        },
                    ],
                },
            ];
        }

        if (currentRole === 'ADMIN_KORA') {
            return [
                {
                    title: 'Administration Commerciale',
                    items: [
                        {
                            id: 'admin-network' as KoraPointActivePage,
                            label: 'Réseau commercial',
                            icon: Store,
                            badge: 'Admin',
                            badgeTone: 'gold' as const,
                        },
                        {
                            id: 'admin-organizations' as KoraPointActivePage,
                            label: 'Organisations & Contrats',
                            icon: Building2,
                            badge: null,
                        },
                        {
                            id: 'tickets' as KoraPointActivePage,
                            label: 'Billets & Contrôle',
                            icon: FileText,
                            badge: null,
                        },
                        {
                            id: 'settlements' as KoraPointActivePage,
                            label: 'Reversements réseau',
                            icon: ReceiptText,
                            badge: null,
                        },
                        {
                            id: 'notifications' as KoraPointActivePage,
                            label: 'Notifications',
                            icon: Bell,
                            badge: unreadNotificationsCount
                                ? String(unreadNotificationsCount)
                                : null,
                            badgeTone: 'amber' as const,
                        },
                    ],
                },
            ];
        }

        if (currentRole === 'FINANCE_KORA') {
            return [
                {
                    title: 'Pôle Finance & Trésorerie',
                    items: [
                        {
                            id: 'finance' as KoraPointActivePage,
                            label: 'Finance & Rapprochements',
                            icon: Landmark,
                            badge: 'Finance',
                            badgeTone: 'emerald' as const,
                        },
                        {
                            id: 'settlements' as KoraPointActivePage,
                            label: 'File des reversements',
                            icon: ReceiptText,
                            badge: null,
                        },
                        {
                            id: 'refunds' as KoraPointActivePage,
                            label: 'Instruction remboursements',
                            icon: RotateCcw,
                            badge: null,
                        },
                        {
                            id: 'tickets' as KoraPointActivePage,
                            label: 'Journal des ventes & audit',
                            icon: FileText,
                            badge: null,
                        },
                        {
                            id: 'commissions' as KoraPointActivePage,
                            label: 'Commissions réseau',
                            icon: TrendingUp,
                            badge: null,
                        },
                        {
                            id: 'admin-network' as KoraPointActivePage,
                            label: 'Réseau commercial',
                            icon: Store,
                            badge: null,
                        },
                        {
                            id: 'notifications' as KoraPointActivePage,
                            label: 'Notifications',
                            icon: Bell,
                            badge: unreadNotificationsCount
                                ? String(unreadNotificationsCount)
                                : null,
                            badgeTone: 'amber' as const,
                        },
                    ],
                },
            ];
        }

        // AGENT_POINT (Default)
        return [
            {
                title: 'Espace Agent Point',
                items: [
                    {
                        id: 'dashboard' as KoraPointActivePage,
                        label: 'Accueil agent',
                        icon: LayoutDashboard,
                        badge: null,
                    },
                    {
                        id: 'sale' as KoraPointActivePage,
                        label: 'Vente guichet',
                        icon: Ticket,
                        badge: health.sales_allowed ? 'Prêt' : 'Bloqué',
                        badgeTone: health.sales_allowed
                            ? ('emerald' as const)
                            : ('destructive' as const),
                    },
                    {
                        id: 'cash' as KoraPointActivePage,
                        label: 'Caisse',
                        icon: Wallet,
                        badge: null,
                    },
                    {
                        id: 'tickets' as KoraPointActivePage,
                        label: 'Billets & Activité',
                        icon: FileText,
                        badge: null,
                    },
                    {
                        id: 'my-point' as KoraPointActivePage,
                        label: 'Mon Point',
                        icon: Store,
                        badge: null,
                    },
                    {
                        id: 'commissions' as KoraPointActivePage,
                        label: 'Commissions',
                        icon: TrendingUp,
                        badge: '3 %',
                        badgeTone: 'gold' as const,
                    },
                    {
                        id: 'settlements' as KoraPointActivePage,
                        label: 'Reversements',
                        icon: ReceiptText,
                        badge: isAutonomous ? 'Préparer' : 'Trésorier',
                        badgeTone: isAutonomous
                            ? ('navy' as const)
                            : ('muted' as const),
                    },
                    {
                        id: 'refunds' as KoraPointActivePage,
                        label: 'Remboursements',
                        icon: RotateCcw,
                        badge: null,
                    },
                    {
                        id: 'notifications' as KoraPointActivePage,
                        label: 'Notifications',
                        icon: Bell,
                        badge: unreadNotificationsCount
                            ? String(unreadNotificationsCount)
                            : null,
                        badgeTone: 'amber' as const,
                    },
                ],
            },
        ];
    }, [
        currentRole,
        health.sales_allowed,
        isAutonomous,
        unreadNotificationsCount,
    ]);

    const handleNavClick = (pageId: KoraPointActivePage) => {
        onNavigate(pageId);
        setMobileNavOpen(false);
    };

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
            {/* Top Demo Banner Slot */}
            {bannerSlot}

            {/* Main Application Container */}
            <div className="flex min-h-[calc(100vh-2.75rem)]">
                {/* Desktop Sidebar */}
                <aside className="hidden w-64 shrink-0 flex-col border-r border-slate-200/80 bg-[var(--kora)] text-white shadow-lg lg:flex">
                    {/* Brand */}
                    <div className="border-b border-white/10 px-5 py-5">
                        <div className="flex items-center gap-3">
                            <div className="grid size-9 place-items-center rounded-xl bg-gold font-black text-kora shadow-md">
                                KP
                            </div>
                            <div>
                                <strong className="block text-sm font-black tracking-wide text-white">
                                    KORA POINT
                                </strong>
                                <span className="block text-[0.625rem] font-extrabold tracking-widest text-gold uppercase">
                                    Distribution Bus
                                </span>
                            </div>
                        </div>

                        {/* Active Point Card */}
                        <div className="mt-4 rounded-xl border border-white/10 bg-white/5 p-3">
                            <div className="flex items-center justify-between gap-1 text-[0.625rem] font-bold tracking-wider text-white/60 uppercase">
                                <span>Point actif</span>
                                <span className="font-mono text-gold">
                                    {point.code}
                                </span>
                            </div>
                            <div className="mt-1 truncate text-xs font-extrabold text-white">
                                {point.name}
                            </div>
                            <div className="mt-1 flex items-center gap-1.5 text-[0.6875rem] text-white/70">
                                {point.organization ? (
                                    <>
                                        <Building2 className="size-3 shrink-0 text-gold" />
                                        <span className="truncate">
                                            {point.organization.name}
                                        </span>
                                    </>
                                ) : (
                                    <>
                                        <Shield className="size-3 shrink-0 text-emerald-400" />
                                        <span>Point autonome</span>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Navigation Menu */}
                    <nav
                        className="flex-1 space-y-4 overflow-y-auto px-3 py-4"
                        aria-label="Navigation partenaire"
                    >
                        {navGroups.map((group) => (
                            <div key={group.title} className="space-y-1">
                                <div className="px-3 pb-1 text-[0.625rem] font-black tracking-wider text-gold uppercase">
                                    {group.title}
                                </div>
                                {group.items.map((item) => {
                                    const Icon = item.icon;
                                    const isActive = activePage === item.id;

                                    return (
                                        <button
                                            key={item.id}
                                            type="button"
                                            onClick={() =>
                                                handleNavClick(item.id)
                                            }
                                            className={cn(
                                                'group flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-xs font-semibold transition',
                                                isActive
                                                    ? 'bg-gold font-black text-kora shadow-md'
                                                    : 'text-white/80 hover:bg-white/10 hover:text-white',
                                            )}
                                        >
                                            <div className="flex items-center gap-2.5 truncate">
                                                <Icon
                                                    className={cn(
                                                        'size-4 shrink-0',
                                                        isActive
                                                            ? 'text-kora'
                                                            : 'text-white/70 group-hover:text-white',
                                                    )}
                                                />
                                                <span className="truncate">
                                                    {item.label}
                                                </span>
                                            </div>
                                            {item.badge && (
                                                <span
                                                    className={cn(
                                                        'rounded-full px-2 py-0.5 text-[0.625rem] font-extrabold',
                                                        isActive
                                                            ? 'bg-kora text-white'
                                                            : item.badgeTone ===
                                                                'emerald'
                                                              ? 'bg-emerald-500/20 text-emerald-300'
                                                              : item.badgeTone ===
                                                                  'destructive'
                                                                ? 'bg-rose-500/25 text-rose-300'
                                                                : item.badgeTone ===
                                                                    'amber'
                                                                  ? 'bg-amber-500/25 text-amber-300'
                                                                  : item.badgeTone ===
                                                                      'gold'
                                                                    ? 'bg-gold/25 text-gold'
                                                                    : 'bg-white/10 text-white/70',
                                                    )}
                                                >
                                                    {item.badge}
                                                </span>
                                            )}
                                        </button>
                                    );
                                })}
                            </div>
                        ))}
                    </nav>

                    {/* Footer User Menu */}
                    <div className="border-t border-white/10 p-4">
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex items-center gap-2.5 truncate">
                                <div className="grid size-8 shrink-0 place-items-center rounded-full bg-gold text-xs font-black text-kora">
                                    {persona.initials}
                                </div>
                                <div className="truncate">
                                    <div className="truncate text-xs font-bold text-white">
                                        {persona.name}
                                    </div>
                                    <div className="truncate text-[0.625rem] font-semibold text-gold">
                                        {persona.roleLabel}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>

                {/* Main Content Area */}
                <div className="flex flex-1 flex-col overflow-x-hidden">
                    {/* Top Navigation Bar */}
                    <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200/80 bg-white/95 px-4 backdrop-blur-md sm:px-6 lg:px-8 dark:border-slate-800 dark:bg-slate-900/95">
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setMobileNavOpen(!mobileNavOpen)}
                                className="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-100 lg:hidden dark:border-slate-800 dark:text-slate-200 dark:hover:bg-slate-800"
                                aria-label="Menu mobile"
                            >
                                {mobileNavOpen ? (
                                    <X className="size-5" />
                                ) : (
                                    <Menu className="size-5" />
                                )}
                            </button>

                            <div className="flex flex-wrap items-center gap-2">
                                <div className="flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400">
                                    <span className="hidden sm:inline">
                                        KORA OPS
                                    </span>
                                    <ChevronRight className="hidden size-3.5 sm:inline" />
                                    <span className="font-extrabold text-slate-900 dark:text-white">
                                        {point.name}
                                    </span>
                                </div>

                                <KoraStatusBadge
                                    label={
                                        point.organization
                                            ? `Réseau : ${point.organization.name}`
                                            : 'Point autonome'
                                    }
                                    tone={
                                        point.organization ? 'blue' : 'emerald'
                                    }
                                    variant="solid"
                                    className="hidden sm:inline-flex"
                                />

                                <KoraStatusBadge
                                    label={`${persona.roleLabel} • ${persona.name}`}
                                    tone={persona.badgeTone}
                                    variant="solid"
                                    className="hidden md:inline-flex"
                                />
                            </div>
                        </div>

                        {/* Topbar Right Context */}
                        <div className="flex items-center gap-2 sm:gap-3">
                            {/* Quick Search Trigger */}
                            {onOpenSearch && (
                                <button
                                    type="button"
                                    onClick={onOpenSearch}
                                    className="hidden items-center gap-2 rounded-xl border border-slate-200/80 bg-slate-50 px-3 py-1.5 text-xs text-slate-500 transition hover:border-slate-300 hover:bg-slate-100 sm:inline-flex dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-400 dark:hover:bg-slate-800"
                                    aria-label="Recherche globale"
                                >
                                    <Search className="size-3.5 text-slate-400" />
                                    <span>Recherche rapide...</span>
                                    <kbd className="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[0.625rem] font-bold text-slate-500 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
                                        Ctrl K
                                    </kbd>
                                </button>
                            )}

                            {/* Notifications Bell */}
                            <button
                                type="button"
                                onClick={() => handleNavClick('notifications')}
                                className={cn(
                                    'relative grid size-9 place-items-center rounded-xl border transition',
                                    activePage === 'notifications'
                                        ? 'border-gold bg-gold/15 text-kora dark:text-gold'
                                        : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800',
                                )}
                                aria-label="Notifications"
                            >
                                <Bell className="size-4" />
                                {(unreadNotificationsCount ?? 0) > 0 && (
                                    <span className="absolute -top-1 -right-1 grid size-4 place-items-center rounded-full bg-amber-500 text-[0.5625rem] font-black text-white shadow-sm">
                                        {unreadNotificationsCount}
                                    </span>
                                )}
                            </button>

                            <div className="text-right">
                                <div className="text-xs font-black text-slate-900 dark:text-white">
                                    {health.sales_allowed ? (
                                        <span className="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                                            <span className="size-2 animate-pulse rounded-full bg-emerald-500" />
                                            Ventes prêtes
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1.5 text-rose-600 dark:text-rose-400">
                                            <span className="size-2 rounded-full bg-rose-500" />
                                            Ventes bloquées
                                        </span>
                                    )}
                                </div>
                                <div className="text-[0.625rem] text-slate-500 dark:text-slate-400">
                                    Plafond dispo :{' '}
                                    <strong className="font-bold text-slate-700 dark:text-slate-200">
                                        {health.available_balance.toLocaleString(
                                            'fr-FR',
                                        )}{' '}
                                        FCFA
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </header>

                    {/* Mobile Navigation Drawer */}
                    {mobileNavOpen && (
                        <div className="fixed inset-0 top-[6.75rem] z-50 bg-black/60 backdrop-blur-sm lg:hidden">
                            <div className="flex h-full w-72 flex-col bg-[var(--kora)] p-4 text-white shadow-xl">
                                <div className="mb-4 flex items-center justify-between border-b border-white/10 pb-3">
                                    <strong className="text-sm font-black text-white">
                                        Menu KORA POINT
                                    </strong>
                                    <button
                                        type="button"
                                        onClick={() => setMobileNavOpen(false)}
                                        className="rounded-lg p-1 text-white/70 hover:text-white"
                                    >
                                        <X className="size-5" />
                                    </button>
                                </div>

                                <nav className="flex-1 space-y-4 overflow-y-auto">
                                    {navGroups.map((group) => (
                                        <div
                                            key={group.title}
                                            className="space-y-1"
                                        >
                                            <div className="px-3 pb-1 text-xs font-black tracking-wider text-gold uppercase">
                                                {group.title}
                                            </div>
                                            {group.items.map((item) => {
                                                const Icon = item.icon;
                                                const isActive =
                                                    activePage === item.id;

                                                return (
                                                    <button
                                                        key={item.id}
                                                        type="button"
                                                        onClick={() =>
                                                            handleNavClick(
                                                                item.id,
                                                            )
                                                        }
                                                        className={cn(
                                                            'flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-sm font-semibold transition',
                                                            isActive
                                                                ? 'bg-gold font-black text-kora shadow-md'
                                                                : 'text-white/80 hover:bg-white/10 hover:text-white',
                                                        )}
                                                    >
                                                        <div className="flex items-center gap-3">
                                                            <Icon className="size-4 shrink-0" />
                                                            <span>
                                                                {item.label}
                                                            </span>
                                                        </div>
                                                        {item.badge && (
                                                            <span className="rounded-full bg-white/20 px-2 py-0.5 text-xs font-bold">
                                                                {item.badge}
                                                            </span>
                                                        )}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    ))}
                                </nav>
                            </div>
                        </div>
                    )}

                    {/* Screen Content Viewport */}
                    <main className="flex-1 p-4 sm:p-6 lg:p-8">{children}</main>
                </div>
            </div>
        </div>
    );
}

export function KoraPointShell({
    point,
    health,
    activePage,
    onNavigate,
    children,
    bannerSlot,
    unreadNotificationsCount,
    onOpenSearch,
    currentRole = 'AGENT_POINT',
    standalone = false,
}: Props) {
    if (standalone) {
        return (
            <KoraPointStandaloneShell
                point={point}
                health={health}
                activePage={activePage}
                onNavigate={onNavigate}
                bannerSlot={bannerSlot}
                unreadNotificationsCount={unreadNotificationsCount}
                onOpenSearch={onOpenSearch}
                currentRole={currentRole}
            >
                {children}
            </KoraPointStandaloneShell>
        );
    }

    return (
        <div className="cash-page space-y-6">
            {bannerSlot}
            <div className="min-w-0">{children}</div>
        </div>
    );
}
