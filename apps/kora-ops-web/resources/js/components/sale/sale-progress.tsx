import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { SaleStage } from './sale-types';

const steps = [
    { number: 1, label: 'Trajet' },
    { number: 2, label: 'Places' },
    { number: 3, label: 'Payeur' },
    { number: 4, label: 'Voyageurs' },
    { number: 5, label: 'Paiement' },
    { number: 6, label: 'Billets' },
];

export function SaleProgress({ stage }: { stage: SaleStage }) {
    const activeStep =
        stage === 'search'
            ? 1
            : stage === 'seats'
              ? 2
              : stage === 'payer'
                ? 3
                : stage === 'passengers'
                  ? 4
                  : stage === 'payment'
                    ? 5
                    : 6;
    const activeLabel =
        stage === 'passengers'
            ? 'Informations voyageurs'
            : steps[activeStep - 1].label;

    return (
        <section className="sale-progress">
            <div className="progress-copy">
                <span>Vente guichet</span>
                <strong>
                    Étape {activeStep} sur 6 · {activeLabel}
                </strong>
            </div>
            <div className="steps">
                {steps.map((step, index) => {
                    const done = step.number < activeStep;
                    const current = step.number === activeStep;

                    return (
                        <div key={step.label} className="step-wrap">
                            <div
                                className={cn(
                                    'step',
                                    (current || done) && 'text-foreground',
                                )}
                            >
                                <span
                                    className={cn(
                                        'step-number',
                                        (current || done) &&
                                            'border-gold bg-gold text-[var(--kora)]',
                                    )}
                                >
                                    {done ? (
                                        <Check className="size-3.5" />
                                    ) : (
                                        step.number
                                    )}
                                </span>
                                <span className="step-label">{step.label}</span>
                            </div>
                            {index < steps.length - 1 && (
                                <span className="step-line" />
                            )}
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
