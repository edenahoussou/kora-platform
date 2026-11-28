import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Props {
    currentStep: number;
    onStepClick: (step: number) => void;
    maxReachedStep: number;
    isFinalized?: boolean;
}

const STEPS = [
    { number: 1, label: 'Trajet & Car', hint: 'Destination & soute' },
    { number: 2, label: 'Acteurs & Pesée', hint: 'Clients, nature & poids' },
    { number: 3, label: 'Paiement & Étiquette', hint: 'Règlement & reçu' },
];

export function ParcelStepStepper({
    currentStep,
    onStepClick,
    maxReachedStep,
    isFinalized = false,
}: Props) {
    const activeLabel = isFinalized
        ? 'Étiquette & Reçu'
        : (STEPS[currentStep - 1]?.label ?? '');

    return (
        <section className="sale-progress no-print">
            <div className="progress-copy">
                <span>Guichet Colis</span>
                <strong>
                    Étape {currentStep} sur 3 · {activeLabel}
                </strong>
            </div>

            <div className="steps gap-2">
                {STEPS.map((step, index) => {
                    const done =
                        step.number < currentStep ||
                        (isFinalized && step.number === 3);
                    const current = step.number === currentStep && !isFinalized;
                    const clickable =
                        !isFinalized && step.number <= maxReachedStep;

                    return (
                        <div key={step.number} className="step-wrap">
                            <button
                                type="button"
                                disabled={!clickable}
                                onClick={() => onStepClick(step.number)}
                                aria-current={current ? 'step' : undefined}
                                className={cn(
                                    'flex items-center gap-2.5 rounded-full border px-3 py-1.5 text-left transition-all',
                                    clickable
                                        ? 'cursor-pointer'
                                        : 'cursor-default',
                                    current &&
                                        'border-gold bg-kora text-white shadow-md ring-2 ring-gold/35',
                                    done &&
                                        'border-emerald-500/35 bg-emerald-500/10 text-emerald-800 hover:bg-emerald-500/15 dark:text-emerald-300',
                                    !current &&
                                        !done &&
                                        'border-transparent bg-transparent text-muted-foreground opacity-75',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-black transition-all',
                                        current &&
                                            'bg-gold text-kora shadow-xs',
                                        done && 'bg-emerald-600 text-white',
                                        !current &&
                                            !done &&
                                            'border border-border bg-muted text-muted-foreground',
                                    )}
                                >
                                    {done ? (
                                        <Check className="size-3.5 stroke-[2.75]" />
                                    ) : (
                                        step.number
                                    )}
                                </span>

                                <span className="min-w-0 leading-tight">
                                    <span
                                        className={cn(
                                            'block truncate text-[11px] font-extrabold tracking-tight',
                                            current
                                                ? 'text-white'
                                                : done
                                                  ? 'text-emerald-800 dark:text-emerald-300'
                                                  : 'text-muted-foreground',
                                        )}
                                    >
                                        {step.label}
                                    </span>
                                    <span
                                        className={cn(
                                            'hidden text-[9px] font-medium xl:block',
                                            current
                                                ? 'text-gold/90'
                                                : 'text-muted-foreground/80',
                                        )}
                                    >
                                        {current
                                            ? 'En cours'
                                            : done
                                              ? 'Complété'
                                              : step.hint}
                                    </span>
                                </span>
                            </button>

                            {index < STEPS.length - 1 && (
                                <span
                                    className={cn(
                                        'h-0.5 min-w-4 flex-1 rounded-full transition-colors',
                                        done
                                            ? 'bg-emerald-500/60'
                                            : current
                                              ? 'bg-gold/50'
                                              : 'bg-border',
                                    )}
                                />
                            )}
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
