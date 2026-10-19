import { Banknote, Bus, Gauge, Percent, ReceiptText } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { formatMoney } from '@/components/sale/sale-utils';
import { cn } from '@/lib/utils';

type Props = {
    revenueNet: number;
    costAssigned: number;
    costUnassigned: number;
    coveragePercent: number;
    tco: number;
    costPerKm: number | null;
};

function Kpi({
    icon: Icon,
    label,
    value,
    detail,
    accent,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
    detail: string;
    accent: 'navy' | 'gold' | 'blue' | 'muted';
}) {
    const accents = {
        navy: 'bg-kora text-white',
        gold: 'bg-gold/15 text-amber-700 dark:text-gold',
        blue: 'bg-digital/10 text-digital',
        muted: 'bg-muted text-muted-foreground',
    };

    return (
        <article className="profit-kpi">
            <span className={cn('profit-kpi-icon', accents[accent])}>
                <Icon className="size-4" strokeWidth={1.8} />
            </span>
            <p className="profit-kpi-label">{label}</p>
            <strong className="profit-kpi-value">{value}</strong>
            <p className="profit-kpi-detail">{detail}</p>
        </article>
    );
}

export function ProfitabilityKpis({
    revenueNet,
    costAssigned,
    costUnassigned,
    coveragePercent,
    tco,
    costPerKm,
}: Props) {
    return (
        <section className="profit-kpis">
            <Kpi
                icon={Banknote}
                label="Recettes nettes"
                value={formatMoney(revenueNet)}
                detail="Paiements confirmés rattachés aux départs, annulations déduites."
                accent="navy"
            />
            <Kpi
                icon={ReceiptText}
                label="Coûts affectés"
                value={formatMoney(costAssigned)}
                detail="Dépenses approuvées rattachées à un départ, un bus ou une ligne."
                accent="gold"
            />
            <Kpi
                icon={Percent}
                label="Coûts non affectés"
                value={formatMoney(costUnassigned)}
                detail="Dépenses sans centre de coût, visibles au niveau réseau."
                accent={costUnassigned > 0 ? 'muted' : 'blue'}
            />
            <Kpi
                icon={Gauge}
                label="Taux de couverture"
                value={`${coveragePercent.toLocaleString('fr-FR')} %`}
                detail="Part des coûts effectivement affectée aux axes."
                accent="blue"
            />
            <Kpi
                icon={Bus}
                label="TCO opérationnel"
                value={formatMoney(tco)}
                detail="Hors acquisition, amortissement, financement et paie."
                accent="navy"
            />
            <Kpi
                icon={Gauge}
                label="Coût/km moyen"
                value={
                    costPerKm === null
                        ? '—'
                        : `${costPerKm.toLocaleString('fr-FR')} FCFA`
                }
                detail="TCO divisé par les kilomètres GPS ou estimés."
                accent="gold"
            />
        </section>
    );
}
