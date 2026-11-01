import { useState, useCallback, useMemo } from 'react';
import {
    DemoScenarioId,
    FinancialHealth,
    getScenarioData,
    KoraPointProfile,
    PointPriorityAlert,
    PointSaleRecord,
    DailyMetrics,
} from './kora-point-mock-data';

export type DemoState = {
    scenarioId: DemoScenarioId;
    point: KoraPointProfile;
    health: FinancialHealth;
    metrics: DailyMetrics;
    priorities: PointPriorityAlert[];
    sales: PointSaleRecord[];
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

    const changeScenario = useCallback((newScenario: DemoScenarioId) => {
        setScenarioId(newScenario);
        setStateOverride(null);
    }, []);

    const resetDemo = useCallback(() => {
        setStateOverride(null);
    }, []);

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

            setStateOverride({
                sales: [newSaleRecord, ...sales],
                health: updatedHealth,
                metrics: updatedMetrics,
            });

            return newSaleRecord;
        },
        [point, health, metrics, sales],
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
        recordSimulatedSale,
    };
}
