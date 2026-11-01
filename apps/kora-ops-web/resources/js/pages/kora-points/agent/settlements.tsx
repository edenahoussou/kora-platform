import { Deferred, Head, router } from '@inertiajs/react';
import { handleAgentNavigate } from '@/components/kora-points/common/agent-nav';
import { KoraPointShell } from '@/components/kora-points/common/kora-point-shell';
import { KoraPointSettlementListView } from '@/components/kora-points/settlements/kora-point-settlement-list-view';
import { Skeleton } from '@/components/ui/skeleton';
import type {
    FinancialHealth,
    KoraPaymentDetails,
    KoraPointProfile,
    PointSettlementMethod,
    PointSettlementRecord,
} from '@/types/kora-points';

type PaginatedSettlements = {
    data: PointSettlementRecord[];
    current_page: number;
    last_page: number;
    total: number;
};

type Props = {
    point: KoraPointProfile;
    health: FinancialHealth;
    settlements?: PaginatedSettlements;
    koraPaymentDetails?: KoraPaymentDetails;
};

function SettlementsSkeleton() {
    return (
        <div className="space-y-4 animate-pulse">
            <div className="flex items-center justify-between">
                <Skeleton className="h-10 w-64 rounded-xl" />
                <Skeleton className="h-10 w-44 rounded-xl" />
            </div>
            <Skeleton className="h-16 w-full rounded-2xl" />
            <Skeleton className="h-16 w-full rounded-2xl" />
            <Skeleton className="h-16 w-full rounded-2xl" />
        </div>
    );
}

export default function AgentSettlementsPage({
    point,
    health,
    settlements,
    koraPaymentDetails,
}: Props) {
    const handleDeclareSettlement = (data: {
        saleUuids: string[];
        paymentMethod: PointSettlementMethod;
        paymentReference: string;
        proofFile: File;
        bankName?: string;
        notes?: string;
    }) => {
        const methodMap: Record<PointSettlementMethod, string> = {
            BANK_TRANSFER: 'bank_transfer',
            BANK_DEPOSIT: 'bank_deposit',
            CHECK: 'check',
            MOBILE_MONEY: 'mobile_money',
        };

        const formData = new FormData();
        data.saleUuids.forEach((uuid) => formData.append('sale_uuids[]', uuid));
        formData.append('method', methodMap[data.paymentMethod] ?? 'bank_transfer');
        formData.append('reference', data.paymentReference);
        formData.append('proof', data.proofFile);
        if (data.bankName) {
            formData.append('bank_name', data.bankName);
        }
        if (data.notes) {
            formData.append('notes', data.notes);
        }

        router.post('/kora-point/settlements/declare', formData, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Versements & Décomptes — KORA POINT" />
            <KoraPointShell
                point={point}
                health={health}
                activePage="settlements"
                onNavigate={handleAgentNavigate}
            >
                <Deferred data="settlements" fallback={<SettlementsSkeleton />}>
                    {settlements ? (
                        <KoraPointSettlementListView
                            point={point}
                            health={health}
                            settlements={settlements.data}
                            koraPaymentDetails={koraPaymentDetails}
                            onDeclareSettlement={handleDeclareSettlement}
                        />
                    ) : (
                        <SettlementsSkeleton />
                    )}
                </Deferred>
            </KoraPointShell>
        </>
    );
}
