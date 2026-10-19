import { CheckCircle2, Info, TriangleAlert } from 'lucide-react';
import { formatMoney } from '@/components/sale/sale-utils';
import { cn } from '@/lib/utils';
import type {
    ProfitabilityControl,
    ProfitabilityProps,
} from './profitability-types';

type Props = {
    reconciliation: ProfitabilityProps['reconciliation'];
    controls: ProfitabilityControl[];
};

export function ProfitabilityReconciliation({
    reconciliation,
    controls,
}: Props) {
    return (
        <section className="profit-reconciliation">
            <div className="profit-reconciliation-flow">
                <span className="section-kicker">Rapprochement</span>
                <h2>Caisse de la période et service rattaché</h2>
                <div className="profit-reconciliation-values">
                    <div>
                        <small>Recettes cash</small>
                        <strong>{formatMoney(reconciliation.cashNet)}</strong>
                        <em>{reconciliation.label}</em>
                    </div>
                    <span aria-hidden="true">→</span>
                    <div>
                        <small>Recettes de service</small>
                        <strong>
                            {formatMoney(reconciliation.serviceNet)}
                        </strong>
                        <em>Rattachées aux départs</em>
                    </div>
                    <span aria-hidden="true">=</span>
                    <div>
                        <small>Écart de timing</small>
                        <strong
                            className={cn(
                                reconciliation.timingGap !== 0 &&
                                    'text-amber-600 dark:text-gold',
                            )}
                        >
                            {formatMoney(reconciliation.timingGap)}
                        </strong>
                        <em>Ventes anticipées ou décalées</em>
                    </div>
                </div>
            </div>
            <div className="profit-controls">
                {controls.map((control) => {
                    const Icon =
                        control.status === 'ok'
                            ? CheckCircle2
                            : control.status === 'warning'
                              ? TriangleAlert
                              : Info;

                    return (
                        <article
                            key={control.key}
                            className={cn(
                                'profit-control',
                                `profit-control-${control.status}`,
                            )}
                        >
                            <Icon className="size-4" aria-hidden="true" />
                            <div>
                                <span>{control.label}</span>
                                <strong>{control.value}</strong>
                                <p>{control.detail}</p>
                            </div>
                        </article>
                    );
                })}
            </div>
        </section>
    );
}
