import { useState, useCallback, useMemo } from 'react';
import {
    DemoScenarioId,
    FinancialHealth,
    getScenarioData,
    KoraPointProfile,
    PointPriorityAlert,
    PointSaleRecord,
    DailyMetrics,
    PointCashSession,
    MOCK_CASH_SESSION_OPEN,
    PointConvention,
    MOCK_CONVENTION,
    PointDocument,
    MOCK_DOCUMENTS,
    PointAssignedUser,
    MOCK_POINT_USERS,
    PointCommissionRecord,
    MOCK_COMMISSION_RECORDS,
    PointSettlementRecord,
    MOCK_POINT_SETTLEMENTS,
    PointRefundRecord,
    MOCK_POINT_REFUNDS,
    OrganizationSubPoint,
    MOCK_ORGANIZATION_SUBPOINTS,
    GroupSettlementRecord,
    MOCK_GROUP_SETTLEMENTS,
    GroupSettlementBreakdown,
} from './kora-point-mock-data';

export type DemoState = {
    scenarioId: DemoScenarioId;
    point: KoraPointProfile;
    health: FinancialHealth;
    metrics: DailyMetrics;
    priorities: PointPriorityAlert[];
    sales: PointSaleRecord[];
    cashSession: PointCashSession;
    convention: PointConvention;
    documents: PointDocument[];
    assignedUsers: PointAssignedUser[];
    commissions: PointCommissionRecord[];
    settlements: PointSettlementRecord[];
    refunds: PointRefundRecord[];
    organizationSubPoints: OrganizationSubPoint[];
    groupSettlements: GroupSettlementRecord[];
};

export function useKoraPointDemo(initialScenario: DemoScenarioId = 'autonomous_healthy') {
    const [scenarioId, setScenarioId] = useState<DemoScenarioId>(initialScenario);
    const [stateOverride, setStateOverride] = useState<Partial<DemoState> | null>(null);

    const baseData = useMemo(() => getScenarioData(scenarioId), [scenarioId]);

    const point = stateOverride?.point ?? baseData.point;
    const health = stateOverride?.health ?? baseData.health;
    const metrics = stateOverride?.metrics ?? baseData.metrics;
    const priorities = stateOverride?.priorities ?? baseData.priorities;
    const sales = stateOverride?.sales ?? baseData.sales;
    const cashSession = stateOverride?.cashSession ?? MOCK_CASH_SESSION_OPEN;
    const convention = stateOverride?.convention ?? MOCK_CONVENTION;
    const documents = stateOverride?.documents ?? MOCK_DOCUMENTS;
    const assignedUsers = stateOverride?.assignedUsers ?? MOCK_POINT_USERS;
    const commissions = stateOverride?.commissions ?? MOCK_COMMISSION_RECORDS;
    const settlements = stateOverride?.settlements ?? MOCK_POINT_SETTLEMENTS;
    const refunds = stateOverride?.refunds ?? MOCK_POINT_REFUNDS;
    const organizationSubPoints =
        stateOverride?.organizationSubPoints ?? MOCK_ORGANIZATION_SUBPOINTS;
    const groupSettlements = stateOverride?.groupSettlements ?? MOCK_GROUP_SETTLEMENTS;

    const changeScenario = useCallback((newScenario: DemoScenarioId) => {
        setScenarioId(newScenario);
        setStateOverride(null);
    }, []);

    const resetDemo = useCallback(() => {
        setStateOverride(null);
    }, []);

    const openCashSession = useCallback((openingAmount: number) => {
        const newSession: PointCashSession = {
            status: 'OPEN',
            opened_at: 'À l’instant',
            operator_name: point.manager_name,
            opening_amount: openingAmount,
            cash_collected: 0,
            mobile_money_collected: 0,
            expected_closing_cash: openingAmount,
            movements: [
                {
                    id: `m-${Date.now()}`,
                    time_label: new Date().toLocaleTimeString('fr-FR', {
                        hour: '2-digit',
                        minute: '2-digit',
                    }),
                    label: 'Ouverture de session — Fond de caisse initial',
                    amount: openingAmount,
                    type: 'OPENING',
                    method: 'CASH',
                    balance_after: openingAmount,
                },
            ],
        };

        setStateOverride((prev) => ({
            ...prev,
            cashSession: newSession,
        }));
    }, [point]);

    const closeCashSession = useCallback((actualCash: number, closingNote?: string) => {
        const expected = cashSession.expected_closing_cash;
        const variance = actualCash - expected;

        const closedSession: PointCashSession = {
            ...cashSession,
            status: 'CLOSED',
            closed_at: 'À l’instant',
            actual_closing_cash: actualCash,
            variance_amount: variance,
            closing_note: closingNote,
        };

        setStateOverride((prev) => ({
            ...prev,
            cashSession: closedSession,
        }));
    }, [cashSession]);

    const recordSimulatedSale = useCallback(
        (sale: {
            passengerName: string;
            passengerPhone: string;
            departureRef: string;
            serviceClass: string;
            departureTime: string;
            routeLabel: string;
            originCity: string;
            destinationCity: string;
            seatNumber: string;
            amountPaid: number;
            paymentMethod: 'CASH' | 'MOBILE_MONEY';
        }) => {
            const commissionRateBps = point.terms.commission_rate_bps;
            const commissionAmount = Math.round((sale.amountPaid * commissionRateBps) / 10000);
            const shortCode = String(Math.floor(1000 + Math.random() * 9000));
            const ticketNumber = `TK-AKP-${new Date().toISOString().slice(2, 10).replace(/-/g, '')}-${shortCode}`;

            const newSaleRecord: PointSaleRecord = {
                uuid: `sale-${Date.now()}`,
                ticket_number: ticketNumber,
                short_code: shortCode,
                created_at: new Date().toISOString().slice(0, 16).replace('T', ' '),
                date_label: 'À l’instant',
                seller_name: point.manager_name,
                departure_ref: sale.departureRef,
                service_class: sale.serviceClass,
                departure_time: sale.departureTime,
                route_label: sale.routeLabel,
                origin_city: sale.originCity,
                destination_city: sale.destinationCity,
                seat_number: sale.seatNumber,
                passenger_name: sale.passengerName,
                passenger_phone: sale.passengerPhone,
                payment_method: sale.paymentMethod,
                amount_paid: sale.amountPaid,
                commission_rate_bps: commissionRateBps,
                commission_amount: commissionAmount,
                settlement_status: 'open',
                refundable: true,
            };

            const newCommissionLine: PointCommissionRecord = {
                uuid: `com-${Date.now()}`,
                sale_uuid: newSaleRecord.uuid,
                ticket_number: ticketNumber,
                date_label: 'À l’instant',
                departure_ref: sale.departureRef,
                route_label: sale.routeLabel,
                seller_name: point.manager_name,
                amount_paid: sale.amountPaid,
                commission_rate_bps: commissionRateBps,
                initial_commission: commissionAmount,
                correction_amount: 0,
                net_commission: commissionAmount,
                settlement_status: 'open',
                has_refund_correction: false,
            };

            const newExposure = health.current_exposure + sale.amountPaid;
            const newAvailable = Math.max(0, health.credit_limit - newExposure);
            const usagePct = Math.min(100, Math.round((newExposure / health.credit_limit) * 100));

            const updatedHealth: FinancialHealth = {
                ...health,
                current_exposure: newExposure,
                available_balance: newAvailable,
                usage_percentage: usagePct,
                sales_allowed: newAvailable >= 7000,
                block_reason:
                    newAvailable < 7000
                        ? 'Plafond atteint après cette vente. Reversement requis pour continuer.'
                        : health.block_reason,
            };

            const updatedMetrics: DailyMetrics = {
                ...metrics,
                today_sales_amount: metrics.today_sales_amount + sale.amountPaid,
                today_tickets_count: metrics.today_tickets_count + 1,
                today_commission_amount: metrics.today_commission_amount + commissionAmount,
                total_due_amount: newExposure,
            };

            // Update cash session movements if open
            let updatedCashSession = cashSession;
            if (cashSession.status === 'OPEN') {
                const isCash = sale.paymentMethod === 'CASH';
                const newCashCollected = cashSession.cash_collected + (isCash ? sale.amountPaid : 0);
                const newMobileCollected = cashSession.mobile_money_collected + (isCash ? 0 : sale.amountPaid);
                const newExpectedCash = cashSession.opening_amount + newCashCollected;
                const newMovement = {
                    id: `m-${Date.now()}`,
                    time_label: new Date().toLocaleTimeString('fr-FR', {
                        hour: '2-digit',
                        minute: '2-digit',
                    }),
                    label: `Vente billet ${ticketNumber} (${sale.passengerName})`,
                    amount: sale.amountPaid,
                    type: isCash ? ('SALE_CASH' as const) : ('SALE_MOBILE' as const),
                    method: sale.paymentMethod,
                    balance_after: newExpectedCash,
                };

                updatedCashSession = {
                    ...cashSession,
                    cash_collected: newCashCollected,
                    mobile_money_collected: newMobileCollected,
                    expected_closing_cash: newExpectedCash,
                    movements: [newMovement, ...cashSession.movements],
                };
            }

            setStateOverride((prev) => ({
                ...prev,
                sales: [newSaleRecord, ...sales],
                commissions: [newCommissionLine, ...commissions],
                health: updatedHealth,
                metrics: updatedMetrics,
                cashSession: updatedCashSession,
            }));

            return newSaleRecord;
        },
        [point, health, metrics, sales, commissions, cashSession],
    );

    const createSettlementDeclaration = useCallback(
        (data: {
            amountDeclared: number;
            paymentMethod: 'BANK_TRANSFER' | 'BANK_DEPOSIT' | 'MOBILE_MONEY';
            paymentReference: string;
            proofFileName?: string;
            notes?: string;
        }) => {
            const newRef = `REV-2026-00${Math.floor(45 + Math.random() * 50)}`;
            const dateStr = new Date().toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: 'short',
            });
            const timeStr = new Date().toLocaleTimeString('fr-FR', {
                hour: '2-digit',
                minute: '2-digit',
            });

            const newSettlement: PointSettlementRecord = {
                uuid: `set-${Date.now()}`,
                reference: newRef,
                point_uuid: point.uuid,
                point_name: point.name,
                created_by_name: point.manager_name,
                period_start: '2026-08-01',
                period_end: '2026-08-07',
                created_at: `${dateStr} ${timeStr}`,
                declared_at: `${dateStr} ${timeStr}`,
                amount_expected: health.current_exposure,
                amount_declared: data.amountDeclared,
                payment_method: data.paymentMethod,
                payment_reference: data.paymentReference,
                proof_file_name: data.proofFileName || 'preuve_versement.pdf',
                proof_file_size: '340 Ko',
                status: 'DECLARED',
                sales_count: sales.filter((s) => s.settlement_status === 'open').length,
                tickets_covered: sales
                    .filter((s) => s.settlement_status === 'open')
                    .map((s) => s.ticket_number),
                notes: data.notes,
                timeline: [
                    {
                        step: 'Déclaration transmise par le partenaire',
                        date_label: `${dateStr} ${timeStr}`,
                        actor: `${point.manager_name} (Agent)`,
                        comment: `Montant déclaré : ${data.amountDeclared.toLocaleString()} FCFA (${data.paymentReference})`,
                    },
                    {
                        step: 'Contrôle et validation Finance KORA',
                        date_label: 'En attente',
                        actor: 'Pôle Trésorerie KORA',
                        comment: 'Vérification bancaire sous 24h ouvrées.',
                    },
                ],
            };

            // Simulating exposure reduction in demo
            const newExposure = Math.max(0, health.current_exposure - data.amountDeclared);
            const newAvailable = Math.max(0, health.credit_limit - newExposure);
            const newPercentage = Math.round((newExposure / health.credit_limit) * 100);

            const updatedHealth: FinancialHealth = {
                ...health,
                current_exposure: newExposure,
                available_balance: newAvailable,
                usage_percentage: newPercentage,
                sales_allowed: newPercentage < 100 && !health.is_overdue,
            };

            // Update sales settlement status to in_settlement
            const updatedSales = sales.map((s) =>
                s.settlement_status === 'open'
                    ? { ...s, settlement_status: 'in_settlement' as const, settlement_ref: newRef }
                    : s,
            );

            setStateOverride((prev) => ({
                ...prev,
                settlements: [newSettlement, ...settlements],
                health: updatedHealth,
                sales: updatedSales,
            }));

            return newSettlement;
        },
        [point, health, sales, settlements],
    );

    const initiateRefundRequest = useCallback(
        (data: {
            ticketNumber: string;
            passengerName: string;
            passengerPhone: string;
            originalAmount: number;
            reason: 'TRIP_CANCELLED' | 'PASSENGER_SICKNESS' | 'DUPLICATE_PURCHASE' | 'SCHEDULE_CHANGE';
            reasonLabel: string;
            retainedFee?: number;
            proofDocumentName?: string;
        }) => {
            const newRef = `REM-2026-00${Math.floor(10 + Math.random() * 50)}`;
            const dateStr = new Date().toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: 'short',
            });
            const timeStr = new Date().toLocaleTimeString('fr-FR', {
                hour: '2-digit',
                minute: '2-digit',
            });
            const retainedFee = data.retainedFee ?? 0;
            const refundAmount = data.originalAmount - retainedFee;
            const commissionRateBps = point.terms.commission_rate_bps;
            const commissionClawback = -Math.round((refundAmount * commissionRateBps) / 10000);

            const matchedSale = sales.find((s) => s.ticket_number === data.ticketNumber);

            const newRefund: PointRefundRecord = {
                uuid: `ref-${Date.now()}`,
                reference: newRef,
                ticket_number: data.ticketNumber,
                sale_uuid: matchedSale?.uuid ?? `sale-mock`,
                passenger_name: data.passengerName,
                passenger_phone: data.passengerPhone,
                departure_ref: matchedSale?.departure_ref ?? 'KE 118',
                route_label: matchedSale?.route_label ?? 'Cotonou → Parakou',
                original_amount: data.originalAmount,
                retained_fee: retainedFee,
                refund_amount: refundAmount,
                commission_clawback: commissionClawback,
                reason: data.reason,
                reason_label: data.reasonLabel,
                status: 'REQUESTED',
                requested_at: `${dateStr} ${timeStr}`,
                requested_by: `${point.manager_name} (Agent ${point.name})`,
                proof_document: data.proofDocumentName,
                timeline: [
                    {
                        step: 'Demande saisie au guichet',
                        date_label: `${dateStr} ${timeStr}`,
                        actor: `${point.manager_name} (Agent)`,
                        detail: `Motif : ${data.reasonLabel}. Billet ${data.ticketNumber}`,
                    },
                    {
                        step: 'Instruction Finance KORA',
                        date_label: 'En attente',
                        actor: 'Service Contrôle Billetterie',
                        detail: 'Contrôle de conformité et calcul de régularisation.',
                    },
                ],
            };

            // Add negative commission adjustment line
            const newCommissionLine: PointCommissionRecord = {
                uuid: `com-adj-${Date.now()}`,
                sale_uuid: matchedSale?.uuid ?? `sale-mock`,
                ticket_number: data.ticketNumber,
                date_label: 'À l’instant',
                departure_ref: matchedSale?.departure_ref ?? 'KE 118',
                route_label: matchedSale?.route_label ?? 'Cotonou → Parakou',
                seller_name: point.manager_name,
                amount_paid: data.originalAmount,
                commission_rate_bps: commissionRateBps,
                initial_commission: Math.round((data.originalAmount * commissionRateBps) / 10000),
                correction_amount: commissionClawback,
                net_commission: Math.round((data.originalAmount * commissionRateBps) / 10000) + commissionClawback,
                settlement_status: 'open',
                has_refund_correction: true,
                refund_ref: newRef,
                refund_reason: `Régularisation pour dossier ${newRef} (${data.reasonLabel})`,
            };

            setStateOverride((prev) => ({
                ...prev,
                refunds: [newRefund, ...refunds],
                commissions: [newCommissionLine, ...commissions],
            }));

            return newRefund;
        },
        [point, sales, refunds, commissions],
    );

    const confirmRefundRestitution = useCallback(
        (refundUuid: string, signatoryName: string, receiptNote?: string) => {
            const dateStr = new Date().toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: 'short',
            });
            const timeStr = new Date().toLocaleTimeString('fr-FR', {
                hour: '2-digit',
                minute: '2-digit',
            });

            const targetRefund = refunds.find((r) => r.uuid === refundUuid);
            if (!targetRefund) return;

            const updatedRefund: PointRefundRecord = {
                ...targetRefund,
                status: 'FUNDS_RESTITUTED',
                restituted_at: `${dateStr} ${timeStr}`,
                restituted_by: `${point.manager_name} (Agent)`,
                receipt_signed: true,
                timeline: [
                    ...targetRefund.timeline,
                    {
                        step: 'Espèces remises au voyageur',
                        date_label: `${dateStr} ${timeStr}`,
                        actor: `${point.manager_name} (Agent)`,
                        detail: `${targetRefund.refund_amount.toLocaleString()} FCFA remis en main propre à ${signatoryName}. Quittance d'émargement générée.`,
                    },
                ],
            };

            // Deduct cash from till if cash session is open
            let updatedCashSession = cashSession;
            if (cashSession.status === 'OPEN') {
                const newExpectedCash = Math.max(
                    0,
                    cashSession.expected_closing_cash - targetRefund.refund_amount,
                );
                const movement = {
                    id: `m-${Date.now()}`,
                    time_label: timeStr,
                    label: `Restitution remboursement ${targetRefund.reference} (${targetRefund.passenger_name})`,
                    amount: -targetRefund.refund_amount,
                    type: 'WITHDRAWAL' as const,
                    method: 'CASH' as const,
                    balance_after: newExpectedCash,
                };
                updatedCashSession = {
                    ...cashSession,
                    expected_closing_cash: newExpectedCash,
                    movements: [movement, ...cashSession.movements],
                };
            }

            setStateOverride((prev) => ({
                ...prev,
                refunds: refunds.map((r) => (r.uuid === refundUuid ? updatedRefund : r)),
                cashSession: updatedCashSession,
            }));
        },
        [point, refunds, cashSession],
    );

    const createGroupSettlementDeclaration = useCallback(
        (data: {
            totalAmountDeclared: number;
            paymentMethod: 'BANK_TRANSFER' | 'BANK_DEPOSIT' | 'MOBILE_MONEY';
            paymentReference: string;
            proofFileName?: string;
            breakdown: GroupSettlementBreakdown[];
            notes?: string;
        }) => {
            const newRef = `REV-ORG-2026-00${Math.floor(10 + Math.random() * 50)}`;
            const dateStr = new Date().toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: 'short',
            });
            const timeStr = new Date().toLocaleTimeString('fr-FR', {
                hour: '2-digit',
                minute: '2-digit',
            });

            const totalExpected = data.breakdown.reduce(
                (sum, b) => sum + b.current_exposure,
                0,
            );

            const newDeclaration: GroupSettlementRecord = {
                uuid: `grp-${Date.now()}`,
                reference: newRef,
                organization_name: point.organization?.name || 'La Poste du Bénin SA',
                created_by_name: `${point.manager_name} (Trésorier)`,
                created_at: `${dateStr} à ${timeStr}`,
                total_amount_expected: totalExpected,
                total_amount_declared: data.totalAmountDeclared,
                payment_method: data.paymentMethod,
                payment_reference: data.paymentReference,
                proof_file_name: data.proofFileName || 'ordre_virement_groupé.pdf',
                status: 'DECLARED',
                breakdown: data.breakdown,
                notes: data.notes,
                timeline: [
                    {
                        step: 'Déclaration de reversement groupé transmise',
                        date_label: `${dateStr} ${timeStr}`,
                        actor: `${point.manager_name} (Trésorier)`,
                        comment: `Total : ${data.totalAmountDeclared.toLocaleString()} FCFA sur ${data.breakdown.length} bureaux. Réf: ${data.paymentReference}`,
                    },
                    {
                        step: 'Rapprochement comptable KORA Trésorerie',
                        date_label: 'En cours',
                        actor: 'Pôle Trésorerie KORA',
                        comment: 'Vérification de la conformité du virement bancaire sous 24-48h.',
                    },
                ],
            };

            // Update subpoints exposure in demo
            const updatedSubPoints = organizationSubPoints.map((sp) => {
                const alloc = data.breakdown.find((b) => b.subpoint_uuid === sp.uuid);
                if (alloc) {
                    const newExposure = Math.max(0, sp.current_exposure - alloc.amount_allocated);
                    const newUsage = Math.round((newExposure / sp.credit_limit) * 100);
                    return {
                        ...sp,
                        current_exposure: newExposure,
                        usage_percentage: newUsage,
                        status:
                            newUsage >= 100
                                ? ('LIMIT_REACHED' as const)
                                : newUsage >= 80
                                ? ('WARNING_THRESHOLD' as const)
                                : ('ACTIVE' as const),
                    };
                }
                return sp;
            });

            setStateOverride((prev) => ({
                ...prev,
                groupSettlements: [newDeclaration, ...groupSettlements],
                organizationSubPoints: updatedSubPoints,
            }));

            return newDeclaration;
        },
        [point, organizationSubPoints, groupSettlements],
    );

    return {
        scenarioId,
        changeScenario,
        resetDemo,
        point,
        health,
        metrics,
        priorities,
        sales,
        cashSession,
        convention,
        documents,
        assignedUsers,
        commissions,
        settlements,
        refunds,
        organizationSubPoints,
        groupSettlements,
        openCashSession,
        closeCashSession,
        recordSimulatedSale,
        createSettlementDeclaration,
        initiateRefundRequest,
        confirmRefundRestitution,
        createGroupSettlementDeclaration,
    };
}
