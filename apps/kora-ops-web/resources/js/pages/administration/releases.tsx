import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Archive,
    ArchiveRestore,
    CheckCircle2,
    Copy,
    HelpCircle,
    Laptop,
    Package,
    PencilLine,
    ShieldAlert,
    Smartphone,
    Upload,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ReleaseEditModal } from '@/components/app-releases/release-edit-modal';
import { ReleaseSharePanel } from '@/components/app-releases/release-share-panel';
import { ReleaseUploadModal } from '@/components/app-releases/release-upload-modal';
import {
    copyToClipboard,
    formatBytes,
    formatReleaseDate,
    versionStatusTones,
} from '@/components/app-releases/shared';
import type {
    AppRelease,
    AppVersionStatus,
    ReleasedAppTab,
    VersionDistribution,
} from '@/components/app-releases/shared';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import administration from '@/routes/administration';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    apps: ReleasedAppTab[];
    selectedApp: string;
    app: { value: string; label: string; slug: string; audience: string };
    links: { download_page: string; latest_apk: string };
    latest: AppRelease | null;
    minimum: { uuid: string; version: string; build_number: number } | null;
    releases: AppRelease[];
    distribution: VersionDistribution;
    constraints: { max_package_kilobytes: number; max_build_number: number };
    permissions: { canManage: boolean };
};

type PendingAction = { type: 'archive' | 'restore'; release: AppRelease };

const kpiTones = {
    navy: 'bg-kora text-white',
    green: 'bg-emerald-500/10 text-emerald-600',
    gold: 'bg-gold/15 text-gold',
    rose: 'bg-destructive/10 text-destructive',
    muted: 'bg-muted text-muted-foreground',
};

function KpiCard({
    icon: Icon,
    label,
    hint,
    value,
    tone,
}: {
    icon: React.ComponentType<{ className?: string; strokeWidth?: number }>;
    label: string;
    hint: string;
    value: number | string;
    tone: keyof typeof kpiTones;
}) {
    return (
        <article className="cash-ledger-card cash-ledger-card-cash">
            <div className="cash-ledger-head">
                <div className="cash-ledger-identity">
                    <span className={cn('cash-ledger-brand', kpiTones[tone])}>
                        <Icon className="size-4" strokeWidth={1.7} />
                    </span>
                    <div>
                        <span className="cash-ledger-title">{label}</span>
                        <small>{hint}</small>
                    </div>
                </div>
            </div>
            <div className="cash-ledger-balance">
                <span>Terminaux</span>
                <strong>{value}</strong>
            </div>
        </article>
    );
}

const barTones: Record<AppVersionStatus, string> = {
    up_to_date: 'bg-kora dark:bg-digital',
    update_available: 'bg-gold',
    update_required: 'bg-destructive',
    unknown: 'bg-muted-foreground/40',
    not_tracked: 'bg-muted-foreground/40',
};

export default function AppReleasesPage({
    apps,
    selectedApp,
    app,
    links,
    latest,
    minimum,
    releases,
    distribution,
    constraints,
    permissions,
}: PageProps) {
    const [uploadOpen, setUploadOpen] = useState(false);
    const [editing, setEditing] = useState<AppRelease | null>(null);
    const [pending, setPending] = useState<PendingAction | null>(null);
    const [processing, setProcessing] = useState(false);

    const copyReleaseLink = async (release: AppRelease) => {
        if (await copyToClipboard(release.download_url)) {
            toast.success(
                `Lien de la version ${release.full_version} copié dans le presse-papiers.`,
            );
        } else {
            toast.error(
                'Copie impossible : sélectionnez le lien manuellement.',
            );
        }
    };

    const confirmPending = () => {
        if (!pending) {
            return;
        }

        const url =
            pending.type === 'archive'
                ? administration.releases.archive.url(pending.release.uuid)
                : administration.releases.restore.url(pending.release.uuid);

        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setPending(null);
                },
                onError: (errors) =>
                    toast.error(
                        errors.release ?? 'L’opération n’a pas pu aboutir.',
                    ),
            },
        );
    };

    const maxVersionCount = Math.max(
        1,
        ...distribution.versions.map((row) => row.terminals),
    );

    return (
        <>
            <Head title="Versions de l'application" />

            <div className="cash-page">
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Administration / Versions de
                            l’application
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Versions de l’application
                        </h1>
                        <p className="mt-1.5 max-w-2xl text-[0.8125rem] leading-6 text-muted-foreground">
                            Publiez les packages hors store, pilotez les mises à
                            jour obligatoires et suivez la version installée sur
                            chaque terminal.
                        </p>
                    </div>

                    {permissions.canManage && (
                        <div className="flex flex-wrap items-center gap-2.5">
                            <button
                                type="button"
                                className="btn btn-primary"
                                onClick={() => setUploadOpen(true)}
                            >
                                <Upload className="size-4" /> Publier une
                                version
                            </button>
                        </div>
                    )}
                </div>

                {apps.length > 0 && (
                    <nav
                        className="cash-reveal mb-5 flex flex-wrap gap-2"
                        aria-label="Applications"
                    >
                        {apps.map((tab) => {
                            const active = tab.value === selectedApp;

                            return (
                                <button
                                    key={tab.value}
                                    type="button"
                                    aria-current={active ? 'page' : undefined}
                                    onClick={() =>
                                        !active &&
                                        router.get(
                                            administration.releases.url({
                                                query: { app: tab.value },
                                            }),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                    className={cn(
                                        'inline-flex items-center gap-2 rounded-full px-4 py-2 text-xs font-extrabold transition-colors',
                                        active
                                            ? 'bg-kora text-white shadow-sm dark:bg-digital/25 dark:text-digital'
                                            : 'bg-muted/60 text-muted-foreground hover:bg-gold/15 hover:text-foreground',
                                    )}
                                >
                                    <Smartphone className="size-3.5" />
                                    {tab.label}
                                    <span
                                        className={cn(
                                            'rounded-full px-1.5 py-0.5 text-[0.625rem]',
                                            active
                                                ? 'bg-white/15'
                                                : 'bg-background',
                                        )}
                                    >
                                        {tab.releases_count}
                                    </span>
                                </button>
                            );
                        })}
                    </nav>
                )}

                <section className="cash-ledger-grid cash-reveal cash-reveal-delay-1 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiCard
                        icon={Laptop}
                        label="Parc suivi"
                        hint="terminaux non perdus"
                        value={distribution.total}
                        tone="navy"
                    />
                    <KpiCard
                        icon={CheckCircle2}
                        label="À jour"
                        hint="dernière version installée"
                        value={distribution.by_status.up_to_date ?? 0}
                        tone="green"
                    />
                    <KpiCard
                        icon={AlertTriangle}
                        label="MAJ disponible"
                        hint="mise à jour proposée"
                        value={distribution.by_status.update_available ?? 0}
                        tone="gold"
                    />
                    <KpiCard
                        icon={ShieldAlert}
                        label="MAJ requise"
                        hint="bloqués jusqu’à la mise à jour"
                        value={distribution.by_status.update_required ?? 0}
                        tone={
                            (distribution.by_status.update_required ?? 0) > 0
                                ? 'rose'
                                : 'muted'
                        }
                    />
                </section>

                <div className="cash-reveal cash-reveal-delay-2 mb-6 grid gap-5 xl:grid-cols-[1.35fr_1fr]">
                    <ReleaseSharePanel
                        appLabel={app.label}
                        appSlug={app.slug}
                        downloadPageUrl={links.download_page}
                        latestApkUrl={links.latest_apk}
                        hasPublishedRelease={latest !== null}
                    />

                    <section className="cash-table-card p-5 md:p-6">
                        <span className="section-kicker flex items-center gap-1.5">
                            <Package className="size-3.5" /> Politique en
                            vigueur
                        </span>
                        {latest ? (
                            <div className="mt-3 grid gap-4">
                                <div>
                                    <span className="text-[0.6875rem] font-bold text-muted-foreground uppercase">
                                        Dernière version publiée
                                    </span>
                                    <p className="mt-0.5 font-mono text-2xl font-black tracking-tight text-foreground">
                                        {latest.version}
                                        <span className="ml-2 text-sm font-semibold text-muted-foreground">
                                            build {latest.build_number}
                                        </span>
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {formatReleaseDate(latest.published_at)}{' '}
                                        · {formatBytes(latest.size_bytes)}
                                    </p>
                                </div>
                                <div className="rounded-xl bg-muted/45 p-3.5 ring-1 ring-black/[0.045]">
                                    <span className="flex items-center gap-1.5 text-xs font-extrabold text-foreground">
                                        <ShieldAlert className="size-3.5 text-destructive" />
                                        Version minimale requise
                                    </span>
                                    <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                        {minimum ? (
                                            <>
                                                Toute installation antérieure à{' '}
                                                <strong className="font-mono text-foreground">
                                                    {minimum.version} (build{' '}
                                                    {minimum.build_number})
                                                </strong>{' '}
                                                est bloquée jusqu’à la mise à
                                                jour.
                                            </>
                                        ) : (
                                            'Aucune version obligatoire : les mises à jour sont proposées, jamais imposées.'
                                        )}
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <div className="mt-3 rounded-xl bg-muted/45 p-4 text-xs leading-5 text-muted-foreground">
                                Aucune version publiée pour {app.label}.{' '}
                                {permissions.canManage
                                    ? 'Publiez le premier APK pour activer la distribution et le contrôle de version.'
                                    : 'Un administrateur doit publier le premier APK.'}
                            </div>
                        )}
                    </section>
                </div>

                <section className="cash-table-card cash-reveal cash-reveal-delay-2 mt-6 p-5 md:p-6">
                    <div className="mb-4 flex flex-wrap items-end justify-between gap-2">
                        <div>
                            <span className="section-kicker">Vue flotte</span>
                            <h2 className="text-base font-extrabold text-foreground">
                                Distribution des versions installées
                            </h2>
                        </div>
                        {(distribution.by_status.unknown ?? 0) > 0 && (
                            <span className="flex items-center gap-1.5 text-[0.6875rem] text-muted-foreground">
                                <HelpCircle className="size-3.5" />
                                {distribution.by_status.unknown} terminal(aux)
                                n’ont jamais remonté leur version
                            </span>
                        )}
                    </div>

                    {distribution.versions.length === 0 ? (
                        <p className="py-6 text-center text-xs text-muted-foreground">
                            Aucun terminal enregistré pour cette application.
                        </p>
                    ) : (
                        <ul className="grid gap-2.5">
                            {distribution.versions.map((row) => (
                                <li
                                    key={row.version ?? 'unknown'}
                                    className="grid grid-cols-[minmax(110px,160px)_1fr_auto] items-center gap-3"
                                >
                                    <span className="truncate font-mono text-xs font-bold text-foreground">
                                        {row.version ?? 'Inconnue'}
                                    </span>
                                    <div className="h-2.5 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className={cn(
                                                'h-full rounded-full',
                                                barTones[row.status],
                                            )}
                                            style={{
                                                width: `${Math.max(4, (row.terminals / maxVersionCount) * 100)}%`,
                                            }}
                                        />
                                    </div>
                                    <span className="flex items-center gap-2">
                                        <strong className="w-8 text-right text-xs text-foreground">
                                            {row.terminals}
                                        </strong>
                                        <KoraStatusBadge
                                            label={row.status_label}
                                            tone={
                                                versionStatusTones[row.status]
                                            }
                                        />
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="cash-table-card cash-reveal cash-reveal-delay-3 mt-6">
                    {releases.length === 0 ? (
                        <div className="px-6 py-16 text-center text-sm text-muted-foreground">
                            <Package className="mx-auto mb-2 size-8 text-muted-foreground/40" />
                            <p className="font-semibold text-foreground">
                                Aucune version publiée
                            </p>
                            <p className="mt-1 text-xs">
                                Les versions publiées et archivées apparaîtront
                                ici.
                            </p>
                        </div>
                    ) : (
                        <div className="cash-table-scroll overflow-x-auto">
                            <table className="w-full min-w-[1120px] text-left">
                                <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-4 py-3">Version</th>
                                        <th className="px-4 py-3">Build</th>
                                        <th className="px-4 py-3">
                                            Obligatoire
                                        </th>
                                        <th className="px-4 py-3">
                                            Publiée le
                                        </th>
                                        <th className="px-4 py-3">État</th>
                                        <th className="px-4 py-3">Auteur</th>
                                        <th className="px-4 py-3 text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {releases.map((release) => (
                                        <tr
                                            key={release.uuid}
                                            className={cn(
                                                'border-b border-black/[0.045] align-top transition-colors hover:bg-muted/30',
                                                release.state === 'archived' &&
                                                    'opacity-60',
                                            )}
                                        >
                                            <td className="px-4 py-3.5">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <span className="font-mono text-sm font-black text-foreground">
                                                        {release.version}
                                                    </span>
                                                    {release.is_latest && (
                                                        <KoraStatusBadge
                                                            label="Dernière"
                                                            tone="navy"
                                                        />
                                                    )}
                                                    {release.is_minimum && (
                                                        <KoraStatusBadge
                                                            label="Minimum requis"
                                                            tone="destructive"
                                                        />
                                                    )}
                                                </div>
                                                {release.release_notes && (
                                                    <p
                                                        className="mt-1 line-clamp-2 max-w-md text-[0.6875rem] leading-4 text-muted-foreground"
                                                        title={
                                                            release.release_notes
                                                        }
                                                    >
                                                        {release.release_notes}
                                                    </p>
                                                )}
                                                <span
                                                    className="mt-1 block font-mono text-[0.625rem] text-muted-foreground/80"
                                                    title={`SHA-256 ${release.sha256}`}
                                                >
                                                    {formatBytes(
                                                        release.size_bytes,
                                                    )}{' '}
                                                    · sha256{' '}
                                                    {release.sha256.slice(
                                                        0,
                                                        12,
                                                    )}
                                                    …
                                                </span>
                                            </td>
                                            <td className="px-4 py-3.5 font-mono text-xs text-foreground">
                                                {release.build_number}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                {release.is_mandatory ? (
                                                    <KoraStatusBadge
                                                        label="Obligatoire"
                                                        tone="destructive"
                                                    />
                                                ) : (
                                                    <KoraStatusBadge
                                                        label="Optionnelle"
                                                        tone="muted"
                                                    />
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5 text-xs text-foreground">
                                                {formatReleaseDate(
                                                    release.published_at,
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                {release.state ===
                                                'archived' ? (
                                                    <div className="text-xs">
                                                        <KoraStatusBadge
                                                            label="Archivée"
                                                            tone="muted"
                                                        />
                                                        <span className="mt-1 block text-[0.625rem] text-muted-foreground">
                                                            {formatReleaseDate(
                                                                release.archived_at,
                                                            )}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <KoraStatusBadge
                                                        label="Publiée"
                                                        tone="blue"
                                                    />
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5 text-xs text-foreground">
                                                {release.author?.name ?? '—'}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="flex flex-nowrap justify-end gap-1.5 whitespace-nowrap">
                                                    {release.state ===
                                                        'published' && (
                                                        <button
                                                            type="button"
                                                            className="btn btn-ghost gap-1.5 px-2.5 py-1 text-xs font-bold text-digital"
                                                            onClick={() =>
                                                                copyReleaseLink(
                                                                    release,
                                                                )
                                                            }
                                                            title="Copier le lien de téléchargement de cette version"
                                                        >
                                                            <Copy className="size-3.5" />
                                                            Copier le lien
                                                        </button>
                                                    )}
                                                    {permissions.canManage && (
                                                        <>
                                                            <button
                                                                type="button"
                                                                className="btn btn-ghost gap-1.5 px-2.5 py-1 text-xs font-bold"
                                                                onClick={() =>
                                                                    setEditing(
                                                                        release,
                                                                    )
                                                                }
                                                            >
                                                                <PencilLine className="size-3.5" />
                                                                Modifier
                                                            </button>
                                                            {release.state ===
                                                            'published' ? (
                                                                <button
                                                                    type="button"
                                                                    className="btn btn-ghost gap-1.5 px-2.5 py-1 text-xs font-bold text-destructive"
                                                                    onClick={() =>
                                                                        setPending(
                                                                            {
                                                                                type: 'archive',
                                                                                release,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    <Archive className="size-3.5" />
                                                                    Archiver
                                                                </button>
                                                            ) : (
                                                                <button
                                                                    type="button"
                                                                    className="btn btn-ghost gap-1.5 px-2.5 py-1 text-xs font-bold"
                                                                    onClick={() =>
                                                                        setPending(
                                                                            {
                                                                                type: 'restore',
                                                                                release,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    <ArchiveRestore className="size-3.5" />
                                                                    Republier
                                                                </button>
                                                            )}
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>

            {uploadOpen && (
                <ReleaseUploadModal
                    app={app}
                    latest={releases[0] ?? null}
                    maxPackageKilobytes={constraints.max_package_kilobytes}
                    maxBuildNumber={constraints.max_build_number}
                    onClose={() => setUploadOpen(false)}
                />
            )}

            {editing && (
                <ReleaseEditModal
                    release={editing}
                    onClose={() => setEditing(null)}
                />
            )}

            {pending && (
                <div
                    className="cash-modal-backdrop"
                    role="presentation"
                    onMouseDown={(event) =>
                        event.target === event.currentTarget &&
                        !processing &&
                        setPending(null)
                    }
                >
                    <div
                        className="cash-modal"
                        role="alertdialog"
                        aria-modal="true"
                        aria-labelledby="release-confirm-title"
                    >
                        <div className="cash-modal-head">
                            <div className="flex items-start gap-3">
                                <span className="cash-opening-icon">
                                    {pending.type === 'archive' ? (
                                        <Archive className="size-5" />
                                    ) : (
                                        <ArchiveRestore className="size-5" />
                                    )}
                                </span>
                                <div>
                                    <span className="section-kicker">
                                        Version {pending.release.full_version}
                                    </span>
                                    <h3 id="release-confirm-title">
                                        {pending.type === 'archive'
                                            ? 'Archiver cette version ?'
                                            : 'Republier cette version ?'}
                                    </h3>
                                    <p>
                                        {pending.type === 'archive'
                                            ? 'Elle ne sera plus proposée au téléchargement ni prise en compte dans le calcul de la dernière version. Les terminaux qui l’ont déjà installée ne seront pas rétrogradés.'
                                            : 'Elle redeviendra téléchargeable et pourra de nouveau être la dernière version proposée aux terminaux.'}
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setPending(null)}
                                className="cash-modal-close"
                                aria-label="Fermer"
                                disabled={processing}
                            >
                                <X className="size-4" />
                            </button>
                        </div>
                        <div className="cash-modal-actions">
                            <button
                                type="button"
                                onClick={() => setPending(null)}
                                className="btn btn-ghost"
                                disabled={processing}
                            >
                                Annuler
                            </button>
                            <button
                                type="button"
                                onClick={confirmPending}
                                className={cn(
                                    'btn gap-2 font-black',
                                    pending.type === 'archive'
                                        ? 'btn-danger'
                                        : 'btn-primary',
                                )}
                                disabled={processing}
                            >
                                {pending.type === 'archive' ? (
                                    <Archive className="size-4" />
                                ) : (
                                    <ArchiveRestore className="size-4" />
                                )}
                                {pending.type === 'archive'
                                    ? 'Archiver'
                                    : 'Republier'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

AppReleasesPage.layout = {
    breadcrumbs: [
        { title: 'Administration', href: '/users' } satisfies BreadcrumbItem,
        {
            title: 'Versions de l’application',
            href: '/administration/releases',
        } satisfies BreadcrumbItem,
    ],
};
