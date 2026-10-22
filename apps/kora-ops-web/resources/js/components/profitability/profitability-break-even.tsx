import { Info, Maximize2 } from 'lucide-react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type {
    ProfitabilityCard,
    ProfitabilityEntity,
} from './profitability-types';

type Props = {
    cards: ProfitabilityCard[];
    axisLabel: string;
    entity: ProfitabilityEntity | null;
    onOpen: (key: string) => void;
    onFilter: (card: ProfitabilityCard) => void;
};

const statusTones: Record<ProfitabilityCard['breakEvenStatus'], string> = {
    atteint: 'profit-tone-ok',
    sous_seuil: 'profit-tone-risk',
    non_calculable: 'profit-tone-neutral',
};

const FILTERABLE_AXES = ['bus', 'line', 'agency'];
const UUID_PATTERN =
    /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export function ProfitabilityBreakEven({
    cards,
    axisLabel,
    entity,
    onOpen,
    onFilter,
}: Props) {
    const handleClick = (card: ProfitabilityCard) => {
        const filterable =
            FILTERABLE_AXES.includes(card.axisType) &&
            UUID_PATTERN.test(card.key);
        const isActiveFilter =
            entity !== null &&
            entity.type === card.axisType &&
            entity.uuid === card.key;

        if (filterable && !isActiveFilter) {
            onFilter(card);
        } else {
            onOpen(card.key);
        }
    };

    return (
        <section aria-label="Seuils de rentabilité">
            <div className="profit-section-head">
                <div>
                    <span className="section-kicker">
                        Seuils de rentabilité
                    </span>
                    <h2>Top 3 — du plus au moins risqué</h2>
                </div>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <button
                            type="button"
                            className="profit-carousel-btn"
                            aria-label="Comment le seuil est calculé"
                        >
                            <Info className="size-3.5" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent className="max-w-xs">
                        Seuil = coûts fixes ÷ contribution par siège (recette
                        nette − coûts variables, rapportée aux sièges vendus).
                        Ci-dessous, les trois {axisLabel.toLowerCase()} de la
                        période, triés du plus au moins à risque.
                    </TooltipContent>
                </Tooltip>
            </div>
            <div className="profit-break-even-grid">
                {cards.map((card) => {
                    const progress =
                        card.capacity && card.breakEvenSeats !== null
                            ? Math.min(
                                100,
                                (card.breakEvenSeats / card.capacity) * 100,
                            )
                            : 0;
                    const fill =
                        card.fillRate === null
                            ? 0
                            : Math.min(100, card.fillRate);

                    return (
                        <div
                            key={card.key}
                            role="button"
                            tabIndex={0}
                            onClick={() => handleClick(card)}
                            onKeyDown={(event) => {
                                if (
                                    event.key === 'Enter' ||
                                    event.key === ' '
                                ) {
                                    event.preventDefault();
                                    handleClick(card);
                                }
                            }}
                            className={cn(
                                'profit-break-even',
                                statusTones[card.breakEvenStatus],
                                card.breakEvenStatus === 'sous_seuil' &&
                                'profit-break-even-risk',
                            )}
                        >
                            <button
                                type="button"
                                className="profit-card-detail"
                                aria-label={`Détail de ${card.label}`}
                                onClick={(event) => {
                                    event.stopPropagation();
                                    onOpen(card.key);
                                }}
                            >
                                <Maximize2
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                            </button>
                            <span className="section-kicker">
                                Seuil de rentabilité
                            </span>
                            <h3>
                                {card.label}
                                <small>{card.subtitle}</small>
                            </h3>
                            <div className="profit-break-even-values">
                                <strong>
                                    {card.breakEvenSeats === null
                                        ? '—'
                                        : `${card.breakEvenSeats} sièges`}
                                </strong>
                                <em>
                                    {card.fillRate === null
                                        ? '—'
                                        : `${card.fillRate.toLocaleString('fr-FR')} %`}
                                </em>
                            </div>
                            <div
                                className="profit-break-even-bar"
                                aria-hidden="true"
                            >
                                <i style={{ width: `${fill}%` }} />
                                <b style={{ left: `${progress}%` }} />
                            </div>
                            <footer>
                                <span>
                                    {card.seatsSold}
                                    {card.capacity
                                        ? ` / ${card.capacity}`
                                        : ''}{' '}
                                    sièges vendus
                                </span>
                                <small>
                                    {card.breakEvenStatus === 'atteint'
                                        ? 'Seuil dépassé'
                                        : card.breakEvenStatus === 'sous_seuil'
                                            ? 'Sous le seuil'
                                            : 'Contribution insuffisante'}
                                </small>
                            </footer>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
