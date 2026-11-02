import { Head, router } from '@inertiajs/react';
import { KoraPointCommissionsView } from '@/components/kora-points/commissions/kora-point-commissions-view';
import { Pagination } from '@/components/kora-points/partner/partner-ui';
import type {
    Paginated,
    PartnerOrganization,
} from '@/types/kora-point-partner';
import type {
    KoraPointProfile,
    PointCommissionRecord,
} from '@/types/kora-points';

type Period = 'current_month' | 'last_month' | 'all';
type CommissionRow = PointCommissionRecord & {
    point_name: string;
    point_code: string;
    point_uuid: string;
};
type PointOption = { uuid: string; name: string; code: string };

export default function PartnerCommissionsPage({
    organization,
    sales,
    totals,
    period,
    period_label,
    count,
    point_filter_options,
    selected_point_uuid,
}: {
    organization: PartnerOrganization;
    sales: Paginated<CommissionRow>;
    totals: {
        gross: number;
        initial: number;
        corrections: number;
        net: number;
    };
    period: Period;
    period_label: string;
    count: number;
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
                day_of_week: organization.settlement_calendar?.weekday ?? 5,
                day_of_month: organization.settlement_calendar?.day ?? 1,
                time: organization.settlement_calendar?.time ?? '17:00',
                timezone:
                    organization.settlement_calendar?.timezone ??
                    'Africa/Porto-Novo',
            },
            grace_period_days: 0,
        },
    };

    const changePeriod = (nextPeriod: Period) =>
        router.get(
            '/kora-partner/commissions',
            {
                period: nextPeriod,
                ...(selected_point_uuid
                    ? { point_uuid: selected_point_uuid }
                    : {}),
            },
            { preserveScroll: true, preserveState: true },
        );

    const changePoint = (pointUuid: string) =>
        router.get(
            '/kora-partner/commissions',
            {
                period,
                ...(pointUuid ? { point_uuid: pointUuid } : {}),
            },
            { preserveScroll: true, preserveState: true },
        );

    const exportUrl = (format: 'xlsx' | 'pdf') => {
        const params = new URLSearchParams({ format, period });

        if (selected_point_uuid) {
            params.set('point_uuid', selected_point_uuid);
        }

        return `/kora-partner/commissions/export?${params.toString()}`;
    };

    return (
        <>
            <Head title={`Commissions — ${organization.name}`} />
            <div className="cash-page w-full max-w-full space-y-5">
                <KoraPointCommissionsView
                    point={point}
                    commissions={sales.data}
                    scopeLabel="Espace partenaire"
                    showPoint
                    periodFilter={period}
                    periodLabel={period_label}
                    totals={totals}
                    operationCount={count}
                    onPeriodChange={changePeriod}
                    exportUrls={{
                        excel: exportUrl('xlsx'),
                        pdf: exportUrl('pdf'),
                    }}
                    toolbarSlot={
                        <label className="text-xs font-semibold text-muted-foreground">
                            Point de vente
                            <select
                                className="ml-2 h-10 min-w-52 rounded-xl border border-border bg-card px-3 text-sm text-foreground"
                                value={selected_point_uuid ?? ''}
                                onChange={(event) =>
                                    changePoint(event.target.value)
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
                        <span>{count} opération(s) sur la période</span>
                        <span>
                            Page {sales.current_page} sur {sales.last_page}
                        </span>
                    </div>
                    {sales.links.length > 3 ? (
                        <Pagination links={sales.links} />
                    ) : null}
                </div>
            </div>
        </>
    );
}
