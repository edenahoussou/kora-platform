import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ClipboardList, Megaphone, Pencil } from 'lucide-react';
import { useRef, useState } from 'react';
import { FieldOperationReadOnlyTable } from '@/components/kora-points/admin/field-operations-panels';
import type {
    Paginated as FieldPaginated,
    PlvOrder,
    PointVisit,
} from '@/components/kora-points/admin/field-operations-panels';
import {
    DateLabel,
    EmptyState,
    MetricCard,
    Money,
    PartnerCard,
    PartnerPage,
    tableCellClass,
    tableHeadClass,
} from '@/components/kora-points/partner/partner-ui';
import { PointFormDialog } from '@/components/kora-points/partner/point-form-dialog';
import type {
    Paginated,
    PartnerDocument,
    PartnerOrganization,
    PartnerPoint,
} from '@/types/kora-point-partner';

type PointSale = {
    uuid: string;
    status: string;
    gross_amount: number;
    commission_amount: number;
    outstanding_amount: number;
    paid_at: string;
    due_at: string;
};

type LedgerEntry = {
    uuid: string;
    type: string;
    amount: number;
    commission_delta: number;
    recorded_at: string;
};
type Props = {
    organization: PartnerOrganization;
    point: PartnerPoint;
    sales: Paginated<PointSale>;
    entries: Paginated<LedgerEntry>;
    refunds: Paginated<{ uuid: string; status: string; amount: number }>;
    documents: PartnerDocument[];
    visits: FieldPaginated<PointVisit>;
    plv_orders: FieldPaginated<PlvOrder>;
    can_manage_points: boolean;
};

export default function PartnerPointPage({
    organization,
    point,
    sales,
    entries,
    documents,
    visits,
    plv_orders,
    can_manage_points,
}: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const [activeTab, setActiveTab] = useState<'summary' | 'visits' | 'plv'>(
        'summary',
    );
    const tabRefs = useRef<Array<HTMLButtonElement | null>>([]);
    const pointTabs = [
        { key: 'summary', label: 'Synthèse' },
        { key: 'visits', label: 'Visites', icon: ClipboardList },
        { key: 'plv', label: 'PLV', icon: Megaphone },
    ] as const;

    return (
        <>
            <Head title={`${point.name} — ${organization.name}`} />
            <PartnerPage
                title={point.name}
                description={`${point.code} · ${point.city}${point.department ? `, ${point.department}` : ''} · ${point.raw_status === 'active' ? 'Actif' : point.raw_status === 'suspended' ? 'Suspendu' : 'Clôturé'}`}
                organizationName={organization.name}
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href="/kora-partner/points"
                        className="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" /> Retour aux Points
                    </Link>
                    {can_manage_points ? (
                        <button
                            type="button"
                            onClick={() => setEditOpen(true)}
                            className="inline-flex items-center gap-2 rounded-md bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground hover:opacity-90"
                        >
                            <Pencil className="size-4" /> Modifier les
                            informations
                        </button>
                    ) : null}
                </div>

                <nav
                    role="tablist"
                    aria-label={`Espaces de la fiche de ${point.name}`}
                    className="flex overflow-x-auto border-b border-border"
                >
                    {pointTabs.map((tab, index) => {
                        const TabIcon = 'icon' in tab ? tab.icon : null;

                        return (
                            <button
                                key={tab.key}
                                ref={(element) => {
                                    tabRefs.current[index] = element;
                                }}
                                type="button"
                                role="tab"
                                id={`partner-point-tab-${tab.key}`}
                                aria-selected={activeTab === tab.key}
                                aria-controls={`partner-point-panel-${tab.key}`}
                                tabIndex={activeTab === tab.key ? 0 : -1}
                                onClick={() => setActiveTab(tab.key)}
                                onKeyDown={(event) => {
                                    if (
                                        event.key === 'ArrowRight' ||
                                        event.key === 'ArrowLeft' ||
                                        event.key === 'Home' ||
                                        event.key === 'End'
                                    ) {
                                        event.preventDefault();
                                        const next =
                                            event.key === 'Home'
                                                ? 0
                                                : event.key === 'End'
                                                  ? pointTabs.length - 1
                                                  : (index +
                                                        (event.key ===
                                                        'ArrowRight'
                                                            ? 1
                                                            : -1) +
                                                        pointTabs.length) %
                                                    pointTabs.length;
                                        setActiveTab(pointTabs[next].key);
                                        tabRefs.current[next]?.focus();
                                    }
                                }}
                                className={`shrink-0 border-b-2 px-4 py-3 text-sm font-semibold ${activeTab === tab.key ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'} focus-visible:outline-2 focus-visible:outline-ring`}
                            >
                                <span className="inline-flex items-center gap-2">
                                    {TabIcon ? (
                                        <TabIcon className="size-4" />
                                    ) : null}
                                    {tab.label}
                                </span>
                            </button>
                        );
                    })}
                </nav>

                {activeTab === 'summary' ? (
                    <div
                        id="partner-point-panel-summary"
                        role="tabpanel"
                        aria-labelledby="partner-point-tab-summary"
                        tabIndex={0}
                        className="space-y-6 focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <MetricCard
                                label="Encours à reverser"
                                value={
                                    <Money amount={point.outstanding_amount} />
                                }
                            />
                            <MetricCard
                                label="Allocation du Point"
                                value={<Money amount={point.credit_limit} />}
                                hint={`${point.usage_percentage} % utilisé`}
                            />
                            <MetricCard
                                label="Ventes cumulées"
                                value={
                                    <Money amount={point.gross_sales_amount} />
                                }
                                hint={`${point.sales_count} vente(s)`}
                            />
                            <MetricCard
                                label="Commissions cumulées"
                                value={
                                    <Money
                                        amount={point.earned_commission_amount}
                                    />
                                }
                                hint={`Taux organisation : ${(point.commission_rate_bps / 100).toFixed(2)} %`}
                            />
                        </div>

                        <PartnerCard title="Informations opérationnelles">
                            <dl className="grid gap-x-8 gap-y-5 p-5 sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Adresse
                                    </dt>
                                    <dd className="mt-1 text-sm font-medium">
                                        {point.address || 'Non renseignée'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Téléphone du Point
                                    </dt>
                                    <dd className="mt-1 text-sm font-medium">
                                        {point.phone || 'Non renseigné'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Responsable agent
                                    </dt>
                                    <dd className="mt-1 text-sm font-medium">
                                        {point.responsible_name ||
                                            'Non renseigné'}
                                        <span className="mt-1 block text-xs font-normal text-muted-foreground">
                                            Gestion du compte par KORA OPS
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Commission
                                    </dt>
                                    <dd className="mt-1 text-sm font-medium">
                                        {(
                                            point.commission_rate_bps / 100
                                        ).toFixed(2)}{' '}
                                        %
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Fréquence de reversement
                                    </dt>
                                    <dd className="mt-1 text-sm font-medium">
                                        {point.terms.settlement_calendar
                                            ?.frequency === 'daily'
                                            ? 'Quotidien'
                                            : point.terms.settlement_calendar
                                                    ?.frequency === 'monthly'
                                              ? 'Mensuel'
                                              : 'Hebdomadaire'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Ventes en retard
                                    </dt>
                                    <dd className="mt-1 text-sm font-medium">
                                        {point.overdue ? 'Oui' : 'Non'}
                                    </dd>
                                </div>
                            </dl>
                        </PartnerCard>

                        <PartnerCard
                            title="Ventes récentes"
                            action={
                                <Link
                                    href="/kora-partner/sales"
                                    className="text-sm font-semibold text-primary hover:underline"
                                >
                                    Toutes les ventes
                                </Link>
                            }
                        >
                            {sales.data.length === 0 ? (
                                <EmptyState>
                                    Aucune vente n’a été réalisée sur ce Point.
                                </EmptyState>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[760px] border-collapse">
                                        <thead className="bg-muted/40">
                                            <tr>
                                                <th className={tableHeadClass}>
                                                    Date
                                                </th>
                                                <th className={tableHeadClass}>
                                                    Vente
                                                </th>
                                                <th className={tableHeadClass}>
                                                    Statut
                                                </th>
                                                <th className={tableHeadClass}>
                                                    Montant
                                                </th>
                                                <th className={tableHeadClass}>
                                                    Commission
                                                </th>
                                                <th className={tableHeadClass}>
                                                    Encours
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {sales.data.map((sale) => (
                                                <tr key={sale.uuid}>
                                                    <td
                                                        className={
                                                            tableCellClass
                                                        }
                                                    >
                                                        <DateLabel
                                                            value={sale.paid_at}
                                                        />
                                                    </td>
                                                    <td
                                                        className={`${tableCellClass} font-mono text-xs`}
                                                    >
                                                        {sale.uuid
                                                            .slice(0, 8)
                                                            .toUpperCase()}
                                                    </td>
                                                    <td
                                                        className={
                                                            tableCellClass
                                                        }
                                                    >
                                                        {sale.status}
                                                    </td>
                                                    <td
                                                        className={
                                                            tableCellClass
                                                        }
                                                    >
                                                        <Money
                                                            amount={
                                                                sale.gross_amount
                                                            }
                                                        />
                                                    </td>
                                                    <td
                                                        className={
                                                            tableCellClass
                                                        }
                                                    >
                                                        <Money
                                                            amount={
                                                                sale.commission_amount
                                                            }
                                                        />
                                                    </td>
                                                    <td
                                                        className={
                                                            tableCellClass
                                                        }
                                                    >
                                                        <Money
                                                            amount={
                                                                sale.outstanding_amount
                                                            }
                                                        />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </PartnerCard>

                        <PartnerCard title="Mouvements d’encours">
                            {entries.data.length === 0 ? (
                                <EmptyState>
                                    Aucun mouvement d’encours.
                                </EmptyState>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[600px] border-collapse">
                                        <thead className="bg-muted/40">
                                            <tr>
                                                <th className={tableHeadClass}>
                                                    Date
                                                </th>
                                                <th className={tableHeadClass}>
                                                    Type
                                                </th>
                                                <th className={tableHeadClass}>
                                                    Montant
                                                </th>
                                                <th className={tableHeadClass}>
                                                    Correction de commission
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {entries.data.map((entry) => (
                                                <tr key={entry.uuid}>
                                                    <td
                                                        className={
                                                            tableCellClass
                                                        }
                                                    >
                                                        <DateLabel
                                                            value={
                                                                entry.recorded_at
                                                            }
                                                        />
                                                    </td>
                                                    <td
                                                        className={
                                                            tableCellClass
                                                        }
                                                    >
                                                        {entry.type}
                                                    </td>
                                                    <td
                                                        className={
                                                            tableCellClass
                                                        }
                                                    >
                                                        <Money
                                                            amount={
                                                                entry.amount
                                                            }
                                                        />
                                                    </td>
                                                    <td
                                                        className={
                                                            tableCellClass
                                                        }
                                                    >
                                                        <Money
                                                            amount={
                                                                entry.commission_delta
                                                            }
                                                        />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </PartnerCard>

                        <PartnerCard title="Documents de l’organisation">
                            {documents.length === 0 ? (
                                <EmptyState>
                                    Aucun document partagé par l’organisation.
                                </EmptyState>
                            ) : (
                                <ul className="divide-y divide-border">
                                    {documents.map((document) => (
                                        <li
                                            key={document.uuid}
                                            className="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                                        >
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {document.title}
                                                </p>
                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                    {document.kind}
                                                </p>
                                            </div>
                                            <a
                                                href={document.download_url}
                                                className="text-sm font-semibold text-primary hover:underline"
                                            >
                                                Télécharger
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </PartnerCard>
                    </div>
                ) : null}

                {activeTab === 'visits' ? (
                    <section
                        id="partner-point-panel-visits"
                        role="tabpanel"
                        aria-labelledby="partner-point-tab-visits"
                        tabIndex={0}
                        className="space-y-3 focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <div>
                            <h2 className="text-sm font-bold">
                                Visites de ce Point
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Consultation des visites, comptes rendus, photos
                                et actions de suivi.
                            </p>
                        </div>
                        <FieldOperationReadOnlyTable
                            kind="visits"
                            visits={visits}
                            page={visits}
                        />
                    </section>
                ) : null}

                {activeTab === 'plv' ? (
                    <section
                        id="partner-point-panel-plv"
                        role="tabpanel"
                        aria-labelledby="partner-point-tab-plv"
                        tabIndex={0}
                        className="space-y-3 focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <div>
                            <h2 className="text-sm font-bold">
                                Commandes PLV de ce Point
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                État de préparation, livraison et installation
                                des supports.
                            </p>
                        </div>
                        <FieldOperationReadOnlyTable
                            kind="plv"
                            plvOrders={plv_orders}
                            page={plv_orders}
                        />
                    </section>
                ) : null}
            </PartnerPage>
            {editOpen ? (
                <PointFormDialog
                    key={point.uuid}
                    open
                    point={point}
                    organization={organization}
                    onClose={() => setEditOpen(false)}
                />
            ) : null}
        </>
    );
}
