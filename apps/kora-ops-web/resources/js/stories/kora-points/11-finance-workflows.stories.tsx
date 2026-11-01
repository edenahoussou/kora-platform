import type { Meta, StoryObj } from '@storybook/react';
import { useState } from 'react';
import { KoraFinanceReconciliationView } from '@/components/kora-points/finance/kora-finance-reconciliation-view';
import { KoraFinanceRefundActionDialog } from '@/components/kora-points/finance/kora-finance-refund-action-dialog';
import { KoraFinanceSettlementActionDialog } from '@/components/kora-points/finance/kora-finance-settlement-action-dialog';
import {
    MOCK_POINT_SETTLEMENTS,
    MOCK_POINT_REFUNDS,
    MOCK_GROUP_SETTLEMENTS,
} from '@/components/kora-points/mock/kora-point-mock-data';

const meta: Meta<typeof KoraFinanceReconciliationView> = {
    title: 'KORA POINT VOYAGE / 11 — Dossiers Finance KORA',
    component: KoraFinanceReconciliationView,
    parameters: {
        layout: 'padded',
    },
};

export default meta;
type Story = StoryObj<typeof KoraFinanceReconciliationView>;

function FinanceInteractiveStory() {
    const [settlements, setSettlements] = useState(MOCK_POINT_SETTLEMENTS);
    const [refunds, setRefunds] = useState(MOCK_POINT_REFUNDS);

    return (
        <div className="mx-auto max-w-7xl">
            <KoraFinanceReconciliationView
                settlements={settlements}
                refunds={refunds}
                groupSettlements={MOCK_GROUP_SETTLEMENTS}
                onReconcileSettlement={(uuid, params) => {
                    setSettlements((prev) =>
                        prev.map((s) =>
                            s.uuid === uuid
                                ? {
                                      ...s,
                                      status: 'VALIDATED',
                                      amount_validated: params.bankAmount,
                                  }
                                : s,
                        ),
                    );
                }}
                onRejectSettlement={(uuid, params) => {
                    setSettlements((prev) =>
                        prev.map((s) =>
                            s.uuid === uuid
                                ? {
                                      ...s,
                                      status: 'REJECTED',
                                      rejection_reason: params.reason,
                                  }
                                : s,
                        ),
                    );
                }}
                onApproveRefund={(uuid, params) => {
                    setRefunds((prev) =>
                        prev.map((r) =>
                            r.uuid === uuid
                                ? {
                                      ...r,
                                      status:
                                          params.payoutMode === 'POINT_CASH'
                                              ? 'APPROVED_BY_FINANCE'
                                              : 'FUNDS_RESTITUTED',
                                  }
                                : r,
                        ),
                    );
                }}
                onRejectRefund={(uuid, params) => {
                    setRefunds((prev) =>
                        prev.map((r) =>
                            r.uuid === uuid
                                ? {
                                      ...r,
                                      status: 'REJECTED',
                                      rejection_reason: params.reason,
                                  }
                                : r,
                        ),
                    );
                }}
            />
        </div>
    );
}

export const VueCompleteReconciliation: Story = {
    render: () => <FinanceInteractiveStory />,
};

function SettlementActionStory() {
    const [isOpen, setIsOpen] = useState(true);
    const settlement =
        MOCK_POINT_SETTLEMENTS.find((s) => s.status === 'DECLARED') ||
        MOCK_POINT_SETTLEMENTS[0];

    return (
        <div className="p-8">
            <button
                type="button"
                onClick={() => setIsOpen(true)}
                className="rounded-xl bg-gold px-4 py-2 text-xs font-black text-kora uppercase"
            >
                Ouvrir le rapprochement ({settlement.reference})
            </button>
            <KoraFinanceSettlementActionDialog
                isOpen={isOpen}
                settlement={settlement}
                onClose={() => setIsOpen(false)}
                onReconcile={(uuid, params) => {
                    alert(
                        `Rapprochement validé : ${params.bankAmount} FCFA au ${params.valueDate}`,
                    );
                    setIsOpen(false);
                }}
                onReject={(uuid, params) => {
                    alert(`Bordereau rejeté : ${params.reason}`);
                    setIsOpen(false);
                }}
            />
        </div>
    );
}

export const ModalRapprochementBancaire: StoryObj<
    typeof KoraFinanceSettlementActionDialog
> = {
    render: () => <SettlementActionStory />,
};

function RefundActionStory() {
    const [isOpen, setIsOpen] = useState(true);
    const refund =
        MOCK_POINT_REFUNDS.find((r) => r.status === 'REQUESTED') ||
        MOCK_POINT_REFUNDS[2];

    return (
        <div className="p-8">
            <button
                type="button"
                onClick={() => setIsOpen(true)}
                className="rounded-xl bg-gold px-4 py-2 text-xs font-black text-kora uppercase"
            >
                Ouvrir l'instruction ({refund.reference})
            </button>
            <KoraFinanceRefundActionDialog
                isOpen={isOpen}
                refund={refund}
                onClose={() => setIsOpen(false)}
                onApprove={(uuid, params) => {
                    alert(
                        `Remboursement approuvé en mode : ${params.payoutMode}`,
                    );
                    setIsOpen(false);
                }}
                onReject={(uuid, params) => {
                    alert(`Remboursement rejeté : ${params.reason}`);
                    setIsOpen(false);
                }}
            />
        </div>
    );
}

export const ModalInstructionRemboursement: StoryObj<
    typeof KoraFinanceRefundActionDialog
> = {
    render: () => <RefundActionStory />,
};
