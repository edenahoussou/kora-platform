import { Head, router } from '@inertiajs/react';
import { Pagination } from '@/components/kora-points/partner/partner-ui';
import { KoraPointRefundListView } from '@/components/kora-points/refunds/kora-point-refund-list-view';
import type {
    Paginated,
    PartnerOrganization,
} from '@/types/kora-point-partner';
import type { KoraPointProfile, PointRefundRecord } from '@/types/kora-points';

type RefundRow = PointRefundRecord & {
    point_uuid: string;
    point_name: string;
    point_code: string;
};
type PointOption = { uuid: string; name: string; code: string };
type RefundSummary = {
    pending: number;
    authorized: number;
    completed: number;
    total_refunded: number;
    commission_clawback: number;
    count: number;
};

export default function PartnerRefundsPage({
    organization,
    refunds,
    refund_summary,
    point_filter_options,
    selected_point_uuid,
}: {
    organization: PartnerOrganization;
    refunds: Paginated<RefundRow>;
    refund_summary: RefundSummary;
    point_filter_options: PointOption[];
    selected_point_uuid: string | null;
}) {
    const point: KoraPointProfile = {
        uuid: organization.uuid,
        code: organization.code,
        name: organization.name,
        city: '',
        country: organization.country,
        address: organization.address ?? '',
        manager_name: organization.name,
        manager_phone: organization.phone ?? '',
        manager_email: '',
        status: 'ready',
        logo_url: organization.logo_url,
        organization: null,
        terms: {
            commission_rate_bps: organization.commission_basis_points,
            credit_limit_amount: organization.ceiling_amount,
            settlement_schedule: {
                frequency:
                    organization.settlement_calendar?.frequency ?? 'daily',
                time: organization.settlement_calendar?.time ?? '17:00',
                timezone:
                    organization.settlement_calendar?.timezone ??
                    'Africa/Porto-Novo',
            },
            grace_period_days: 0,
        },
    };

    return (
        <>
            <Head title={`Remboursements — ${organization.name}`} />
            <div className="cash-page w-full max-w-full space-y-5">
                <KoraPointRefundListView
                    point={point}
                    refunds={refunds.data}
                    scopeLabel="Espace partenaire"
                    showPoint
                    summary={{
                        pendingFinance: refund_summary.pending,
                        readyForRestitution: refund_summary.authorized,
                        totalRefunded: refund_summary.total_refunded,
                        totalClawback: refund_summary.commission_clawback,
                        totalCount: refund_summary.count,
                    }}
                    toolbarSlot={
                        <label className="text-xs font-semibold text-muted-foreground">
                            Point de vente
                            <select
                                className="ml-2 h-10 min-w-52 rounded-xl border border-border bg-card px-3 text-sm text-foreground"
                                value={selected_point_uuid ?? ''}
                                onChange={(event) =>
                                    router.get(
                                        '/kora-partner/refunds',
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
                                {point_filter_options.map((option) => (
                                    <option
                                        key={option.uuid}
                                        value={option.uuid}
                                    >
                                        {option.name} · {option.code}
                                    </option>
                                ))}
                            </select>
                        </label>
                    }
                />
                <div className="rounded-2xl border border-border bg-card">
                    <div className="flex items-center justify-between gap-3 px-5 py-3 text-xs text-muted-foreground">
                        <span>{refunds.total} dossier(s)</span>
                        <span>
                            Page {refunds.current_page} sur {refunds.last_page}
                        </span>
                    </div>
                    {refunds.links.length > 3 ? (
                        <Pagination links={refunds.links} />
                    ) : null}
                </div>
            </div>
        </>
    );
}
