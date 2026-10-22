import {
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    Minus,
} from 'lucide-react';
import { formatMoney } from '@/components/sale/sale-utils';
import { cn } from '@/lib/utils';
import type { ProfitabilityBreakEven } from './profitability-types';

type Props = {
    revenueNet: number;
    result: number;
    marginPercent: number | null;
    costUnassigned: number;
    networkUnassigned: number;
    filtered: boolean;
    coveragePercent: number;
    breakEven: ProfitabilityBreakEven;
};

const statusLabels: Record<ProfitabilityBreakEven['status'], string> = {
    atteint: 'Seuil réseau atteint',
    sous_seuil: 'Sous le seuil réseau',
    non_calculable: 'Seuil non calculable',
};

export function ProfitabilityHero({
    revenueNet,
    result,
    marginPercent,
    costUnassigned,
    networkUnassigned,
    filtered,
    coveragePercent,
    breakEven,
}: Props) {
    const positive = result >= 0;
    const Icon = !positive
        ? ArrowDownRight
        : marginPercent !== null && marginPercent > 0
            ? ArrowUpRight
            : Minus;

    return (
        <section className="profit-hero">
            <div className="profit-hero-copy">
                <span className="section-kicker">Rentabilité consolidée</span>
                <h2>
                    Comprendre la contribution
                    <br />
                    <em>de chaque opération.</em>
                </h2>
                <p>
                    Recettes rattachées aux départs de la période, rapprochées
                    des dépenses opérationnelles structurées par centre de coût.
                    Aucun salaire, colis ou commission KORA POINT n’est simulé.
                </p>
                <div className="profit-hero-total">
                    <small>Résultat opérationnel réseau</small>
                    <strong>{formatMoney(result)}</strong>
                    <span
                        className={cn(
                            'profit-hero-margin',
                            !positive && 'profit-hero-margin-negative',
                        )}
                    >
                        <Icon className="size-3.5" aria-hidden="true" />
                        Marge{' '}
                        {marginPercent === null
                            ? 'non calculable'
                            : `${marginPercent.toLocaleString('fr-FR')} %`}{' '}
                        · {statusLabels[breakEven.status]}
                    </span>
                </div>
            </div>
            <div className="profit-hero-summary">
                <div className="profit-hero-summary-head">
                    <span>Lecture réseau</span>
                    <span className="profit-hero-badge">
                        {coveragePercent.toLocaleString('fr-FR')} % couvert
                    </span>
                </div>
                <dl>
                    <div>
                        <dt>Recettes nettes de service</dt>
                        <dd>{formatMoney(revenueNet)}</dd>
                    </div>
                    <div>
                        <dt>Coûts non affectés</dt>
                        <dd>{formatMoney(costUnassigned)}</dd>
                    </div>
                    <div>
                        <dt>Sièges seuil</dt>
                        <dd>
                            {breakEven.breakEvenSeats === null
                                ? '—'
                                : `${breakEven.breakEvenSeats} sièges`}
                        </dd>
                    </div>
                    <div>
                        <dt>Sièges vendus</dt>
                        <dd>
                            {breakEven.seatsSold}
                            {breakEven.capacity
                                ? ` / ${breakEven.capacity}`
                                : ''}
                        </dd>
                    </div>
                </dl>
                {costUnassigned > 0 && (
                    <p className="profit-hero-warning">
                        <AlertTriangle
                            className="size-3.5"
                            aria-hidden="true"
                        />
                        {filtered
                            ? 'Ces coûts entrent dans le total de la vue sans rattachement à un axe opérationnel.'
                            : 'Les coûts non affectés restent visibles au niveau réseau : ils ne sont jamais poussés dans la marge d’un axe.'}
                    </p>
                )}
                {filtered && networkUnassigned > 0 && (
                    <p className="profit-hero-warning">
                        <AlertTriangle
                            className="size-3.5"
                            aria-hidden="true"
                        />
                        {formatMoney(networkUnassigned)} de coûts réseau sans
                        rattachement restent hors de cette vue filtrée.
                    </p>
                )}
            </div>
        </section>
    );
}
