import { router } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    ChevronRight,
    Edit3,
    Lock,
    Search,
    Shield,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import roles from '@/routes/roles';
import type {
    AdministrationPermission,
    AdministrationRole,
} from '@/types/administration';

type PermissionGroup = {
    domain: string;
    label: string;
    items: AdministrationPermission[];
};

type Props = {
    open: boolean;
    role: AdministrationRole | null;
    permissions: PermissionGroup[];
    canManage?: boolean;
    onOpenChange: (open: boolean) => void;
    onEditMetadata?: (role: AdministrationRole) => void;
};

export function RoleMatrixDrawer({
    open,
    role,
    permissions = [],
    canManage = false,
    onOpenChange,
    onEditMetadata,
}: Props) {
    if (!open || !role) {
        return null;
    }

    return (
        <RoleMatrixDrawerContent
            key={role.code}
            role={role}
            permissions={permissions}
            canManage={canManage}
            onOpenChange={onOpenChange}
            onEditMetadata={onEditMetadata}
        />
    );
}

function RoleMatrixDrawerContent({
    role,
    permissions,
    canManage,
    onOpenChange,
    onEditMetadata,
}: {
    role: AdministrationRole;
    permissions: PermissionGroup[];
    canManage: boolean;
    onOpenChange: (open: boolean) => void;
    onEditMetadata?: (role: AdministrationRole) => void;
}) {
    const isSystemRole = Boolean(role.isSystem);

    const [selectedCodes, setSelectedCodes] = useState<Set<string>>(
        () => new Set(role.permissions?.map((p) => p.code) ?? []),
    );
    const [searchQuery, setSearchQuery] = useState('');
    const [saving, setSaving] = useState(false);
    const [collapsedDomains, setCollapsedDomains] = useState<
        Record<string, boolean>
    >({});

    const totalPermissionsCount = useMemo(
        () => permissions.reduce((acc, group) => acc + group.items.length, 0),
        [permissions],
    );

    // Filtrer les permissions selon la recherche
    const filteredGroups = useMemo(() => {
        const query = searchQuery.trim().toLowerCase();

        if (!query) {
            return permissions;
        }

        return permissions
            .map((group) => {
                const matches = group.items.filter(
                    (p) =>
                        p.label.toLowerCase().includes(query) ||
                        p.code.toLowerCase().includes(query) ||
                        group.label.toLowerCase().includes(query),
                );

                return {
                    ...group,
                    items: matches,
                };
            })
            .filter((group) => group.items.length > 0);
    }, [permissions, searchQuery]);

    if (!open || !role) {
        return null;
    }

    const togglePermission = (code: string) => {
        if (isSystemRole || !canManage) {
            return;
        }

        setSelectedCodes((prev) => {
            const next = new Set(prev);

            if (next.has(code)) {
                next.delete(code);
            } else {
                next.add(code);
            }

            return next;
        });
    };

    const toggleDomain = (group: PermissionGroup) => {
        if (isSystemRole || !canManage) {
            return;
        }

        const groupCodes = group.items.map((p) => p.code);
        const allSelected = groupCodes.every((code) => selectedCodes.has(code));

        setSelectedCodes((prev) => {
            const next = new Set(prev);

            if (allSelected) {
                groupCodes.forEach((code) => next.delete(code));
            } else {
                groupCodes.forEach((code) => next.add(code));
            }

            return next;
        });
    };

    const selectAll = () => {
        if (isSystemRole || !canManage) {
            return;
        }

        const all = new Set<string>();
        permissions.forEach((group) => {
            group.items.forEach((p) => all.add(p.code));
        });
        setSelectedCodes(all);
    };

    const deselectAll = () => {
        if (isSystemRole || !canManage) {
            return;
        }

        setSelectedCodes(new Set());
    };

    const toggleDomainCollapse = (domain: string) => {
        setCollapsedDomains((prev) => ({
            ...prev,
            [domain]: !prev[domain],
        }));
    };

    const handleSave = () => {
        if (isSystemRole || !canManage) {
            return;
        }

        setSaving(true);
        router.put(
            roles.permissions.update.url({ role: role.code }),
            {
                permissions: Array.from(selectedCodes),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSaving(false);
                    onOpenChange(false);
                },
                onError: () => {
                    setSaving(false);
                },
            },
        );
    };

    const categoryBadgeTone =
        role.category === 'central'
            ? 'bg-kora text-white dark:bg-digital/20 dark:text-digital'
            : role.category === 'agency'
              ? 'bg-gold/15 text-gold'
              : 'bg-emerald-500/15 text-emerald-600';

    const categoryLabel =
        role.category === 'central'
            ? 'Siège Central'
            : role.category === 'agency'
              ? 'Agence'
              : 'Terrain';

    return (
        <div className="fixed inset-0 z-50 overflow-hidden" role="presentation">
            {/* Backdrop */}
            <div
                className="companies-panel-backdrop absolute inset-0 bg-[rgba(0,29,78,0.3)] backdrop-blur-[9px]"
                onClick={() => onOpenChange(false)}
            />

            {/* Sliding Panel XXL */}
            <aside
                className="companies-panel absolute top-0 right-0 flex h-full w-full max-w-[48rem] flex-col border-l border-border bg-card shadow-[-20px_0_60px_rgba(0,29,78,0.2)]"
                role="dialog"
                aria-modal="true"
                aria-label={`Matrice des permissions ${role.label}`}
            >
                {/* Header */}
                <header className="border-b border-border px-6 py-5">
                    <div className="flex items-start justify-between gap-4">
                        <div className="flex items-center gap-3">
                            <span className="grid size-11 place-items-center rounded-xl bg-gold/15 text-gold">
                                <Shield className="size-5" />
                            </span>
                            <div>
                                <span className="section-kicker">
                                    Matrice des habilitations
                                </span>
                                <h3 className="text-xl font-black text-foreground">
                                    {role.label}
                                </h3>
                                <div className="mt-1 flex flex-wrap items-center gap-2">
                                    <span
                                        className={cn(
                                            'rounded-md px-2 py-0.5 text-[0.625rem] font-black tracking-wider uppercase',
                                            categoryBadgeTone,
                                        )}
                                    >
                                        {categoryLabel}
                                    </span>
                                    {isSystemRole ? (
                                        <KoraStatusBadge
                                            label="Rôle Système Protégé"
                                            tone="blue"
                                        />
                                    ) : (
                                        <KoraStatusBadge
                                            label="Rôle Personnalisé"
                                            tone="gold"
                                        />
                                    )}
                                    <KoraStatusBadge
                                        label={
                                            role.isActive
                                                ? 'Actif'
                                                : 'Désactivé'
                                        }
                                        tone={role.isActive ? 'blue' : 'muted'}
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            {canManage && !isSystemRole && onEditMetadata && (
                                <button
                                    type="button"
                                    onClick={() => onEditMetadata(role)}
                                    className="btn btn-ghost gap-1.5 text-xs"
                                    title="Modifier le nom ou la description"
                                >
                                    <Edit3 className="size-3.5" />
                                    Modifier le rôle
                                </button>
                            )}
                            <button
                                type="button"
                                onClick={() => onOpenChange(false)}
                                className="cash-modal-close"
                                aria-label="Fermer"
                            >
                                <X className="size-4" />
                            </button>
                        </div>
                    </div>

                    {role.description && (
                        <p className="mt-3 text-xs leading-relaxed text-muted-foreground">
                            {role.description}
                        </p>
                    )}

                    {/* Avertissement rôle système */}
                    {isSystemRole && (
                        <div className="mt-3 flex items-start gap-2.5 rounded-xl border border-blue-500/30 bg-blue-500/10 p-3 text-xs text-blue-700 dark:text-blue-300">
                            <Lock className="mt-0.5 size-4 shrink-0 text-blue-500" />
                            <p className="text-[0.6875rem] leading-relaxed">
                                <strong>Rôle natif protégé :</strong> Les
                                permissions de ce rôle sont fixées par le modèle
                                de sécurité KORA BUS pour garantir l’intégrité
                                des opérations. Elles ne peuvent pas être
                                altérées.
                            </p>
                        </div>
                    )}
                </header>

                {/* Toolbar : Recherche & Actions globales */}
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-muted/20 px-6 py-3">
                    <div className="relative min-w-[220px] flex-1">
                        <Search className="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <input
                            type="search"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Rechercher une permission ou un domaine…"
                            className="h-9 w-full rounded-xl bg-card pr-3 pl-9 text-xs text-foreground ring-1 ring-border transition outline-none focus:ring-2 focus:ring-gold/40"
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        <span className="text-xs font-bold text-foreground">
                            <strong className="font-black text-gold">
                                {selectedCodes.size}
                            </strong>{' '}
                            / {totalPermissionsCount} permissions
                        </span>

                        {!isSystemRole && canManage && (
                            <div className="ml-1 flex items-center gap-1.5 border-l border-border pl-3">
                                <button
                                    type="button"
                                    onClick={selectAll}
                                    className="btn btn-ghost h-7 px-2 py-1 text-[0.6875rem]"
                                >
                                    Tout cocher
                                </button>
                                <button
                                    type="button"
                                    onClick={deselectAll}
                                    className="btn btn-ghost h-7 px-2 py-1 text-[0.6875rem] text-muted-foreground"
                                >
                                    Tout décocher
                                </button>
                            </div>
                        )}
                    </div>
                </div>

                {/* Body : Liste des Domaines & Permissions */}
                <div className="flex-1 space-y-4 overflow-y-auto px-6 py-6">
                    {filteredGroups.length === 0 ? (
                        <div className="py-16 text-center text-xs text-muted-foreground">
                            Aucune permission ne correspond à « {searchQuery} ».
                        </div>
                    ) : (
                        filteredGroups.map((group) => {
                            const groupCodes = group.items.map((p) => p.code);
                            const selectedInGroup = groupCodes.filter((code) =>
                                selectedCodes.has(code),
                            ).length;
                            const allGroupSelected =
                                groupCodes.length > 0 &&
                                selectedInGroup === groupCodes.length;
                            const isCollapsed = Boolean(
                                collapsedDomains[group.domain],
                            );

                            return (
                                <div
                                    key={group.domain}
                                    className="overflow-hidden rounded-2xl border border-border bg-card shadow-xs transition-all"
                                >
                                    {/* Domain Header */}
                                    <div className="flex items-center justify-between border-b border-border bg-muted/30 px-4 py-3">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                toggleDomainCollapse(
                                                    group.domain,
                                                )
                                            }
                                            className="flex items-center gap-2.5 text-left text-xs font-black text-foreground transition hover:text-gold"
                                        >
                                            {isCollapsed ? (
                                                <ChevronRight className="size-4 text-muted-foreground" />
                                            ) : (
                                                <ChevronDown className="size-4 text-muted-foreground" />
                                            )}
                                            <span>{group.label}</span>
                                            <span className="rounded-full bg-muted px-2 py-0.5 font-mono text-[0.625rem] font-bold text-muted-foreground">
                                                {selectedInGroup} /{' '}
                                                {group.items.length}
                                            </span>
                                        </button>

                                        {!isSystemRole && canManage && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    toggleDomain(group)
                                                }
                                                className="text-[0.6875rem] font-bold text-gold hover:underline"
                                            >
                                                {allGroupSelected
                                                    ? 'Tout décocher'
                                                    : 'Tout cocher'}
                                            </button>
                                        )}
                                    </div>

                                    {/* Domain Permissions Grid */}
                                    {!isCollapsed && (
                                        <div className="grid grid-cols-1 gap-2 p-3 sm:grid-cols-2">
                                            {group.items.map((perm) => {
                                                const checked =
                                                    selectedCodes.has(
                                                        perm.code,
                                                    );

                                                return (
                                                    <label
                                                        key={perm.code}
                                                        className={cn(
                                                            'flex cursor-pointer items-start gap-3 rounded-xl border p-2.5 transition select-none',
                                                            checked
                                                                ? 'border-gold/40 bg-gold/5 dark:bg-gold/10'
                                                                : 'border-transparent hover:bg-muted/40',
                                                            isSystemRole ||
                                                                !canManage
                                                                ? 'cursor-default opacity-85'
                                                                : '',
                                                        )}
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            checked={checked}
                                                            disabled={
                                                                isSystemRole ||
                                                                !canManage
                                                            }
                                                            onChange={() =>
                                                                togglePermission(
                                                                    perm.code,
                                                                )
                                                            }
                                                            className="sr-only"
                                                        />
                                                        <span
                                                            className={cn(
                                                                'mt-0.5 grid size-4 shrink-0 place-items-center rounded-md border text-xs transition',
                                                                checked
                                                                    ? 'border-gold bg-gold text-[var(--kora)]'
                                                                    : 'border-border bg-card',
                                                            )}
                                                        >
                                                            {checked && (
                                                                <Check className="size-3 stroke-[3]" />
                                                            )}
                                                        </span>
                                                        <div className="flex-1">
                                                            <span className="block text-xs leading-tight font-bold text-foreground">
                                                                {perm.label}
                                                            </span>
                                                            <span className="mt-0.5 block font-mono text-[0.625rem] text-muted-foreground">
                                                                {perm.code}
                                                            </span>
                                                        </div>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>
                            );
                        })
                    )}
                </div>

                {/* Footer Bar */}
                <footer className="flex items-center justify-between border-t border-border bg-muted/10 px-6 py-4">
                    <button
                        type="button"
                        onClick={() => onOpenChange(false)}
                        className="btn btn-ghost text-xs"
                    >
                        Fermer
                    </button>

                    {!isSystemRole && canManage && (
                        <button
                            type="button"
                            onClick={handleSave}
                            disabled={saving}
                            className="btn btn-primary gap-2 text-xs font-black shadow-md hover:shadow-lg"
                        >
                            <Check className="size-3.5" />
                            {saving
                                ? 'Enregistrement…'
                                : 'Enregistrer les permissions'}
                        </button>
                    )}
                </footer>
            </aside>
        </div>
    );
}
