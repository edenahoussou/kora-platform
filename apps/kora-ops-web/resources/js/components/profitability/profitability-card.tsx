import { MapPin, Satellite } from 'lucide-react';
import { formatMoney } from '@/components/sale/sale-utils';
import { cn } from '@/lib/utils';
import type { ProfitabilityCard } from './profitability-types';

const statusLabels: Record<ProfitabilityCard['breakEvenStatus'], string> = {
    atteint: 'Rentable',
    sous_seuil: 'Sous le seuil',
    non_calculable: 'Non calculable',
};

type Props = {
    card: ProfitabilityCard;
    onOpen: (key: string) => void;
};

export function ProfitabilityCardView({ card, onOpen }: Props) {
    const risky = card.breakEvenStatus === 'sous_seuil';

    return (
        <button
            type="button"
            className={cn('profit-card', risky && 'profit-card-risk')}
            onClick={() => onOpen(card.key)}
        >
            <header>
                <div>
                    <span className="section-kicker">{card.subtitle}</span>
                    <h3>{card.label}</h3>
                </div>
                <span
                    className={cn(
                        'profit-status',
                        card.breakEvenStatus === 'atteint' &&
                            'profit-status-ok',
                        risky && 'profit-status-risk',
                    )}
                >
                    {statusLabels[card.breakEvenStatus]}
                </span>
            </header>
            <dl>
                <div>
                    <dt>Recettes</dt>
                    <dd>{formatMoney(card.revenueNet)}</dd>
                </div>
                <div>
                    <dt>Coûts</dt>
                    <dd>{formatMoney(card.costTotal)}</dd>
                </div>
                <div>
                    <dt>Contribution</dt>
                    <dd
                        className={cn(
                            card.contribution < 0 && 'text-destructive',
                        )}
                    >
                        {formatMoney(card.contribution)}
                    </dd>
                </div>
            </dl>
            <footer>
                <span>
                    {card.marginPercent === null
                        ? 'Marge —'
                        : `${card.marginPercent.toLocaleString('fr-FR')} % de marge`}
                </span>
                <span className="profit-card-meta">
                    {card.breakEvenSeats === null
                        ? 'Seuil —'
                        : `${card.breakEvenSeats} sièges seuil`}
                    {' · '}
                    {card.seatsSold}
                    {card.capacity ? `/${card.capacity}` : ''} vendus
                </span>
            </footer>
            <div className="profit-card-footnote">
                <span>
                    {card.costPerKm === null
                        ? 'Coût/km —'
                        : `${card.costPerKm.toLocaleString('fr-FR')} FCFA/km`}
                </span>
                <span
                    className={cn(
                        'profit-distance',
                        card.distanceSource === 'gps'
                            ? 'profit-distance-gps'
                            : 'profit-distance-estimated',
                    )}
                >
                    {card.distanceSource === 'gps' ? (
                        <Satellite className="size-3" aria-hidden="true" />
                    ) : (
                        <MapPin className="size-3" aria-hidden="true" />
                    )}
                    {card.distanceSource === 'gps'
                        ? 'GPS'
                        : card.distanceSource === 'mixed'
                          ? 'Mixte'
                          : 'Estimé'}
                </span>
            </div>
        </button>
    );
}
