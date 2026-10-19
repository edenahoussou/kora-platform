import { ChartColumn } from 'lucide-react';

type Props = {
    axisLabel: string;
};

export function ProfitabilityEmpty({ axisLabel }: Props) {
    return (
        <section className="profit-empty">
            <span className="profit-empty-icon">
                <ChartColumn className="size-6" strokeWidth={1.6} />
            </span>
            <h2>Aucune donnée de rentabilité sur cette période</h2>
            <p>
                Aucun départ, paiement rattaché ni dépense approuvée sur l’axe{' '}
                {axisLabel.toLowerCase()}. Élargissez la période ou vérifiez que
                les dépenses portent un centre de coût.
            </p>
        </section>
    );
}
