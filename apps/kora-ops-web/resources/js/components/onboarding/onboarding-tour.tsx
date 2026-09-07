import { ChevronLeft, ChevronRight, X } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/lib/utils';

export type OnboardingStep = {
    /** Selector for the target element to highlight. */
    target: string;
    /** Title shown in the tooltip. */
    title: string;
    /** Body text shown in the tooltip. */
    body: string;
    /** Optional side preference for the tooltip. */
    side?: 'top' | 'bottom' | 'left' | 'right';
};

type OnboardingTourProps = {
    steps: OnboardingStep[];
    isActive: boolean;
    onComplete: () => void;
    onDismiss: () => void;
};

type Rect = {
    top: number;
    left: number;
    width: number;
    height: number;
    bottom: number;
    right: number;
};

type TooltipPosition = {
    top: number;
    left: number;
};

const PADDING = 8;
const TOOLTIP_WIDTH = 360;
const TOOLTIP_OFFSET = 16;
const TOOLTIP_ESTIMATED_HEIGHT = 220;

function getTooltipPosition(
    targetRect: Rect,
    side: OnboardingStep['side'],
): {
    position: TooltipPosition;
    side: 'top' | 'bottom' | 'left' | 'right';
} {
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;

    const allSides: Array<'top' | 'bottom' | 'left' | 'right'> = [
        'bottom',
        'top',
        'right',
        'left',
    ];
    const sides = side
        ? [side, ...allSides.filter((s) => s !== side)]
        : allSides;

    for (const candidate of sides) {
        let top = 0;
        let left = 0;

        switch (candidate) {
            case 'top':
                top =
                    targetRect.top - TOOLTIP_OFFSET - TOOLTIP_ESTIMATED_HEIGHT;
                left =
                    targetRect.left + targetRect.width / 2 - TOOLTIP_WIDTH / 2;
                break;
            case 'bottom':
                top = targetRect.bottom + TOOLTIP_OFFSET;
                left =
                    targetRect.left + targetRect.width / 2 - TOOLTIP_WIDTH / 2;
                break;
            case 'left':
                top = targetRect.top + targetRect.height / 2 - 100;
                left = targetRect.left - TOOLTIP_OFFSET - TOOLTIP_WIDTH;
                break;
            case 'right':
                top = targetRect.top + targetRect.height / 2 - 100;
                left = targetRect.right + TOOLTIP_OFFSET;
                break;
        }

        // Clamp within viewport.
        left = Math.max(
            PADDING,
            Math.min(left, viewportWidth - TOOLTIP_WIDTH - PADDING),
        );
        top = Math.max(
            PADDING,
            Math.min(top, viewportHeight - TOOLTIP_ESTIMATED_HEIGHT - PADDING),
        );

        // Check if this side fits without too much clamping.
        if (
            top >= PADDING &&
            top + TOOLTIP_ESTIMATED_HEIGHT <= viewportHeight - PADDING
        ) {
            return { position: { top, left }, side: candidate };
        }
    }

    // Fallback: bottom.
    return {
        position: {
            top: Math.min(
                targetRect.bottom + TOOLTIP_OFFSET,
                viewportHeight - TOOLTIP_ESTIMATED_HEIGHT - PADDING,
            ),
            left: Math.max(
                PADDING,
                Math.min(
                    targetRect.left + targetRect.width / 2 - TOOLTIP_WIDTH / 2,
                    viewportWidth - TOOLTIP_WIDTH - PADDING,
                ),
            ),
        },
        side: 'bottom',
    };
}

export function OnboardingTour({
    steps,
    isActive,
    onComplete,
    onDismiss,
}: OnboardingTourProps) {
    const [currentStep, setCurrentStep] = useState(0);
    const [targetRect, setTargetRect] = useState<Rect | null>(null);
    const [tooltipPos, setTooltipPos] = useState<TooltipPosition>({
        top: 0,
        left: 0,
    });
    const [tooltipSide, setTooltipSide] = useState<
        'top' | 'bottom' | 'left' | 'right'
    >('bottom');
    const overlayRef = useRef<HTMLDivElement>(null);

    const step = steps[currentStep];
    const isLast = currentStep === steps.length - 1;
    const isFirst = currentStep === 0;

    const next = useCallback(() => {
        setCurrentStep((s) => {
            if (s === steps.length - 1) {
                onComplete();

                return s;
            }

            return s + 1;
        });
    }, [onComplete, steps.length]);

    const prev = useCallback(() => {
        setCurrentStep((s) => Math.max(0, s - 1));
    }, []);

    const updatePosition = useCallback(() => {
        if (!step) {
            return;
        }

        const el = document.querySelector(step.target);

        if (!el) {
            return;
        }

        const rect = el.getBoundingClientRect();
        const rectData: Rect = {
            top: rect.top,
            left: rect.left,
            width: rect.width,
            height: rect.height,
            bottom: rect.bottom,
            right: rect.right,
        };
        setTargetRect(rectData);

        const { position, side } = getTooltipPosition(rectData, step.side);
        setTooltipPos(position);
        setTooltipSide(side);
    }, [step]);

    useLayoutEffect(() => {
        if (isActive) {
            // eslint-disable-next-line react-hooks/set-state-in-effect
            setCurrentStep(0);
        }
    }, [isActive]);

    useEffect(() => {
        if (!isActive) {
            return;
        }

        // eslint-disable-next-line react-hooks/set-state-in-effect
        updatePosition();

        const handleResize = () => updatePosition();
        const handleScroll = () => updatePosition();

        window.addEventListener('resize', handleResize);
        window.addEventListener('scroll', handleScroll, true);

        const interval = setInterval(updatePosition, 200);

        return () => {
            window.removeEventListener('resize', handleResize);
            window.removeEventListener('scroll', handleScroll, true);
            clearInterval(interval);
        };
    }, [isActive, currentStep, updatePosition]);

    useEffect(() => {
        if (!isActive || !step) {
            return;
        }

        const handleKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                onDismiss();
            } else if (e.key === 'ArrowRight') {
                next();
            } else if (e.key === 'ArrowLeft') {
                prev();
            }
        };

        window.addEventListener('keydown', handleKey);

        return () => window.removeEventListener('keydown', handleKey);
    }, [isActive, currentStep, step, next, prev, onDismiss]);

    if (!isActive || !step || !targetRect) {
        return null;
    }

    const highlightBox = {
        top: targetRect.top - PADDING,
        left: targetRect.left - PADDING,
        width: targetRect.width + PADDING * 2,
        height: targetRect.height + PADDING * 2,
    };

    return createPortal(
        <div
            ref={overlayRef}
            className="kora-onboarding-overlay"
            role="dialog"
            aria-modal="true"
            aria-label={step.title}
        >
            {/* Dark overlay with cut-out for the highlighted element */}
            <svg
                className="kora-onboarding-mask"
                style={{
                    position: 'fixed',
                    top: 0,
                    left: 0,
                    width: '100vw',
                    height: '100vh',
                    pointerEvents: 'none',
                }}
            >
                <defs>
                    <mask id="kora-onboarding-hole">
                        <rect width="100%" height="100%" fill="white" />
                        <rect
                            x={highlightBox.left}
                            y={highlightBox.top}
                            width={highlightBox.width}
                            height={highlightBox.height}
                            rx="12"
                            fill="black"
                        />
                    </mask>
                </defs>
                <rect
                    width="100%"
                    height="100%"
                    fill="rgba(0, 29, 78, 0.55)"
                    mask="url(#kora-onboarding-hole)"
                />
            </svg>

            {/* Highlight ring around the target */}
            <div
                className="kora-onboarding-highlight"
                style={{
                    position: 'fixed',
                    top: highlightBox.top,
                    left: highlightBox.left,
                    width: highlightBox.width,
                    height: highlightBox.height,
                }}
            />

            {/* Tooltip card */}
            <div
                key={currentStep}
                className="kora-onboarding-tooltip kora-onboarding-tooltip-enter"
                style={{
                    position: 'fixed',
                    top: tooltipPos.top,
                    left: tooltipPos.left,
                    width: TOOLTIP_WIDTH,
                }}
            >
                {/* Arrow pointing to the target */}
                <div
                    className={cn(
                        'kora-onboarding-arrow',
                        `kora-onboarding-arrow-${tooltipSide}`,
                    )}
                />

                <div className="kora-onboarding-tooltip-header">
                    <span className="kora-onboarding-step-counter">
                        {currentStep + 1} / {steps.length}
                    </span>
                    <button
                        type="button"
                        onClick={onDismiss}
                        className="kora-onboarding-close"
                        aria-label="Fermer le parcours"
                    >
                        <X className="size-3.5" strokeWidth={2} />
                    </button>
                </div>

                <h3 className="kora-onboarding-tooltip-title">{step.title}</h3>
                <p className="kora-onboarding-tooltip-body">{step.body}</p>

                <div className="kora-onboarding-tooltip-footer">
                    {/* Progress dots */}
                    <div className="kora-onboarding-dots">
                        {steps.map((_, i) => (
                            <span
                                key={i}
                                className={cn(
                                    'kora-onboarding-dot',
                                    i === currentStep &&
                                        'kora-onboarding-dot-active',
                                )}
                            />
                        ))}
                    </div>

                    <div className="flex items-center gap-2">
                        {!isFirst && (
                            <button
                                type="button"
                                onClick={prev}
                                className="kora-onboarding-btn-prev"
                            >
                                <ChevronLeft
                                    className="size-3.5"
                                    strokeWidth={2}
                                />
                                Précédent
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={next}
                            className="kora-onboarding-btn-next"
                        >
                            {isLast ? 'Terminer' : 'Suivant'}
                            {!isLast && (
                                <ChevronRight
                                    className="size-3.5"
                                    strokeWidth={2}
                                />
                            )}
                        </button>
                    </div>
                </div>
            </div>
        </div>,
        document.body,
    );
}
