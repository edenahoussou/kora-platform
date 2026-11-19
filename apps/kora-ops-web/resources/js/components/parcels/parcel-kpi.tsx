import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { KoraTooltip } from '@/components/kora-tooltip';
import { cn } from '@/lib/utils';

export type ParcelKpiTone = 'gold' | 'navy' | 'blue' | 'green';

const toneClass: Record<ParcelKpiTone, string> = {
    gold: 'cash-ledger-card-kpi-gold',
    navy: 'cash-ledger-card-kpi-navy',
    blue: 'cash-ledger-card-kpi-digital',
    green: 'cash-ledger-card-kpi-emerald',
};

type Props = {
    icon: LucideIcon;
    label: string;
    hint: string;
    value: ReactNode;
    tooltip: ReactNode;
    tone?: ParcelKpiTone;
};

/**
 * KPI Colis aligné sur les cash-ledger-card des dashboards Finance/Ops.
 * Le tooltip explique systématiquement le mode de calcul.
 */
export function ParcelKpi({
    icon: Icon,
    label,
    hint,
    value,
    tooltip,
    tone = 'navy',
}: Props) {
    return (
        <KoraTooltip
            side="bottom"
            content={
                <>
                    <span className="block font-black">{label}</span>
                    {tooltip}
                </>
            }
        >
            <button
                type="button"
                className={cn(
                    'cash-ledger-card group w-full text-left transition-[transform,box-shadow] duration-300 hover:-translate-y-1.5 hover:shadow-[0_25px_55px_rgba(0,29,78,0.14)]',
                    toneClass[tone],
                )}
            >
                <div className="cash-ledger-head">
                    <div className="cash-ledger-identity">
                        <span className="cash-ledger-brand">
                            <Icon className="size-4" strokeWidth={1.8} />
                        </span>
                        <div>
                            <span className="cash-ledger-title">{label}</span>
                            <small>{hint}</small>
                        </div>
                    </div>
                </div>
                <div className="cash-ledger-balance mb-0">
                    <strong>{value}</strong>
                </div>
            </button>
        </KoraTooltip>
    );
}
