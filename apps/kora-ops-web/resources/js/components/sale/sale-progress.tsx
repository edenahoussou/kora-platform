import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { SaleStage } from './sale-types';

const steps = [
    { number: 1, label: 'Trajet' },
    { number: 2, label: 'Places' },
    { number: 3, label: 'Passagers' },
    { number: 4, label: 'Paiement' },
];

export function SaleProgress({ stage }: { stage: SaleStage }) {
    const activeStep = stage === 'search' ? 1 : 2;

    return (
        <section className="mb-5 flex flex-col gap-5 rounded-[1rem] border border-border bg-card px-5 py-4 shadow-[0_10px_28px_rgba(0,29,78,0.045)] md:flex-row md:items-center">
            <div className="min-w-[12rem]">
                <span className="block text-[0.625rem] font-black tracking-[0.13em] text-digital uppercase">
                    Vente guichet
                </span>
                <strong className="mt-1 block text-[0.75rem] font-extrabold text-foreground">
                    {stage === 'held'
                        ? 'Places bloquées'
                        : `Étape ${activeStep} sur 4`}
                </strong>
            </div>
            <div className="flex min-w-0 flex-1 items-center gap-2">
                {steps.map((step, index) => {
                    const done = step.number < activeStep;
                    const current = step.number === activeStep;

                    return (
                        <div
                            key={step.label}
                            className="flex min-w-0 flex-1 items-center gap-2"
                        >
                            <div
                                className={cn(
                                    'flex min-w-0 items-center gap-1.5 text-[0.625rem] font-extrabold text-muted-foreground',
                                    (current || done) && 'text-foreground',
                                )}
                            >
                                <span
                                    className={cn(
                                        'grid size-7 shrink-0 place-items-center rounded-full border border-border bg-background text-[0.6875rem]',
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
                                <span className="hidden truncate sm:inline">
                                    {step.label}
                                </span>
                            </div>
                            {index < steps.length - 1 && (
                                <span className="h-px min-w-3 flex-1 bg-border" />
                            )}
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
