import {
    ChevronLeft,
    ChevronRight,
    GalleryHorizontal,
    Info,
    LayoutGrid,
    Pause,
    Play,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { ProfitabilityCardView } from './profitability-card';
import type {
    ProfitabilityCard,
    ProfitabilityEntity,
} from './profitability-types';

const AUTOPLAY_MS = 30_000;

type Props = {
    cards: ProfitabilityCard[];
    axisLabel: string;
    axisIcon: LucideIcon;
    suspended: boolean;
    entity: ProfitabilityEntity | null;
    onOpen: (key: string) => void;
    onFilter: (card: ProfitabilityCard) => void;
};

export function ProfitabilityCarousel({
    cards,
    axisLabel,
    axisIcon,
    suspended,
    entity,
    onOpen,
    onFilter,
}: Props) {
    const trackRef = useRef<HTMLDivElement | null>(null);
    const hoverRef = useRef(false);
    const focusRef = useRef(false);
    const dragRef = useRef<{
        startX: number;
        startLeft: number;
        moved: boolean;
    } | null>(null);
    const suppressClickRef = useRef(false);
    const [index, setIndex] = useState(0);
    const [canScroll, setCanScroll] = useState(false);
    const [autoplayOn, setAutoplayOn] = useState(true);
    const [showAll, setShowAll] = useState(false);
    const [dragging, setDragging] = useState(false);

    const step = useCallback((): number => {
        const track = trackRef.current;

        if (track === null || track.children.length === 0) {
            return 0;
        }

        const first = track.children[0] as HTMLElement;

        if (track.children.length > 1) {
            const second = track.children[1] as HTMLElement;

            return second.offsetLeft - first.offsetLeft;
        }

        return first.offsetWidth;
    }, []);

    const syncState = useCallback(() => {
        const track = trackRef.current;

        if (track === null) {
            return;
        }

        setCanScroll(track.scrollWidth > track.clientWidth + 1);
        const unit = step();

        if (unit > 0) {
            setIndex(
                Math.min(cards.length - 1, Math.round(track.scrollLeft / unit)),
            );
        }
    }, [cards.length, step]);

    useEffect(() => {
        syncState();
        const track = trackRef.current;

        if (track === null) {
            return;
        }

        const observer = new ResizeObserver(syncState);
        observer.observe(track);

        return () => observer.disconnect();
    }, [syncState, showAll]);

    useEffect(() => {
        const timer = window.setInterval(() => {
            const track = trackRef.current;

            if (
                track === null ||
                showAll ||
                !autoplayOn ||
                suspended ||
                hoverRef.current ||
                focusRef.current ||
                dragging ||
                document.hidden ||
                window.matchMedia('(prefers-reduced-motion: reduce)').matches
            ) {
                return;
            }

            const unit = step();

            if (unit <= 0) {
                return;
            }

            if (track.scrollLeft + track.clientWidth >= track.scrollWidth - 4) {
                track.scrollTo({ left: 0, behavior: 'smooth' });
            } else {
                track.scrollBy({ left: unit, behavior: 'smooth' });
            }
        }, AUTOPLAY_MS);

        return () => window.clearInterval(timer);
    }, [autoplayOn, showAll, suspended, step, dragging]);

    const slide = (direction: 1 | -1) => {
        const track = trackRef.current;

        if (track === null) {
            return;
        }

        setAutoplayOn(false);
        const unit = step();

        if (unit <= 0) {
            return;
        }

        if (direction === 1) {
            if (track.scrollLeft + track.clientWidth >= track.scrollWidth - 4) {
                track.scrollTo({ left: 0, behavior: 'smooth' });
            } else {
                track.scrollBy({ left: unit, behavior: 'smooth' });
            }
        } else if (track.scrollLeft <= 4) {
            track.scrollTo({ left: track.scrollWidth, behavior: 'smooth' });
        } else {
            track.scrollBy({ left: -unit, behavior: 'smooth' });
        }
    };

    const onDragStart = (event: React.PointerEvent<HTMLDivElement>) => {
        if (event.pointerType !== 'mouse' || event.button !== 0) {
            return;
        }

        const track = trackRef.current;

        if (track === null) {
            return;
        }

        dragRef.current = {
            startX: event.clientX,
            startLeft: track.scrollLeft,
            moved: false,
        };
        track.setPointerCapture(event.pointerId);
    };

    const onDragMove = (event: React.PointerEvent<HTMLDivElement>) => {
        const drag = dragRef.current;
        const track = trackRef.current;

        if (drag === null || track === null) {
            return;
        }

        const delta = event.clientX - drag.startX;

        if (!drag.moved) {
            if (Math.abs(delta) < 6) {
                return;
            }

            drag.moved = true;
            setDragging(true);
        }

        track.scrollLeft = drag.startLeft - delta;
    };

    const onDragEnd = () => {
        const drag = dragRef.current;
        const track = trackRef.current;

        dragRef.current = null;

        if (drag === null || track === null || !drag.moved) {
            return;
        }

        suppressClickRef.current = true;
        window.setTimeout(() => {
            suppressClickRef.current = false;
        }, 200);
        setDragging(false);
        setAutoplayOn(false);

        const unit = step();

        if (unit > 0) {
            track.scrollTo({
                left: Math.round(track.scrollLeft / unit) * unit,
                behavior: 'smooth',
            });
        }
    };

    return (
        <section
            className="profit-carousel"
            aria-label="Contributions détaillées"
        >
            <div className="profit-section-head">
                <div>
                    <span className="section-kicker">
                        Contribution détaillée
                    </span>
                    <h2>Par {axisLabel.toLowerCase()}</h2>
                </div>
                <div className="profit-carousel-controls">
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <button
                                type="button"
                                className="profit-carousel-btn"
                                aria-label="Comment la contribution est calculée"
                            >
                                <Info className="size-3.5" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent className="max-w-xs">
                            Contribution = recette nette − coûts totaux (directs
                            + répartis). La recette nette regroupe les paiements
                            confirmés de la période, ventilés par départ et
                            diminués des annulations reconnues ; les coûts sont
                            les dépenses approuvées, affectées directement ou
                            réparties selon leur règle. Cartes triées du plus au
                            moins à risque. Cliquez une carte bus, ligne ou
                            agence pour filtrer tout le rapport sur cette entité ;
                            l’icône en haut à droite ouvre le détail.
                        </TooltipContent>
                    </Tooltip>
                    {!showAll && canScroll && (
                        <>
                            <button
                                type="button"
                                className="profit-carousel-btn"
                                onClick={() => slide(-1)}
                                aria-label="Carte précédente"
                            >
                                <ChevronLeft className="size-4" />
                            </button>
                            <span className="profit-carousel-counter">
                                {index + 1} / {cards.length}
                            </span>
                            <button
                                type="button"
                                className="profit-carousel-btn"
                                onClick={() => slide(1)}
                                aria-label="Carte suivante"
                            >
                                <ChevronRight className="size-4" />
                            </button>
                            <button
                                type="button"
                                className="profit-carousel-btn"
                                onClick={() => setAutoplayOn((on) => !on)}
                                aria-label={
                                    autoplayOn
                                        ? 'Mettre le défilement en pause'
                                        : 'Reprendre le défilement automatique'
                                }
                                aria-pressed={autoplayOn}
                            >
                                {autoplayOn ? (
                                    <Pause className="size-3.5" />
                                ) : (
                                    <Play className="size-3.5" />
                                )}
                            </button>
                        </>
                    )}
                    <button
                        type="button"
                        className={cn(
                            'profit-carousel-btn profit-carousel-toggle',
                            showAll && 'active',
                        )}
                        onClick={() => setShowAll((all) => !all)}
                        aria-pressed={showAll}
                    >
                        {showAll ? (
                            <GalleryHorizontal
                                className="size-3.5"
                                aria-hidden="true"
                            />
                        ) : (
                            <LayoutGrid
                                className="size-3.5"
                                aria-hidden="true"
                            />
                        )}
                        {showAll ? 'Vue carrousel' : 'Tout afficher'}
                    </button>
                </div>
            </div>
            {showAll ? (
                <div className="profit-grid">
                    {cards.map((card) => (
                        <ProfitabilityCardView
                            key={card.key}
                            card={card}
                            axisIcon={axisIcon}
                            entity={entity}
                            onOpen={onOpen}
                            onFilter={onFilter}
                        />
                    ))}
                </div>
            ) : (
                <div
                    ref={trackRef}
                    className={cn(
                        'profit-carousel-track',
                        dragging && 'profit-carousel-dragging',
                    )}
                    role="region"
                    aria-roledescription="carrousel"
                    aria-label={`Contributions par ${axisLabel.toLowerCase()}`}
                    onScroll={syncState}
                    onPointerDown={onDragStart}
                    onPointerMove={onDragMove}
                    onPointerUp={onDragEnd}
                    onPointerCancel={onDragEnd}
                    onLostPointerCapture={onDragEnd}
                    onClickCapture={(event) => {
                        if (suppressClickRef.current) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                    }}
                    onMouseEnter={() => {
                        hoverRef.current = true;
                    }}
                    onMouseLeave={() => {
                        hoverRef.current = false;
                    }}
                    onFocusCapture={() => {
                        focusRef.current = true;
                    }}
                    onBlurCapture={(event) => {
                        if (
                            !(event.currentTarget as HTMLElement).contains(
                                event.relatedTarget as Node | null,
                            )
                        ) {
                            focusRef.current = false;
                        }
                    }}
                >
                    {cards.map((card) => (
                        <div key={card.key} className="profit-carousel-item">
                            <ProfitabilityCardView
                                card={card}
                                axisIcon={axisIcon}
                                entity={entity}
                                onOpen={onOpen}
                                onFilter={onFilter}
                            />
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}
