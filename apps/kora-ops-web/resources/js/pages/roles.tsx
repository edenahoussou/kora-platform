import { Head, router } from '@inertiajs/react';
import {
    ChevronRight,
    Edit3,
    Key,
    Lock,
    Plus,
    Power,
    RotateCcw,
    Search,
    Shield,
    ShieldCheck,
    Sparkles,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { RoleFormModal } from '@/components/roles/role-form-modal';
import { RoleMatrixDrawer } from '@/components/roles/role-matrix-drawer';
import { cn } from '@/lib/utils';
import rolesActions from '@/routes/roles';
import type {
    AdministrationPermission,
    AdministrationRole,
} from '@/types/administration';

type PermissionGroup = {
    domain: string;
    label: string;
    items: AdministrationPermission[];
};

type RolesPageProps = {
    roles?: AdministrationRole[];
    categories?: Array<{
        value: AdministrationRole['category'];
        label: string;
    }>;
    permissions?: PermissionGroup[];
    canManage?: boolean;
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
    tone?: 'navy' | 'gold' | 'blue' | 'muted' | 'green';
}) {
    const toneStyles = {
        navy: 'bg-kora text-white',
        gold: 'bg-gold/15 text-gold',
        blue: 'bg-digital/10 text-digital',
        muted: 'bg-muted text-muted-foreground',
        green: 'bg-emerald-500/10 text-emerald-600',
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

export default function RolesPage({
    roles = [],
    categories = [
        { value: 'central', label: 'Siège / Central' },
        { value: 'agency', label: 'Agence' },
        { value: 'terrain', label: 'Terrain' },
    ],
    permissions = [],
    canManage = false,
}: RolesPageProps) {
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedCategory, setSelectedCategory] = useState<string>('all');
    const [selectedStatus, setSelectedStatus] = useState<string>('all');

    const [formModalOpen, setFormModalOpen] = useState(false);
    const [editingRole, setEditingRole] = useState<AdministrationRole | null>(
        null,
    );

    const [matrixDrawerOpen, setMatrixDrawerOpen] = useState(false);
    const [selectedRoleForMatrix, setSelectedRoleForMatrix] =
        useState<AdministrationRole | null>(null);

    // KPI stats
    const stats = useMemo(() => {
        const systemCount = roles.filter((r) => r.isSystem).length;
        const customCount = roles.filter((r) => !r.isSystem).length;
        const totalPermissions = permissions.reduce(
            (acc, group) => acc + group.items.length,
            0,
        );

        return {
            totalRoles: roles.length,
            systemCount,
            customCount,
            totalPermissions,
        };
    }, [roles, permissions]);

    // Filtrage des rôles
    const filteredRoles = useMemo(() => {
        return roles.filter((role) => {
            const matchesQuery =
                !searchQuery.trim() ||
                role.label.toLowerCase().includes(searchQuery.toLowerCase()) ||
                role.code.toLowerCase().includes(searchQuery.toLowerCase()) ||
                Boolean(
                    role.description
                        ?.toLowerCase()
                        .includes(searchQuery.toLowerCase()),
                );

            const matchesCategory =
                selectedCategory === 'all' ||
                role.category === selectedCategory;

            const matchesStatus =
                selectedStatus === 'all' ||
                (selectedStatus === 'active' && role.isActive) ||
                (selectedStatus === 'inactive' && !role.isActive);

            return matchesQuery && matchesCategory && matchesStatus;
        });
    }, [roles, searchQuery, selectedCategory, selectedStatus]);

    const openCreateRole = () => {
        setEditingRole(null);
        setFormModalOpen(true);
    };

    const openEditRole = (role: AdministrationRole) => {
        setEditingRole(role);
        setFormModalOpen(true);
    };

    const openMatrix = (role: AdministrationRole) => {
        setSelectedRoleForMatrix(role);
        setMatrixDrawerOpen(true);
    };

    const handleDeactivate = (role: AdministrationRole) => {
        if (
            !window.confirm(
                `Désactiver le rôle ${role.label} ? Les utilisateurs ayant ce rôle perdront temporairement leurs accès liés.`,
            )
        ) {
            return;
        }

        router.delete(rolesActions.destroy.url({ role: role.code }), {
            preserveScroll: true,
        });
    };

    const handleRestore = (role: AdministrationRole) => {
        if (!window.confirm(`Réactiver le rôle ${role.label} ?`)) {
            return;
        }

        router.post(
            rolesActions.restore.url({ role: role.code }),
            {},
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head title="Rôles & Permissions" />

            <div className="cash-page">
                {/* Page Hero */}
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Administration / Rôles & Habilitations
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Rôles & Habilitations
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            Consultez les profils d’accès, paramétrez les rôles
                            sur-mesure et contrôlez la matrice des permissions
                            KORA.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2.5">
                        {canManage && (
                            <button
                                type="button"
                                className="btn btn-primary"
                                onClick={openCreateRole}
                            >
                                <Plus className="size-4" /> Nouveau rôle
                            </button>
                        )}
                    </div>
                </div>

                {/* KPI Strip (4 colonnes alignées sur PC) */}
                <section className="cash-ledger-grid cash-reveal cash-reveal-delay-1 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiCard
                        icon={ShieldCheck}
                        label="Rôles référencés"
                        hint="profils d’accès OPS"
                        value={stats.totalRoles}
                        tone="navy"
                    />
                    <KpiCard
                        icon={Lock}
                        label="Rôles système"
                        hint="socle immuable protégé"
                        value={stats.systemCount}
                        tone="blue"
                    />
                    <KpiCard
                        icon={Sparkles}
                        label="Rôles personnalisés"
                        hint="configurés sur-mesure"
                        value={stats.customCount}
                        tone="gold"
                    />
                    <KpiCard
                        icon={Key}
                        label="Permissions catalogue"
                        hint="habilitations granulaires"
                        value={stats.totalPermissions}
                        tone="green"
                    />
                </section>

                {/* Filterbar */}
                <div className="cash-filterbar cash-reveal cash-reveal-delay-2">
                    <Search className="cash-filter-icon size-4" />
                    <input
                        value={searchQuery}
                        onChange={(event) => setSearchQuery(event.target.value)}
                        placeholder="Rechercher par nom de rôle, code ou description…"
                        className="cash-filter-search"
                        aria-label="Rechercher un rôle"
                    />

                    {/* Catégories Filter */}
                    <label className="cash-filter-field relative block">
                        <span className="sr-only">Catégorie</span>
                        <select
                            value={selectedCategory}
                            onChange={(e) =>
                                setSelectedCategory(e.target.value)
                            }
                            className="cash-filter-select h-11 w-full appearance-none rounded-xl bg-muted/55 px-3 pr-8 text-xs font-bold text-foreground ring-1 ring-black/[0.045] transition outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
                        >
                            <option value="all">Toutes les catégories</option>
                            {categories.map((cat) => (
                                <option key={cat.value} value={cat.value}>
                                    {cat.label}
                                </option>
                            ))}
                        </select>
                        <ChevronRight
                            className="pointer-events-none absolute top-1/2 right-3 size-3.5 -translate-y-1/2 rotate-90 text-muted-foreground"
                            strokeWidth={1.7}
                        />
                    </label>

                    {/* Statut Filter */}
                    <label className="cash-filter-field relative block">
                        <span className="sr-only">Statut</span>
                        <select
                            value={selectedStatus}
                            onChange={(e) => setSelectedStatus(e.target.value)}
                            className="cash-filter-select h-11 w-full appearance-none rounded-xl bg-muted/55 px-3 pr-8 text-xs font-bold text-foreground ring-1 ring-black/[0.045] transition outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
                        >
                            <option value="all">Tous les statuts</option>
                            <option value="active">Actifs</option>
                            <option value="inactive">Désactivés</option>
                        </select>
                        <ChevronRight
                            className="pointer-events-none absolute top-1/2 right-3 size-3.5 -translate-y-1/2 rotate-90 text-muted-foreground"
                            strokeWidth={1.7}
                        />
                    </label>

                    {(searchQuery ||
                        selectedCategory !== 'all' ||
                        selectedStatus !== 'all') && (
                        <button
                            type="button"
                            className="btn btn-ghost cash-filter-reset"
                            onClick={() => {
                                setSearchQuery('');
                                setSelectedCategory('all');
                                setSelectedStatus('all');
                            }}
                        >
                            Réinitialiser
                        </button>
                    )}
                </div>

                {/* Grid of Bento Cards */}
                <div className="cash-reveal cash-reveal-delay-3 grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
                    {filteredRoles.length === 0 ? (
                        <div className="col-span-full rounded-2xl border border-border bg-card p-12 text-center text-muted-foreground">
                            <Shield className="mx-auto mb-2 size-8 text-muted-foreground/40" />
                            <p className="text-sm font-semibold text-foreground">
                                Aucun rôle trouvé
                            </p>
                            <p className="mt-1 text-xs">
                                Aucun profil ne correspond aux critères de
                                recherche renseignés.
                            </p>
                        </div>
                    ) : (
                        filteredRoles.map((role) => {
                            const categoryBadgeTone =
                                role.category === 'central'
                                    ? 'bg-kora/10 text-kora dark:bg-digital/20 dark:text-digital'
                                    : role.category === 'agency'
                                      ? 'bg-gold/15 text-gold'
                                      : 'bg-emerald-500/10 text-emerald-600';

                            const categoryLabel =
                                role.category === 'central'
                                    ? 'Siège Central'
                                    : role.category === 'agency'
                                      ? 'Agence'
                                      : 'Terrain';

                            const permissionsCount =
                                role.permissions?.length ?? 0;

                            // Extraction des domaines couverts
                            const domainsCovered = Array.from(
                                new Set(
                                    (role.permissions ?? []).map(
                                        (p) => p.code.split('.')[0],
                                    ),
                                ),
                            ).slice(0, 4);

                            return (
                                <article
                                    key={role.code}
                                    className="group relative flex flex-col justify-between rounded-2xl border border-border bg-card p-5 shadow-xs transition-all duration-300 hover:border-gold/50 hover:shadow-md"
                                >
                                    <div>
                                        {/* Top Badges */}
                                        <div className="flex items-center justify-between gap-2">
                                            <div className="flex items-center gap-1.5">
                                                <span
                                                    className={cn(
                                                        'rounded-md px-2 py-0.5 text-[0.625rem] font-black tracking-wider uppercase',
                                                        categoryBadgeTone,
                                                    )}
                                                >
                                                    {categoryLabel}
                                                </span>
                                                {role.isSystem ? (
                                                    <span className="rounded-md bg-muted px-2 py-0.5 text-[0.625rem] font-bold text-muted-foreground">
                                                        Système
                                                    </span>
                                                ) : (
                                                    <span className="rounded-md bg-gold/10 px-2 py-0.5 text-[0.625rem] font-bold text-gold">
                                                        Personnalisé
                                                    </span>
                                                )}
                                            </div>

                                            <KoraStatusBadge
                                                label={
                                                    role.isActive
                                                        ? 'Actif'
                                                        : 'Désactivé'
                                                }
                                                tone={
                                                    role.isActive
                                                        ? 'blue'
                                                        : 'muted'
                                                }
                                            />
                                        </div>

                                        {/* Role Title & Code */}
                                        <div className="mt-4">
                                            <h3 className="text-base font-black text-foreground transition group-hover:text-gold">
                                                {role.label}
                                            </h3>
                                            <span className="mt-0.5 block font-mono text-[0.65rem] text-muted-foreground">
                                                Code: {role.code}
                                            </span>
                                        </div>

                                        {/* Description */}
                                        <p className="mt-2.5 line-clamp-2 min-h-[2rem] text-xs leading-relaxed text-muted-foreground">
                                            {role.description || (
                                                <span className="text-muted-foreground/60 italic">
                                                    Profil d’accès configuré
                                                    pour les opérations KORA
                                                    BUS.
                                                </span>
                                            )}
                                        </p>

                                        {/* Permissions Count & Domains Preview */}
                                        <div className="mt-4 border-t border-border/60 pt-3">
                                            <div className="flex items-center justify-between text-xs">
                                                <span className="font-semibold text-muted-foreground">
                                                    Habilitations actives
                                                </span>
                                                <span className="font-mono font-black text-foreground">
                                                    {permissionsCount}
                                                </span>
                                            </div>

                                            {domainsCovered.length > 0 && (
                                                <div className="mt-2 flex flex-wrap gap-1">
                                                    {domainsCovered.map(
                                                        (domain) => (
                                                            <span
                                                                key={domain}
                                                                className="rounded bg-muted/60 px-1.5 py-0.5 font-mono text-[0.5625rem] text-muted-foreground"
                                                            >
                                                                {domain}
                                                            </span>
                                                        ),
                                                    )}
                                                    {(role.permissions ?? [])
                                                        .length > 4 && (
                                                        <span className="rounded bg-muted/40 px-1.5 py-0.5 font-mono text-[0.5625rem] text-muted-foreground">
                                                            +
                                                            {(
                                                                role.permissions ??
                                                                []
                                                            ).length - 4}
                                                        </span>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    {/* Action Buttons */}
                                    <div className="mt-5 flex items-center justify-between gap-2 border-t border-border/60 pt-3">
                                        <button
                                            type="button"
                                            onClick={() => openMatrix(role)}
                                            className="btn btn-ghost gap-1.5 px-2 text-xs font-bold text-foreground hover:text-gold"
                                        >
                                            <Key className="size-3.5 text-gold" />
                                            {role.isSystem
                                                ? 'Voir permissions'
                                                : 'Matrice permissions'}{' '}
                                            →
                                        </button>

                                        {canManage && !role.isSystem && (
                                            <div className="flex items-center gap-1">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        openEditRole(role)
                                                    }
                                                    className="grid size-7 place-items-center rounded-lg bg-muted text-muted-foreground transition hover:text-foreground"
                                                    title="Modifier le libellé et la description"
                                                >
                                                    <Edit3 className="size-3.5" />
                                                </button>

                                                {role.isActive ? (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleDeactivate(
                                                                role,
                                                            )
                                                        }
                                                        className="grid size-7 place-items-center rounded-lg bg-destructive/10 text-destructive transition hover:bg-destructive/20"
                                                        title="Désactiver ce rôle"
                                                    >
                                                        <Power className="size-3.5" />
                                                    </button>
                                                ) : (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleRestore(role)
                                                        }
                                                        className="grid size-7 place-items-center rounded-lg bg-emerald-500/10 text-emerald-600 transition hover:bg-emerald-500/20"
                                                        title="Réactiver ce rôle"
                                                    >
                                                        <RotateCcw className="size-3.5" />
                                                    </button>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </article>
                            );
                        })
                    )}
                </div>
            </div>

            {/* Modale de création / modification de rôle */}
            <RoleFormModal
                open={formModalOpen}
                role={editingRole}
                categories={categories}
                onOpenChange={setFormModalOpen}
            />

            {/* Drawer XXL de la Matrice de Permissions */}
            <RoleMatrixDrawer
                open={matrixDrawerOpen}
                role={selectedRoleForMatrix}
                permissions={permissions}
                canManage={canManage}
                onOpenChange={setMatrixDrawerOpen}
                onEditMetadata={(role) => {
                    setMatrixDrawerOpen(false);
                    openEditRole(role);
                }}
            />
        </>
    );
}
