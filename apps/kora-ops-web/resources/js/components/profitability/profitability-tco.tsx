import { MapPin, Satellite } from 'lucide-react';
import { formatMoney } from '@/components/sale/sale-utils';
import { cn } from '@/lib/utils';
import type { ProfitabilityTcoRow } from './profitability-types';

export function ProfitabilityTco({ rows }: { rows: ProfitabilityTcoRow[] }) {
    return (
        <section className="profit-panel">
            <div className="profit-panel-heading">
                <span className="section-kicker">TCO flotte</span>
                <h2>TCO opérationnel par bus</h2>
                <p>
                    Dépenses affectées au bus sur la période. Le badge de
                    distance indique si les kilomètres viennent du GPS ou de la
                    distance planifiée.
                </p>
            </div>
            {rows.length === 0 ? (
                <p className="profit-table-empty">
                    Aucun bus avec un coût affecté sur la période.
                </p>
            ) : (
                <div className="profit-table-scroll">
                    <table className="profit-table">
                        <thead>
                            <tr>
                                <th>Bus</th>
                                <th className="text-right">TCO</th>
                                <th className="text-right">Km</th>
                                <th className="text-right">Coût/km</th>
                                <th className="text-right">Recette/place</th>
                                <th className="text-right">Résultat/place</th>
                                <th className="text-right">GPS</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.key}>
                                    <th>
                                        <span className="profit-bus-cell">
                                            {row.label}
                                            <span
                                                className={cn(
                                                    'profit-distance',
                                                    row.distanceSource === 'gps'
                                                        ? 'profit-distance-gps'
                                                        : 'profit-distance-estimated',
                                                )}
                                            >
                                                {row.distanceSource ===
                                                'gps' ? (
                                                    <Satellite
                                                        className="size-3"
                                                        aria-hidden="true"
                                                    />
                                                ) : (
                                                    <MapPin
                                                        className="size-3"
                                                        aria-hidden="true"
                                                    />
                                                )}
                                                {row.distanceSource === 'gps'
                                                    ? 'GPS'
                                                    : row.distanceSource ===
                                                        'mixed'
                                                      ? 'Mixte'
                                                      : 'Estimé'}
                                            </span>
                                        </span>
                                    </th>
                                    <td className="text-right tabular-nums">
                                        {formatMoney(row.tco)}
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {row.km.toLocaleString('fr-FR')}
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {row.costPerKm === null
                                            ? '—'
                                            : `${row.costPerKm.toLocaleString('fr-FR')} FCFA`}
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {row.revenuePerSeat === null
                                            ? '—'
                                            : formatMoney(row.revenuePerSeat)}
                                    </td>
                                    <td
                                        className={cn(
                                            'text-right tabular-nums',
                                            row.resultPerSeat !== null &&
                                                row.resultPerSeat < 0 &&
                                                'text-destructive',
                                        )}
                                    >
                                        {row.resultPerSeat === null
                                            ? '—'
                                            : formatMoney(row.resultPerSeat)}
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {row.gpsCoverage.toLocaleString(
                                            'fr-FR',
                                        )}{' '}
                                        %
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}
