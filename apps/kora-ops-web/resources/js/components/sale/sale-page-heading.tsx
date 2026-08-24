import type { ReactNode } from 'react';

export function SalePageHeading({ action }: { action?: ReactNode }) {
    return (
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div className="mb-2 text-[0.6875rem] font-semibold text-muted-foreground">
                    KORA OPS / Vente guichet
                </div>
                <h1 className="max-w-6xl text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                    Vente au guichet
                </h1>
                <p className="mt-1.5 max-w-3xl text-[0.8125rem] leading-6 text-muted-foreground">
                    Recherchez un départ, choisissez les places et bloquez-les
                    temporairement avant l’encaissement.
                </p>
            </div>
            {action}
        </div>
    );
}
