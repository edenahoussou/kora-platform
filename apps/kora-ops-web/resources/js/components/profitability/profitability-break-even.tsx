import { cn } from '@/lib/utils';
import type { ProfitabilityCard } from './profitability-types';

type Props = {
    cards: ProfitabilityCard[];
    onOpen: (key: string) => void;
};

export function ProfitabilityBreakEven({ cards, onOpen }: Props) {
    return (
        <section className="profit-break-even-grid">
            {cards.map((card) => {
                const progress =
                    card.capacity && card.breakEvenSeats !== null
                        ? Math.min(
                              100,
                              (card.breakEvenSeats / card.capacity) * 100,
                          )
                        : 0;
                const fill =
                    card.fillRate === null ? 0 : Math.min(100, card.fillRate);

                return (
                    <button
                        key={card.key}
                        type="button"
                        onClick={() => onOpen(card.key)}
                        className={cn(
                            'profit-break-even',
                            card.breakEvenStatus === 'sous_seuil' &&
                                'profit-break-even-risk',
                        )}
                    >
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
                    </button>
                );
            })}
        </section>
    );
}
