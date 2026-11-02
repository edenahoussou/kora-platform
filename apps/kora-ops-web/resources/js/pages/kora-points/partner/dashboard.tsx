import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, CircleAlert, Store, Ticket } from 'lucide-react';
import { KoraPointActivityChart } from '@/components/kora-points/agent-dashboard/kora-point-activity-chart';
import { KoraPointHeroBanner } from '@/components/kora-points/agent-dashboard/kora-point-hero-banner';
import { KoraPointKpiGrid } from '@/components/kora-points/agent-dashboard/kora-point-kpi-grid';
import { KoraPointQuickActions } from '@/components/kora-points/agent-dashboard/kora-point-quick-actions';
import { KoraTable } from '@/components/kora-table';
import type { TableColumn } from '@/components/kora-table';
import type {
    PartnerOrganization,
    PartnerPoint,
} from '@/types/kora-point-partner';
import type {
    DailyMetrics,
    FinancialHealth,
    KoraPointProfile,
    PointPriorityAlert,
    PointSaleRecord,
} from '@/types/kora-points';

type PartnerHealth = FinancialHealth & {
    global_credit_limit: number;
    organization_allocated_limit: number;
    unallocated_amount: number;
    network_point_count: number;
    blocked_point_count: number;
};

type PointOption = { uuid: string; name: string; code: string; status: string };
type RecentSale = PointSaleRecord & {
    point_uuid: string;
    point_name: string;
    point_code: string;
};

type Props = {
    organization: PartnerOrganization;
    profile: KoraPointProfile;
    health: PartnerHealth;
    metrics: DailyMetrics;
    priorities: PointPriorityAlert[];
    points: PartnerPoint[];
    point_filter_options: PointOption[];
    selected_point_uuid: string | null;
    recent_sales: RecentSale[];
    recent_sales_total: number;
    is_treasurer: boolean;
};

const money = (amount: number) =>
    `${new Intl.NumberFormat('fr-FR').format(amount)} XOF`;

export default function PartnerDashboardPage({
    organization,
    profile,
    health,
    metrics,
    priorities,
    points,
    point_filter_options,
    selected_point_uuid,
    recent_sales,
    recent_sales_total,
    is_treasurer,
}: Props) {
    const pointColumns: TableColumn<PartnerPoint>[] = [
        {
            key: 'point',
            header: 'Point de vente',
            render: (point) => (
                <Link
                    href={`/kora-partner/points/${point.uuid}`}
                    className="font-bold text-foreground hover:text-primary hover:underline"
                >
                    {point.name}
                    <span className="mt-0.5 block text-xs font-normal text-muted-foreground">
                        {point.code} · {point.city}
                    </span>
                </Link>
            ),
        },
        {
            key: 'status',
            header: 'État',
            render: (point) => {
                const label =
                    point.raw_status === 'closed'
                        ? 'Clôturé'
                        : point.raw_status === 'suspended'
                          ? 'Suspendu'
                          : point.overdue
                            ? 'En retard'
                            : point.usage_percentage >= 80
                              ? 'À surveiller'
                              : 'Actif';
                const tone =
                    point.raw_status === 'closed' ||
                    point.raw_status === 'suspended' ||
                    point.overdue
                        ? 'text-rose-600'
                        : point.usage_percentage >= 80
                          ? 'text-amber-600'
                          : 'text-emerald-600';

                return <span className={`font-semibold ${tone}`}>{label}</span>;
            },
        },
        {
            key: 'sales',
            header: 'Ventes du mois',
            align: 'right',
            render: (point) => money(point.monthly_sales_amount),
        },
        {
            key: 'outstanding',
            header: 'Encours',
            align: 'right',
            render: (point) => money(point.outstanding_amount),
        },
        {
            key: 'allocation',
            header: 'Allocation',
            align: 'right',
            render: (point) => money(point.credit_limit),
        },
    ];

    const salesColumns: TableColumn<RecentSale>[] = [
        {
            key: 'date',
            header: 'Date',
            render: (sale) =>
                new Intl.DateTimeFormat('fr-FR', {
                    dateStyle: 'medium',
                }).format(new Date(sale.created_at)),
        },
        {
            key: 'ticket',
            header: 'Billet',
            render: (sale) => (
                <span className="font-mono text-xs font-bold">
                    {sale.ticket_number}
                </span>
            ),
        },
        {
            key: 'point',
            header: 'Point',
            render: (sale) => (
                <Link
                    href={`/kora-partner/points/${sale.point_uuid}`}
                    className="font-semibold hover:underline"
                >
                    {sale.point_name}
                    <span className="ml-1 text-xs text-muted-foreground">
                        ({sale.point_code})
                    </span>
                </Link>
            ),
        },
        {
            key: 'passenger',
            header: 'Voyageur',
            render: (sale) => sale.passenger_name,
        },
        {
            key: 'amount',
            header: 'Montant',
            align: 'right',
            render: (sale) => money(sale.amount_paid),
        },
        {
            key: 'commission',
            header: 'Commission',
            align: 'right',
            render: (sale) => money(sale.commission_amount),
        },
    ];

    const navigate = (pageId: string) => {
        const routes: Record<string, string> = {
            points: '/kora-partner/points',
            sales: '/kora-partner/sales',
            tickets: '/kora-partner/sales',
            commissions: '/kora-partner/commissions',
            settlement: '/kora-points/remittances',
            remittances: '/kora-points/remittances',
        };
        router.visit(routes[pageId] ?? '/kora-partner');
    };

    return (
        <>
            <Head title={`Tableau de bord — ${organization.name}`} />
            <div className="cash-page w-full max-w-full space-y-5">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h1 className="text-xl font-bold tracking-tight text-foreground">
                        Tableau de bord
                    </h1>
                    <label className="text-xs font-semibold text-muted-foreground">
                        Point de vente
                        <select
                            className="ml-2 h-9 min-w-52 rounded-lg border border-border bg-card px-3 text-sm text-foreground"
                            value={selected_point_uuid ?? ''}
                            onChange={(event) =>
                                router.get(
                                    '/kora-partner',
                                    event.target.value
                                        ? { point_uuid: event.target.value }
                                        : {},
                                    {
                                        preserveScroll: true,
                                        preserveState: true,
                                    },
                                )
                            }
                        >
                            <option value="">Toute l’organisation</option>
                            {point_filter_options.map((point) => (
                                <option key={point.uuid} value={point.uuid}>
                                    {point.name} · {point.code}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>

                {!health.sales_allowed && health.network_point_count > 0 ? (
                    <div className="flex items-start gap-3 rounded-2xl border border-rose-500/20 bg-rose-500/10 p-4 text-sm text-foreground">
                        <CircleAlert className="mt-0.5 size-5 shrink-0 text-rose-600" />
                        <div>
                            <strong>
                                Tous les Points actifs sont bloqués.
                            </strong>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {health.block_reason}
                            </p>
                        </div>
                    </div>
                ) : null}

                <KoraPointHeroBanner
                    point={profile}
                    health={health}
                    mode="organization"
                    globalCreditLimit={health.global_credit_limit}
                    unallocatedAmount={health.unallocated_amount}
                    onViewPoints={() => navigate('points')}
                    onViewSettlements={() => navigate('remittances')}
                />
                <KoraPointKpiGrid
                    metrics={metrics}
                    terms={profile.terms}
                    mode="organization"
                    onKpiClick={(type) =>
                        navigate(
                            type === 'commissions'
                                ? 'commissions'
                                : type === 'settlement'
                                  ? 'remittances'
                                  : 'sales',
                        )
                    }
                />
                <KoraPointQuickActions
                    mode="organization"
                    onNavigate={navigate}
                />
                <KoraPointActivityChart
                    activity7d={metrics.activity_7d}
                    priorities={priorities}
                    mode="organization"
                    onQuickAction={navigate}
                />

                <section className="cash-reveal cash-reveal-delay-2">
                    <div className="cash-section-intro">
                        <div>
                            <span className="section-kicker">
                                Réseau partenaire
                            </span>
                            <h2>Activité des Points</h2>
                        </div>
                        <Link
                            href="/kora-partner/points"
                            className="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline"
                        >
                            Tous les Points <ArrowRight className="size-4" />
                        </Link>
                    </div>
                    <KoraTable
                        title="Points de vente de l’organisation"
                        subtitle={`${health.network_point_count} Point(s) actif(s) · les alertes restent propres à chaque Point`}
                        icon={Store}
                        columns={pointColumns}
                        data={points}
                        rowKey={(point) => point.uuid}
                        emptyTitle="Aucun Point de vente"
                        emptyMessage="Les Points rattachés à l’organisation apparaîtront ici."
                        emptyIcon={Store}
                    />
                </section>

                <section className="cash-reveal cash-reveal-delay-3">
                    <div className="cash-section-intro">
                        <div>
                            <span className="section-kicker">
                                Dernières opérations
                            </span>
                            <h2>Ventes récentes</h2>
                        </div>
                        <Link
                            href="/kora-partner/sales"
                            className="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline"
                        >
                            Voir les ventes <ArrowRight className="size-4" />
                        </Link>
                    </div>
                    <KoraTable
                        title="Activité récente de toute l’organisation"
                        subtitle={`${recent_sales_total} vente(s) au total`}
                        icon={Ticket}
                        columns={salesColumns}
                        data={recent_sales}
                        rowKey={(sale) => sale.uuid}
                        emptyTitle="Aucune vente enregistrée"
                        emptyMessage="Les ventes de vos Points apparaîtront ici."
                        emptyIcon={Ticket}
                    />
                </section>

                <p className="text-xs text-muted-foreground">
                    {is_treasurer
                        ? 'Les reversements groupés sont préparés depuis le menu Reversements.'
                        : 'Les reversements groupés de l’organisation sont accessibles depuis le menu Reversements.'}
                </p>
            </div>
        </>
    );
}
