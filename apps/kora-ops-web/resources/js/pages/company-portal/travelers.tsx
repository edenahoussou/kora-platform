import { Head, router, usePage } from '@inertiajs/react';
import {
    ChevronRight,
    Edit3,
    Plus,
    Power,
    RotateCcw,
    Search,
    SlidersHorizontal,
    Syringe,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { CompanyTravelerFormModal } from '@/components/company-portal/company-traveler-form-modal';
import type { CompanyTravelerRecord } from '@/components/company-portal/company-traveler-types';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import companyPortal from '@/routes/company-portal';
import type { BreadcrumbItem } from '@/types';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type Props = {
    account: {
        uuid: string;
        name: string;
        reference: string;
    };
    travelers: Paginated<CompanyTravelerRecord>;
    filters: { q: string; status: string };
    documentTypes: Record<string, string>;
};

const statusLabels: Record<string, string> = {
    active: 'Actif',
    inactive: 'Retiré',
};

const statusTones: Record<string, 'navy' | 'muted'> = {
    active: 'navy',
    inactive: 'muted',
};

const statusOptions: [string, string][] = [
    ['all', 'Tous les statuts'],
    ['active', 'Actifs'],
    ['inactive', 'Retirés'],
];

function FilterSelect({
    label,
    value,
    onChange,
    options,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: [string, string][];
}) {
    return (
        <label className="cash-filter-field relative block">
            <span className="sr-only">{label}</span>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="cash-filter-select h-11 w-full appearance-none rounded-xl bg-muted/55 px-3 pr-8 text-xs font-bold text-foreground ring-1 ring-black/[0.045] transition-[box-shadow,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
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
}

function Pagination({
    currentPage,
    totalPages,
    total,
    from,
    to,
    onPageChange,
}: {
    currentPage: number;
    totalPages: number;
    total: number;
    from: number | null;
    to: number | null;
    onPageChange: (page: number) => void;
}) {
    if (total === 0) {
        return null;
    }

    return (
        <div className="flex items-center justify-between border-t border-black/[0.045] px-4 py-3 text-xs text-muted-foreground">
            <span>
                {from}–{to} sur {total}
            </span>
            <div className="flex items-center gap-1.5">
                <button
                    type="button"
                    className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-[transform,opacity,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:-translate-x-0.5 hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                    onClick={() => onPageChange(currentPage - 1)}
                    disabled={currentPage <= 1}
                    aria-label="Page précédente"
                >
                    <ChevronRight
                        className="size-3.5 rotate-180"
                        strokeWidth={1.7}
                    />
                </button>
                <span className="px-2 font-bold text-foreground">
                    {currentPage} / {totalPages}
                </span>
                <button
                    type="button"
                    className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-[transform,opacity,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:translate-x-0.5 hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                    onClick={() => onPageChange(currentPage + 1)}
                    disabled={currentPage >= totalPages}
                    aria-label="Page suivante"
                >
                    <ChevronRight className="size-3.5" strokeWidth={1.7} />
                </button>
            </div>
        </div>
    );
}

export default function CompanyPortalTravelers({
    account,
    travelers,
    filters,
    documentTypes,
}: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editingTraveler, setEditingTraveler] =
        useState<CompanyTravelerRecord | null>(null);

    const { url: currentUrl } = usePage();
    const initialFilters = useMemo(() => {
        const params = currentUrl.includes('?')
            ? new URLSearchParams(currentUrl.slice(currentUrl.indexOf('?') + 1))
            : new URLSearchParams();

        return {
            q: params.get('q') ?? filters.q ?? '',
            status: params.get('status') ?? filters.status ?? 'all',
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const [query, setQuery] = useState(initialFilters.q);
    const [status, setStatus] = useState(initialFilters.status);

    const visit = (
        overrides: { q?: string; status?: string; page?: number } = {},
    ) => {
        const nextQuery = overrides.q ?? query;
        const nextStatus = overrides.status ?? status;
        const params: Record<string, string> = {};

        if (nextQuery.trim() !== '') {
            params.q = nextQuery.trim();
        }

        if (nextStatus !== 'all') {
            params.status = nextStatus;
        }

        if (overrides.page !== undefined && overrides.page > 1) {
            params.page = String(overrides.page);
        }

        router.get(companyPortal.travelers.index.url(), params, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const openCreate = () => {
        setEditingTraveler(null);
        setModalOpen(true);
    };

    const openEdit = (traveler: CompanyTravelerRecord) => {
        setEditingTraveler(traveler);
        setModalOpen(true);
    };

    const changeStatus = (
        traveler: CompanyTravelerRecord,
        nextStatus: 'active' | 'inactive',
    ) => {
        const name = `${traveler.firstName} ${traveler.lastName}`;
        const message =
            nextStatus === 'inactive'
                ? `Retirer ${name} du roster ? Il ne pourra plus être sélectionné pour un achat.`
                : `Réactiver ${name} dans le roster ?`;

        if (!window.confirm(message)) {
            return;
        }

        router.patch(
            companyPortal.travelers.status.url({ traveler: traveler.uuid }),
            { status: nextStatus },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Membres voyageurs" />
            <div className="px-4 py-6 sm:px-6 lg:px-8">
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Espace entreprise / Membres voyageurs
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Membres voyageurs
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            {account.name} — déclarez les personnes autorisées à
                            voyager pour le compte de votre organisation.
                        </p>
                    </div>
                    <button
                        type="button"
                        className="btn btn-primary"
                        onClick={openCreate}
                    >
                        <Plus className="size-4" /> Déclarer un voyageur
                    </button>
                </div>

                <div className="cash-filterbar cash-reveal cash-reveal-delay-1">
                    <Search className="cash-filter-icon size-4" />
                    <input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                visit({ page: 1 });
                            }
                        }}
                        aria-label="Rechercher un voyageur"
                        placeholder="Rechercher par nom, téléphone ou email…"
                        className="cash-filter-search"
                    />
                    <FilterSelect
                        label="Statut"
                        value={status}
                        onChange={(value) => {
                            setStatus(value);
                            visit({ status: value, page: 1 });
                        }}
                        options={statusOptions}
                    />
                    <button
                        type="button"
                        className="btn btn-navy cash-filter-submit"
                        onClick={() => visit({ page: 1 })}
                    >
                        <SlidersHorizontal className="size-4" /> Rechercher
                    </button>
                    <button
                        type="button"
                        className="btn btn-ghost cash-filter-reset"
                        onClick={() => {
                            setQuery('');
                            setStatus('all');
                            router.get(
                                companyPortal.travelers.index.url(),
                                {},
                                {
                                    preserveScroll: true,
                                    preserveState: true,
                                    replace: true,
                                },
                            );
                        }}
                    >
                        Réinitialiser
                    </button>
                </div>

                <section className="cash-table-card cash-reveal cash-reveal-delay-2">
                    {travelers.data.length === 0 ? (
                        <div className="px-6 py-16 text-center">
                            <span className="mx-auto grid size-12 place-items-center rounded-2xl bg-gold/10 text-gold">
                                <Users className="size-5" strokeWidth={1.6} />
                            </span>
                            <p className="mt-4 text-sm font-bold text-foreground">
                                Aucun voyageur déclaré
                            </p>
                            <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                Déclarez les personnes de votre organisation
                                autorisées à voyager. Elles pourront ensuite
                                être sélectionnées lors d’un achat.
                            </p>
                        </div>
                    ) : (
                        <div className="cash-table-scroll overflow-x-auto">
                            <table className="w-full min-w-[860px] text-left">
                                <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-4 py-3">Voyageur</th>
                                        <th className="px-4 py-3">Contact</th>
                                        <th className="px-4 py-3">Pièce</th>
                                        <th className="px-4 py-3">Statut</th>
                                        <th className="px-4 py-3 text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {travelers.data.map((traveler) => (
                                        <tr
                                            key={traveler.uuid}
                                            className="group border-b border-black/[0.045] transition-[background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3.5">
                                                <div className="flex items-center gap-3">
                                                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-gold/12 text-[0.6875rem] font-black text-[var(--kora)] uppercase">
                                                        {traveler.firstName.charAt(
                                                            0,
                                                        )}
                                                        {traveler.lastName.charAt(
                                                            0,
                                                        )}
                                                    </span>
                                                    <div>
                                                        <p className="text-xs font-black text-foreground">
                                                            {traveler.firstName}{' '}
                                                            {traveler.lastName}
                                                        </p>
                                                        <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                                            {traveler.nationality ??
                                                                'Nationalité non renseignée'}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <p className="text-xs font-bold text-foreground">
                                                    {traveler.phone}
                                                </p>
                                                <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                                    {traveler.email ??
                                                        'Email non renseigné'}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                {traveler.documentType ? (
                                                    <>
                                                        <p className="text-xs font-bold text-foreground">
                                                            {
                                                                traveler.documentTypeLabel
                                                            }
                                                        </p>
                                                        <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                                            {traveler.documentNumber ??
                                                                '—'}
                                                        </p>
                                                    </>
                                                ) : (
                                                    <span className="text-[0.625rem] text-muted-foreground italic">
                                                        Aucun document
                                                    </span>
                                                )}
                                                {traveler.yellowFeverVaccinated && (
                                                    <span className="mt-1 inline-flex items-center gap-1 rounded-full bg-gold/10 px-2 py-0.5 text-[0.5625rem] font-extrabold tracking-[0.06em] text-[var(--kora)] uppercase">
                                                        <Syringe className="size-2.5" />
                                                        Fièvre jaune
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <KoraStatusBadge
                                                    label={
                                                        statusLabels[
                                                            traveler.status
                                                        ] ?? traveler.status
                                                    }
                                                    tone={
                                                        statusTones[
                                                            traveler.status
                                                        ] ?? 'muted'
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="flex justify-end gap-1.5">
                                                    <button
                                                        type="button"
                                                        className="grid size-8 place-items-center rounded-full bg-muted text-foreground transition-[transform,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:scale-105 hover:bg-gold/15"
                                                        onClick={() =>
                                                            openEdit(traveler)
                                                        }
                                                        aria-label={`Modifier ${traveler.firstName} ${traveler.lastName}`}
                                                    >
                                                        <Edit3
                                                            className="size-3.5"
                                                            strokeWidth={1.7}
                                                        />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className={cn(
                                                            'grid size-8 place-items-center rounded-full transition-[transform,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:scale-105',
                                                            traveler.status ===
                                                                'active'
                                                                ? 'bg-destructive/10 text-destructive hover:bg-destructive/20'
                                                                : 'bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20',
                                                        )}
                                                        onClick={() =>
                                                            changeStatus(
                                                                traveler,
                                                                traveler.status ===
                                                                    'active'
                                                                    ? 'inactive'
                                                                    : 'active',
                                                            )
                                                        }
                                                        aria-label={
                                                            traveler.status ===
                                                            'active'
                                                                ? `Retirer ${traveler.firstName} ${traveler.lastName} du roster`
                                                                : `Réactiver ${traveler.firstName} ${traveler.lastName}`
                                                        }
                                                    >
                                                        {traveler.status ===
                                                        'active' ? (
                                                            <Power
                                                                className="size-3.5"
                                                                strokeWidth={
                                                                    1.7
                                                                }
                                                            />
                                                        ) : (
                                                            <RotateCcw
                                                                className="size-3.5"
                                                                strokeWidth={
                                                                    1.7
                                                                }
                                                            />
                                                        )}
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <Pagination
                        currentPage={travelers.current_page}
                        totalPages={travelers.last_page}
                        total={travelers.total}
                        from={travelers.from}
                        to={travelers.to}
                        onPageChange={(page) => visit({ page })}
                    />
                </section>
            </div>

            <CompanyTravelerFormModal
                open={modalOpen}
                traveler={editingTraveler}
                documentTypes={documentTypes}
                onOpenChange={setModalOpen}
            />
        </>
    );
}

CompanyPortalTravelers.layout = {
    breadcrumbs: [
        { title: 'Espace entreprise', href: '/entreprise' },
        {
            title: 'Membres voyageurs',
            href: '/entreprise/membres',
        } satisfies BreadcrumbItem,
    ],
};
