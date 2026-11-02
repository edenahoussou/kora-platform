import { Head, router } from '@inertiajs/react';
import { KoraPointSettlementListView } from '@/components/kora-points/settlements/kora-point-settlement-list-view';
import type {
    FinancialHealth,
    KoraPaymentDetails,
    KoraPointProfile,
    PointSettlementMethod,
    PointSettlementRecord,
} from '@/types/kora-points';

type Props = {
    organization_name: string;
    point: KoraPointProfile;
    health: FinancialHealth;
    settlements: PointSettlementRecord[];
    koraPaymentDetails: KoraPaymentDetails;
};

export default function PartnerRemittancesPage({
    organization_name,
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
        formData.append('method', methodMap[data.paymentMethod]);
        formData.append('reference', data.paymentReference);
        formData.append('proof', data.proofFile);

        if (data.bankName) {
            formData.append('bank_name', data.bankName);
        }

        if (data.notes) {
            formData.append('notes', data.notes);
        }

        router.post('/kora-points/remittances/declare', formData, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={`Reversements — ${organization_name}`} />
            <div className="cash-page w-full max-w-full space-y-5">
                <KoraPointSettlementListView
                    point={point}
                    health={health}
                    settlements={settlements}
                    networkScope
                    organizationName={organization_name}
                    previewUrl="/kora-points/remittances/preview"
                    koraPaymentDetails={koraPaymentDetails}
                    onDeclareSettlement={handleDeclareSettlement}
                />
            </div>
        </>
    );
}
