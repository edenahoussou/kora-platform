import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-react';
import { formatMoney } from '@/components/sale/sale-utils';
import { cn } from '@/lib/utils';
import type { ProfitabilityPnlRow } from './profitability-types';

const statusLabels: Record<ProfitabilityPnlRow['status'], string> = {
    available: 'Disponible',
    pending: 'En suspens',
    out_of_scope: 'Hors périmètre V1',
};

function Evolution({ value }: { value: number | null }) {
    if (value === null) {
        return <span className="profit-pnl-evolution">—</span>;
    }

    const Icon = value > 0 ? ArrowUpRight : value < 0 ? ArrowDownRight : Minus;

    return (
        <span
            className={cn(
                'profit-pnl-evolution',
                value > 0 && 'profit-pnl-up',
                value < 0 && 'profit-pnl-down',
            )}
        >
            <Icon className="size-3" aria-hidden="true" />
            {value > 0 ? '+' : ''}
            {value.toLocaleString('fr-FR')} %
        </span>
    );
}

export function ProfitabilityPnl({ rows }: { rows: ProfitabilityPnlRow[] }) {
    return (
        <section className="profit-panel">
            <div className="profit-panel-heading">
                <span className="section-kicker">Structure P&amp;L</span>
                <h2>Décomposition du résultat</h2>
                <p>
                    Les postes sans source sont marqués « en suspens » ou « hors
                    périmètre V1 », jamais affichés à zéro.
                </p>
            </div>
            <div className="profit-table-scroll">
                <table className="profit-table">
                    <thead>
                        <tr>
                            <th>Poste</th>
                            <th>Source</th>
                            <th className="text-right">Montant</th>
                            <th className="text-right">Part</th>
                            <th className="text-right">Évolution</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr
                                key={row.key}
                                className={cn(
                                    row.key === 'result' && 'profit-pnl-result',
                                )}
                            >
                                <th>{row.label}</th>
                                <td>{row.source}</td>
                                <td className="text-right tabular-nums">
                                    {row.amount === null
                                        ? '—'
                                        : formatMoney(row.amount)}
                                </td>
                                <td className="text-right tabular-nums">
                                    {row.sharePercent === null
                                        ? '—'
                                        : `${row.sharePercent.toLocaleString('fr-FR')} %`}
                                </td>
                                <td className="text-right">
                                    <Evolution value={row.evolutionPercent} />
                                </td>
                                <td>
                                    <span
                                        className={cn(
                                            'profit-source',
                                            row.status === 'pending' &&
                                                'profit-source-pending',
                                            row.status === 'out_of_scope' &&
                                                'profit-source-out',
                                        )}
                                    >
                                        {statusLabels[row.status]}
                                    </span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}
