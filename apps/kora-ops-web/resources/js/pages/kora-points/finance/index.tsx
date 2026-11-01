import { Head, router } from '@inertiajs/react';
import { KoraFinanceReconciliationView } from '@/components/kora-points/finance/kora-finance-reconciliation-view';
import type {
    GroupSettlementRecord,
    PointRefundRecord,
    PointSettlementRecord,
} from '@/components/kora-points/mock/kora-point-mock-data';

type Props = {
    settlements?: PointSettlementRecord[];
    refunds?: PointRefundRecord[];
    groupSettlements?: GroupSettlementRecord[];
    stats?: {
        totalToReconcile: number;
        refundsInArbitration: number;
        monthlyValidatedRemittances: number;
        totalRemittancesCount: number;
        totalRefundsCount: number;
    };
};

export default function KoraPointFinanceIndexPage({
    settlements = [],
    refunds = [],
    groupSettlements = [],
}: Props) {
    const handleReconcileSettlement = (
        settlementUuid: string,
        params: {
            bankAmount: number;
            valueDate: string;
            bankReference?: string;
            notes?: string;
        },
    ) => {
        router.post(
            `/kora-points/remittances/${settlementUuid}/receive`,
            {
                received_amount: params.bankAmount,
                bankAmount: params.bankAmount,
                note: params.notes,
                notes: params.notes,
                reject: false,
            },
            {
                preserveScroll: true,
            },
        );
    };

    const handleRejectSettlement = (
        settlementUuid: string,
        params: { reason: string },
    ) => {
        router.post(
            `/kora-points/remittances/${settlementUuid}/receive`,
            {
                reject: true,
                note: params.reason,
                reason: params.reason,
                received_amount: 0,
            },
            {
                preserveScroll: true,
            },
        );
    };

    const handleApproveRefund = (
        refundUuid: string,
        params: {
            payoutMode: 'POINT_CASH' | 'KORA_DIRECT';
            notes?: string;
        },
    ) => {
        router.post(
            `/kora-points/refunds/${refundUuid}/authorize`,
            {
                payout_mode: params.payoutMode,
                notes: params.notes,
            },
            {
                preserveScroll: true,
            },
        );
    };

    const handleRejectRefund = (
        refundUuid: string,
        params: { reason: string },
    ) => {
        router.post(
            `/kora-points/refunds/${refundUuid}/reject`,
            {
                reason: params.reason,
            },
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head title="Rapprochement et Finance KORA POINT - Réseau commercial" />
            <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <KoraFinanceReconciliationView
                    settlements={settlements}
                    refunds={refunds}
                    groupSettlements={groupSettlements}
                    onReconcileSettlement={handleReconcileSettlement}
                    onRejectSettlement={handleRejectSettlement}
                    onApproveRefund={handleApproveRefund}
                    onRejectRefund={handleRejectRefund}
                />
            </div>
        </>
    );
}
