import {
    Banknote,
    MapPin,
    Maximize2,
    ReceiptText,
    Satellite,
    Scale,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { formatMoney } from '@/components/sale/sale-utils';
import { cn } from '@/lib/utils';
import type {
    ProfitabilityCard,
    ProfitabilityEntity,
} from './profitability-types';

const statusLabels: Record<ProfitabilityCard['breakEvenStatus'], string> = {
    atteint: 'Rentable',
    sous_seuil: 'Sous le seuil',
    non_calculable: 'Non calculable',
};

const statusTones: Record<ProfitabilityCard['breakEvenStatus'], string> = {
    atteint: 'profit-tone-ok',
    sous_seuil: 'profit-tone-risk',
    non_calculable: 'profit-tone-neutral',
};

const FILTERABLE_AXES = ['bus', 'line', 'agency'];
const UUID_PATTERN =
    /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

type Props = {
    card: ProfitabilityCard;
    axisIcon: LucideIcon;
    entity: ProfitabilityEntity | null;
    onOpen: (key: string) => void;
    onFilter: (card: ProfitabilityCard) => void;
};

export function ProfitabilityCardView({
    card,
    axisIcon: AxisIcon,
    entity,
    onOpen,
    onFilter,
}: Props) {
    const risky = card.breakEvenStatus === 'sous_seuil';
    const fill = card.fillRate === null ? 0 : Math.min(100, card.fillRate);
    const threshold =
        card.capacity && card.breakEvenSeats !== null
            ? Math.min(100, (card.breakEvenSeats / card.capacity) * 100)
            : 0;
    const filterable =
        FILTERABLE_AXES.includes(card.axisType) && UUID_PATTERN.test(card.key);
    const isActiveFilter =
        entity !== null &&
        entity.type === card.axisType &&
        entity.uuid === card.key;

    const handleClick = () => {
        if (filterable && !isActiveFilter) {
            onFilter(card);
        } else {
            onOpen(card.key);
        }
    };

    return (
        <div
            role="button"
            tabIndex={0}
            className={cn(
                'profit-card',
                statusTones[card.breakEvenStatus],
                risky && 'profit-card-risk',
                filterable && 'profit-card-filterable',
                isActiveFilter && 'profit-card-active',
            )}
            onClick={handleClick}
            onKeyDown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    handleClick();
                }
            }}
            aria-label={
                filterable
                    ? `Filtrer le rapport sur ${card.label}`
                    : `Détail de ${card.label}`
            }
        >
            <header>
                <div className="profit-card-heading">
                    <span className="profit-card-axis-icon" aria-hidden="true">
                        <AxisIcon className="size-3.5" />
                    </span>
                    <div>
                        <span className="section-kicker">{card.subtitle}</span>
                        <h3>{card.label}</h3>
                    </div>
                </div>
                <div className="profit-card-actions">
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
                    <button
                        type="button"
                        className="profit-card-detail"
                        aria-label={`Détail de ${card.label}`}
                        onClick={(event) => {
                            event.stopPropagation();
                            onOpen(card.key);
                        }}
                    >
                        <Maximize2 className="size-3.5" aria-hidden="true" />
                    </button>
                </div>
            </header>
            <dl>
                <div>
                    <dt>
                        <Banknote className="size-3" aria-hidden="true" />
                        Recettes
                    </dt>
                    <dd>{formatMoney(card.revenueNet)}</dd>
                </div>
                <div>
                    <dt>
                        <ReceiptText className="size-3" aria-hidden="true" />
                        Coûts
                    </dt>
                    <dd>{formatMoney(card.costTotal)}</dd>
                </div>
                <div>
                    <dt>
                        <Scale className="size-3" aria-hidden="true" />
                        Contribution
                    </dt>
                    <dd
                        className={cn(
                            card.contribution < 0 && 'text-destructive',
                        )}
                    >
                        {formatMoney(card.contribution)}
                    </dd>
                </div>
            </dl>
            <div className="profit-break-even-bar" aria-hidden="true">
                <i style={{ width: `${fill}%` }} />
                <b style={{ left: `${threshold}%` }} />
            </div>
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
        </div>
    );
}
