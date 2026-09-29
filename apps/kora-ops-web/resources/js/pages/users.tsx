import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Edit3,
    KeyRound,
    Mail,
    Phone,
    Plus,
    Search,
    SlidersHorizontal,
    Unlock,
    Users as UsersIcon,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { UserCredentialsDialog } from '@/components/users/user-credentials-dialog';
import { UserDetailDrawer } from '@/components/users/user-detail-drawer';
import { UserFormModal } from '@/components/users/user-form-modal';
import { cn } from '@/lib/utils';
import { users as usersIndexRoute } from '@/routes';
import usersActions from '@/routes/users';
import type {
    AdministrationAgency,
    AdministrationRole,
    AdministrationUser,
    PaginatedAdministrationUsers,
} from '@/types/administration';

type UsersPageProps = {
    users?: PaginatedAdministrationUsers;
    filters?: {
        q?: string;
        role?: string;
        agency?: string;
        status?: string;
        archived?: string;
    };
    statuses?: string[];
    roles?: AdministrationRole[];
    agencies?: AdministrationAgency[];
    permissions?: {
        canManage?: boolean;
    };
    temporaryPassword?: string | null;
    otpChallengeUuid?: string | null;
};

/** Flash Inertia : racine de l'objet page (`page.flash`), jamais dans `props`. */
type CredentialsFlash = {
    temporaryPassword?: string;
    otpChallengeUuid?: string;
};

function KpiCard({
    icon: Icon,
    label,
    hint,
    value,
    tone = 'navy',
}: {
    icon: React.ComponentType<{ className?: string; strokeWidth?: number }>;
    label: string;
    hint: string;
    value: string | number;
    tone: 'navy' | 'gold' | 'blue' | 'muted' | 'green' | 'rose';
}) {
    const toneStyles = {
        navy: 'bg-kora text-white',
        gold: 'bg-gold/15 text-gold',
        blue: 'bg-digital/10 text-digital',
        muted: 'bg-muted text-muted-foreground',
        green: 'bg-emerald-500/10 text-emerald-600',
        rose: 'bg-destructive/10 text-destructive',
    };

    return (
        <article className="cash-ledger-card cash-ledger-card-cash">
            <div className="cash-ledger-head">
                <div className="cash-ledger-identity">
                    <span className={cn('cash-ledger-brand', toneStyles[tone])}>
                        <Icon className="size-4" strokeWidth={1.7} />
                    </span>
                    <div>
                        <span className="cash-ledger-title">{label}</span>
                        <small>{hint}</small>
                    </div>
                </div>
            </div>
            <div className="cash-ledger-balance">
                <span>Total</span>
                <strong>{value}</strong>
            </div>
        </article>
    );
}

export default function UsersPage({
    users: paginatedUsers = {
        data: [],
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
        from: 0,
        to: 0,
    },
    filters = {},
    statuses = ['active', 'suspended', 'locked'],
    roles = [],
    agencies = [],
    permissions = { canManage: false },
    temporaryPassword: propTempPassword,
    otpChallengeUuid: propOtpChallenge,
}: UsersPageProps) {
    const page = usePage();
    const pageProps = page.props as unknown as UsersPageProps;
    const credentialsFlash = page.flash as CredentialsFlash;

    const [query, setQuery] = useState(filters.q ?? '');
    const [selectedRole, setSelectedRole] = useState(filters.role ?? 'all');
    const [selectedAgency, setSelectedAgency] = useState(
        filters.agency ?? 'all',
    );
    const [selectedStatus, setSelectedStatus] = useState(
        filters.status ?? 'all',
    );
    const [selectedArchived, setSelectedArchived] = useState(
        filters.archived ?? 'none',
    );

    const [modalOpen, setModalOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<AdministrationUser | null>(
        null,
    );

    const [drawerOpen, setDrawerOpen] = useState(false);
    const [selectedUser, setSelectedUser] = useState<AdministrationUser | null>(
        null,
    );

    const [credentialsOpen, setCredentialsOpen] = useState(false);
    const [credPassword, setCredPassword] = useState<string | null>(null);
    const [credOtp, setCredOtp] = useState<string | null>(null);
    const [credUserName, setCredUserName] = useState<string | null>(null);
    const [credUserEmail, setCredUserEmail] = useState<string | null>(null);

    const [dismissedSecret, setDismissedSecret] = useState<string | null>(null);

    const flashTemp =
        credentialsFlash.temporaryPassword ??
        propTempPassword ??
        pageProps?.temporaryPassword;
    const flashOtp =
        credentialsFlash.otpChallengeUuid ??
        propOtpChallenge ??
        pageProps?.otpChallengeUuid;

    const hasNewFlash = Boolean(
        (flashTemp && flashTemp !== dismissedSecret) ||
        (flashOtp && flashOtp !== dismissedSecret),
    );

    const isCredentialsOpen = credentialsOpen || hasNewFlash;
    const activePassword =
        credPassword ?? (hasNewFlash ? (flashTemp ?? null) : null);
    const activeOtp = credOtp ?? (hasNewFlash ? (flashOtp ?? null) : null);

    // KPI stats calculés sur la liste
    const counts = useMemo(() => {
        const list = paginatedUsers.data;

        return {
            total: paginatedUsers.total,
            active: list.filter((u) => u.status === 'active' && !u.deleted_at)
                .length,
            operational: list.filter(
                (u) =>
                    (u.role?.category === 'agency' ||
                        u.role?.category === 'terrain') &&
                    !u.deleted_at,
            ).length,
            lockedOrSuspended: list.filter(
                (u) =>
                    (u.status === 'locked' || u.status === 'suspended') &&
                    !u.deleted_at,
            ).length,
        };
    }, [paginatedUsers]);

    const buildParams = (
        overrides: {
            q?: string;
            role?: string;
            agency?: string;
            status?: string;
            archived?: string;
            page?: number;
        } = {},
    ): Record<string, string> => {
        const nextQuery = overrides.q ?? query;
        const nextRole = overrides.role ?? selectedRole;
        const nextAgency = overrides.agency ?? selectedAgency;
        const nextStatus = overrides.status ?? selectedStatus;
        const nextArchived = overrides.archived ?? selectedArchived;

        const params: Record<string, string> = {};

        if (nextQuery.trim() !== '') {
            params.q = nextQuery.trim();
        }

        if (nextRole !== 'all') {
            params.role = nextRole;
        }

        if (nextAgency !== 'all') {
            params.agency = nextAgency;
        }

        if (nextStatus !== 'all') {
            params.status = nextStatus;
        }

        if (nextArchived !== 'none' && nextArchived !== '') {
            params.archived = nextArchived;
        }

        if (overrides.page !== undefined && overrides.page > 1) {
            params.page = String(overrides.page);
        }

        return params;
    };

    const visit = (overrides: Parameters<typeof buildParams>[0] = {}) => {
        router.get(usersIndexRoute.url(), buildParams(overrides), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const applyFilters = () => visit({ page: 1 });

    const resetFilters = () => {
        setQuery('');
        setSelectedRole('all');
        setSelectedAgency('all');
        setSelectedStatus('all');
        setSelectedArchived('none');
        router.get(
            usersIndexRoute.url(),
            {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const openCreate = () => {
        setEditingUser(null);
        setModalOpen(true);
    };

    const openEdit = (user: AdministrationUser) => {
        setEditingUser(user);
        setModalOpen(true);
    };

    const openDetail = (user: AdministrationUser) => {
        setSelectedUser(user);
        setDrawerOpen(true);
    };

    // Actions utilisateur
    const handlePasswordReset = (user: AdministrationUser) => {
        if (
            !window.confirm(
                `Réinitialiser le mot de passe de ${user.name} ? Un mot de passe temporaire unique sera généré.`,
            )
        ) {
            return;
        }

        setCredUserName(user.name);
        setCredUserEmail(user.email);
        router.post(
            usersActions.password.reset.url({ user: user.uuid }),
            {},
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const temp = (page.flash as CredentialsFlash)
                        .temporaryPassword;

                    if (temp) {
                        setCredPassword(temp);
                        setCredentialsOpen(true);
                    }
                },
            },
        );
    };

    const handlePinReset = (user: AdministrationUser) => {
        if (
            !window.confirm(
                `Envoyer un code OTP de réinitialisation de code PIN à ${user.name} ?`,
            )
        ) {
            return;
        }

        setCredUserName(user.name);
        setCredUserEmail(user.email);
        router.post(
            usersActions.pin.reset.url({ user: user.uuid }),
            {},
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const challenge = (page.flash as CredentialsFlash)
                        .otpChallengeUuid;

                    if (challenge) {
                        setCredOtp(challenge);
                        setCredentialsOpen(true);
                    }
                },
            },
        );
    };

    const handleUnlock = (user: AdministrationUser) => {
        if (
            !window.confirm(
                `Déverrouiller le compte de ${user.name} et remettre à zéro le compteur de tentatives ?`,
            )
        ) {
            return;
        }

        router.post(
            usersActions.unlock.url({ user: user.uuid }),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (selectedUser?.uuid === user.uuid) {
                        setSelectedUser({ ...user, status: 'active' });
                    }
                },
            },
        );
    };

    const handleArchive = (user: AdministrationUser) => {
        if (
            !window.confirm(
                `Archiver le compte de ${user.name} ? L’utilisateur ne pourra plus se connecter.`,
            )
        ) {
            return;
        }

        router.delete(usersActions.destroy.url({ user: user.uuid }), {
            preserveScroll: true,
            onSuccess: () => {
                setDrawerOpen(false);
            },
        });
    };

    const handleRestore = (user: AdministrationUser) => {
        if (
            !window.confirm(
                `Restaurer le compte de ${user.name} ? L’accès sera réactivé.`,
            )
        ) {
            return;
        }

        router.post(
            usersActions.restore.url({ user: user.uuid }),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDrawerOpen(false);
                },
            },
        );
    };

    const filterSelect = (
        label: string,
        value: string,
        onChange: (value: string) => void,
        options: [string, string][],
    ) => (
        <label className="cash-filter-field relative block">
            <span className="sr-only">{label}</span>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="cash-filter-select h-11 w-full appearance-none rounded-xl bg-muted/55 px-3 pr-8 text-xs font-bold text-foreground ring-1 ring-black/[0.045] transition-all outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
            >
                {options.map(([optionValue, optionLabel]) => (
                    <option key={optionValue} value={optionValue}>
                        {optionLabel}
                    </option>
                ))}
            </select>
            <ChevronRight
                className="pointer-events-none absolute top-1/2 right-3 size-3.5 -translate-y-1/2 rotate-90 text-muted-foreground"
                strokeWidth={1.7}
            />
        </label>
    );

    return (
        <>
            <Head title="Utilisateurs & Accès" />

            <div className="cash-page">
                {/* Hero / Page Header */}
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Administration / Utilisateurs
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Comptes & Utilisateurs
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            Gérez les collaborateurs, profils d’accès,
                            périmètres d’agence et la sécurité des comptes OPS.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2.5">
                        {permissions?.canManage && (
                            <button
                                type="button"
                                className="btn btn-primary"
                                onClick={openCreate}
                            >
                                <Plus className="size-4" /> Nouvel utilisateur
                            </button>
                        )}
                    </div>
                </div>

                {/* KPI Cards Strip (4 colonnes alignées sur PC) */}
                <section className="cash-ledger-grid cash-reveal cash-reveal-delay-1 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiCard
                        icon={UsersIcon}
                        label="Utilisateurs"
                        hint="enregistrés au référentiel"
                        value={counts.total}
                        tone="navy"
                    />
                    <KpiCard
                        icon={CheckCircle2}
                        label="Actifs"
                        hint="accès opérationnel autorisé"
                        value={counts.active}
                        tone="green"
                    />
                    <KpiCard
                        icon={Building2}
                        label="Agence & Terrain"
                        hint="guichets, gares & équipages"
                        value={counts.operational}
                        tone="gold"
                    />
                    <KpiCard
                        icon={AlertTriangle}
                        label="Attention requise"
                        hint="comptes suspendus ou bloqués"
                        value={counts.lockedOrSuspended}
                        tone={counts.lockedOrSuspended > 0 ? 'rose' : 'muted'}
                    />
                </section>

                {/* Filter Bar */}
                <div className="cash-filterbar cash-reveal cash-reveal-delay-2">
                    <Search className="cash-filter-icon size-4" />
                    <input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                applyFilters();
                            }
                        }}
                        aria-label="Rechercher par nom, email ou téléphone"
                        placeholder="Rechercher par nom, email, téléphone…"
                        className="cash-filter-search"
                    />

                    {filterSelect(
                        'Rôle',
                        selectedRole,
                        (value) => {
                            setSelectedRole(value);
                            visit({ role: value, page: 1 });
                        },
                        [
                            ['all', 'Tous les rôles'],
                            ...roles.map(
                                (r) =>
                                    [r.code, `${r.label}`] as [string, string],
                            ),
                        ],
                    )}

                    {filterSelect(
                        'Agence',
                        selectedAgency,
                        (value) => {
                            setSelectedAgency(value);
                            visit({ agency: value, page: 1 });
                        },
                        [
                            ['all', 'Toutes les agences'],
                            ...agencies.map(
                                (a) =>
                                    [a.uuid, `${a.code} · ${a.name}`] as [
                                        string,
                                        string,
                                    ],
                            ),
                        ],
                    )}

                    {filterSelect(
                        'Statut',
                        selectedStatus,
                        (value) => {
                            setSelectedStatus(value);
                            visit({ status: value, page: 1 });
                        },
                        [
                            ['all', 'Tous les statuts'],
                            ...statuses.map(
                                (s) =>
                                    [
                                        s,
                                        s === 'active'
                                            ? 'Actif'
                                            : s === 'suspended'
                                              ? 'Suspendu'
                                              : s === 'locked'
                                                ? 'Verrouillé'
                                                : s,
                                    ] as [string, string],
                            ),
                        ],
                    )}

                    {filterSelect(
                        'Archivage',
                        selectedArchived,
                        (value) => {
                            setSelectedArchived(value);
                            visit({
                                archived: value === 'none' ? '' : value,
                                page: 1,
                            });
                        },
                        [
                            ['none', 'Comptes actifs seuls'],
                            ['with', 'Tous (avec archivés)'],
                            ['only', 'Archivés uniquement'],
                        ],
                    )}

                    <button
                        type="button"
                        className="btn btn-navy cash-filter-submit"
                        onClick={applyFilters}
                    >
                        <SlidersHorizontal className="size-4" /> Filtrer
                    </button>
                    <button
                        type="button"
                        className="btn btn-ghost cash-filter-reset"
                        onClick={resetFilters}
                    >
                        Réinitialiser
                    </button>
                </div>

                {/* Table Section */}
                <section className="cash-table-card cash-reveal cash-reveal-delay-3">
                    {paginatedUsers.data.length === 0 ? (
                        <div className="px-6 py-16 text-center text-sm text-muted-foreground">
                            <UsersIcon className="mx-auto mb-2 size-8 text-muted-foreground/40" />
                            <p className="font-semibold text-foreground">
                                Aucun utilisateur trouvé
                            </p>
                            <p className="mt-1 text-xs">
                                Ajustez vos critères de recherche ou
                                réinitialisez les filtres.
                            </p>
                            <button
                                type="button"
                                onClick={resetFilters}
                                className="btn btn-ghost mt-4 text-xs"
                            >
                                Réinitialiser les filtres
                            </button>
                        </div>
                    ) : (
                        <>
                            <div className="cash-table-scroll overflow-x-auto">
                                <table className="w-full min-w-[950px] text-left">
                                    <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Collaborateur
                                            </th>
                                            <th className="px-4 py-3">
                                                Rôle attribué
                                            </th>
                                            <th className="px-4 py-3">
                                                Périmètre / Agence
                                            </th>
                                            <th className="px-4 py-3">
                                                Contact
                                            </th>
                                            <th className="px-4 py-3">
                                                Statut
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {paginatedUsers.data.map((user) => {
                                            const isArchived = Boolean(
                                                user.deleted_at,
                                            );
                                            const isLocked =
                                                user.status === 'locked';

                                            const statusTone = isArchived
                                                ? 'muted'
                                                : isLocked
                                                  ? 'destructive'
                                                  : user.status === 'suspended'
                                                    ? 'gold'
                                                    : 'blue';

                                            const statusLabel = isArchived
                                                ? 'Archivé'
                                                : isLocked
                                                  ? 'Verrouillé'
                                                  : user.status === 'suspended'
                                                    ? 'Suspendu'
                                                    : 'Actif';

                                            const roleCategoryLabel =
                                                user.role?.category ===
                                                'central'
                                                    ? 'Siège'
                                                    : user.role?.category ===
                                                        'agency'
                                                      ? 'Agence'
                                                      : 'Terrain';

                                            const roleBadgeTone =
                                                user.role?.category ===
                                                'central'
                                                    ? 'bg-kora/10 text-kora dark:bg-digital/20 dark:text-digital'
                                                    : user.role?.category ===
                                                        'agency'
                                                      ? 'bg-gold/15 text-gold'
                                                      : 'bg-emerald-500/10 text-emerald-600';

                                            return (
                                                <tr
                                                    key={user.uuid}
                                                    className="group cursor-pointer border-b border-black/[0.045] transition-colors hover:bg-muted/30"
                                                    onClick={() =>
                                                        openDetail(user)
                                                    }
                                                >
                                                    {/* Nom & Email */}
                                                    <td className="px-4 py-3.5">
                                                        <div className="flex items-center gap-3">
                                                            <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-gold/15 text-xs font-black text-gold">
                                                                {user.name
                                                                    .split(' ')
                                                                    .map(
                                                                        (n) =>
                                                                            n[0],
                                                                    )
                                                                    .filter(
                                                                        Boolean,
                                                                    )
                                                                    .slice(0, 2)
                                                                    .join('')
                                                                    .toUpperCase()}
                                                            </span>
                                                            <div>
                                                                <p className="text-xs font-black text-foreground">
                                                                    {user.name}
                                                                </p>
                                                                <span className="flex items-center gap-1 text-[0.6875rem] text-muted-foreground">
                                                                    <Mail className="size-2.5 text-muted-foreground/70" />
                                                                    {user.email}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    {/* Rôle */}
                                                    <td className="px-4 py-3.5">
                                                        <div className="flex items-center gap-1.5">
                                                            <span
                                                                className={cn(
                                                                    'rounded-md px-2 py-0.5 text-[0.625rem] font-black tracking-wider uppercase',
                                                                    roleBadgeTone,
                                                                )}
                                                            >
                                                                {
                                                                    roleCategoryLabel
                                                                }
                                                            </span>
                                                            <span className="text-xs font-bold text-foreground">
                                                                {user.role
                                                                    ?.label ??
                                                                    'Sans rôle'}
                                                            </span>
                                                        </div>
                                                    </td>

                                                    {/* Agence */}
                                                    <td className="px-4 py-3.5">
                                                        {user.agency ? (
                                                            <div className="flex items-center gap-1.5 text-xs">
                                                                <Building2 className="size-3.5 text-gold" />
                                                                <span className="font-semibold text-foreground">
                                                                    {
                                                                        user
                                                                            .agency
                                                                            .name
                                                                    }
                                                                </span>
                                                                <span className="font-mono text-[0.6875rem] text-muted-foreground">
                                                                    (
                                                                    {
                                                                        user
                                                                            .agency
                                                                            .code
                                                                    }
                                                                    )
                                                                </span>
                                                            </div>
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground italic">
                                                                Réseau central /
                                                                Siège
                                                            </span>
                                                        )}
                                                    </td>

                                                    {/* Contact */}
                                                    <td className="px-4 py-3.5 font-mono text-xs">
                                                        {user.phone ? (
                                                            <div className="flex items-center gap-1 text-muted-foreground">
                                                                <Phone className="size-3 text-gold" />
                                                                <span>
                                                                    {user.phone}
                                                                </span>
                                                            </div>
                                                        ) : (
                                                            <span className="text-muted-foreground/60">
                                                                —
                                                            </span>
                                                        )}
                                                    </td>

                                                    {/* Statut */}
                                                    <td className="px-4 py-3.5">
                                                        <KoraStatusBadge
                                                            label={statusLabel}
                                                            tone={statusTone}
                                                        />
                                                    </td>

                                                    {/* Actions */}
                                                    <td
                                                        className="px-4 py-3.5 text-right"
                                                        onClick={(e) =>
                                                            e.stopPropagation()
                                                        }
                                                    >
                                                        <div className="flex items-center justify-end gap-1.5">
                                                            {permissions?.canManage &&
                                                                !isArchived && (
                                                                    <>
                                                                        {isLocked && (
                                                                            <button
                                                                                type="button"
                                                                                className="grid size-8 place-items-center rounded-lg bg-destructive/15 text-destructive transition hover:bg-destructive/25"
                                                                                onClick={() =>
                                                                                    handleUnlock(
                                                                                        user,
                                                                                    )
                                                                                }
                                                                                title="Déverrouiller le compte"
                                                                            >
                                                                                <Unlock className="size-3.5" />
                                                                            </button>
                                                                        )}
                                                                        <button
                                                                            type="button"
                                                                            className="grid size-8 place-items-center rounded-lg bg-gold/15 text-gold transition hover:bg-gold/25"
                                                                            onClick={() =>
                                                                                handlePasswordReset(
                                                                                    user,
                                                                                )
                                                                            }
                                                                            title="Réinitialiser le mot de passe"
                                                                        >
                                                                            <KeyRound className="size-3.5" />
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            className="grid size-8 place-items-center rounded-lg bg-muted text-foreground transition hover:bg-muted/80"
                                                                            onClick={() =>
                                                                                openEdit(
                                                                                    user,
                                                                                )
                                                                            }
                                                                            title="Modifier le compte"
                                                                        >
                                                                            <Edit3 className="size-3.5" />
                                                                        </button>
                                                                    </>
                                                                )}
                                                            <button
                                                                type="button"
                                                                className="btn btn-ghost px-2.5 py-1 text-xs"
                                                                onClick={() =>
                                                                    openDetail(
                                                                        user,
                                                                    )
                                                                }
                                                            >
                                                                Détail →
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            {/* Pagination footer */}
                            {paginatedUsers.total > 0 && (
                                <div className="flex items-center justify-between border-t border-black/[0.045] px-4 py-3 text-xs text-muted-foreground">
                                    <span>
                                        {paginatedUsers.from ?? 1}–
                                        {paginatedUsers.to ??
                                            paginatedUsers.data.length}{' '}
                                        sur {paginatedUsers.total} utilisateurs
                                    </span>
                                    <div className="flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-all hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                                            onClick={() =>
                                                visit({
                                                    page:
                                                        paginatedUsers.current_page -
                                                        1,
                                                })
                                            }
                                            disabled={
                                                paginatedUsers.current_page <= 1
                                            }
                                            aria-label="Page précédente"
                                        >
                                            <ChevronLeft className="size-3.5" />
                                        </button>
                                        <span className="px-2 font-bold text-foreground">
                                            Page {paginatedUsers.current_page} /{' '}
                                            {paginatedUsers.last_page}
                                        </span>
                                        <button
                                            type="button"
                                            className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-all hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                                            onClick={() =>
                                                visit({
                                                    page:
                                                        paginatedUsers.current_page +
                                                        1,
                                                })
                                            }
                                            disabled={
                                                paginatedUsers.current_page >=
                                                paginatedUsers.last_page
                                            }
                                            aria-label="Page suivante"
                                        >
                                            <ChevronRight className="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </section>
            </div>

            {/* Modale de création / modification */}
            <UserFormModal
                open={modalOpen}
                user={editingUser}
                roles={roles}
                agencies={agencies}
                onOpenChange={setModalOpen}
            />

            {/* Drawer 360° de consultation & gestion */}
            <UserDetailDrawer
                open={drawerOpen}
                user={selectedUser}
                canManage={permissions?.canManage}
                onOpenChange={setDrawerOpen}
                onEdit={(user) => {
                    setDrawerOpen(false);
                    openEdit(user);
                }}
                onPasswordReset={handlePasswordReset}
                onPinReset={handlePinReset}
                onUnlock={handleUnlock}
                onArchive={handleArchive}
                onRestore={handleRestore}
            />

            {/* Dialogue de restitution sécurisée des identifiants */}
            <UserCredentialsDialog
                open={isCredentialsOpen}
                temporaryPassword={activePassword}
                otpChallengeUuid={activeOtp}
                userName={credUserName}
                userEmail={credUserEmail}
                onClose={() => {
                    setCredentialsOpen(false);
                    setCredPassword(null);
                    setCredOtp(null);

                    if (flashTemp) {
                        setDismissedSecret(flashTemp);
                    } else if (flashOtp) {
                        setDismissedSecret(flashOtp);
                    }
                }}
            />
        </>
    );
}
