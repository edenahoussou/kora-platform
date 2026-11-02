import { Head, router } from '@inertiajs/react';
import { Pagination } from '@/components/kora-points/partner/partner-ui';
import { KoraPointTicketListView } from '@/components/kora-points/tickets/kora-point-ticket-list-view';
import type {
    Paginated,
    PartnerOrganization,
} from '@/types/kora-point-partner';
import type { PointSaleRecord } from '@/types/kora-points';

type PartnerSaleRecord = PointSaleRecord & {
    point_uuid: string;
    point_name: string;
    point_code: string;
};
type PointOption = { uuid: string; name: string; code: string };

export default function PartnerSalesPage({
    organization,
    sales,
    point_filter_options,
    selected_point_uuid,
}: {
    organization: PartnerOrganization;
    sales: Paginated<PartnerSaleRecord>;
    point_filter_options: PointOption[];
    selected_point_uuid: string | null;
}) {
    return (
        <>
            <Head title={`Ventes — ${organization.name}`} />
            <div className="cash-page w-full max-w-full space-y-5">
                <KoraPointTicketListView
                    sales={sales.data}
                    showPoint
                    scopeLabel="Espace partenaire"
                    toolbarSlot={
                        <label className="text-xs font-semibold text-muted-foreground">
                            Point de vente
                            <select
                                className="ml-2 h-10 min-w-52 rounded-xl border border-border bg-card px-3 text-sm text-foreground"
                                value={selected_point_uuid ?? ''}
                                onChange={(event) =>
                                    router.get(
                                        '/kora-partner/sales',
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
                    }
                />
                <div className="rounded-2xl border border-border bg-card">
                    <div className="flex items-center justify-between gap-3 px-5 py-3 text-xs text-muted-foreground">
                        <span>{sales.total} vente(s)</span>
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
