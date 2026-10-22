import { cn } from '@/lib/utils';
import { profitabilityAxisIcon } from './profitability-axis-icons';

type Props = {
    axis: string;
    axisOptions: Record<string, string>;
    onChange: (axis: string) => void;
};

export function ProfitabilityAxisTabs({ axis, axisOptions, onChange }: Props) {
    return (
        <nav className="profit-axis-tabs" aria-label="Axe de rentabilité">
            {Object.entries(axisOptions).map(([value, label]) => {
                const Icon = profitabilityAxisIcon(value);

                return (
                    <button
                        key={value}
                        type="button"
                        onClick={() => onChange(value)}
                        className={cn(value === axis && 'active')}
                        aria-pressed={value === axis}
                    >
                        <Icon className="size-3.5" aria-hidden="true" />
                        {label}
                    </button>
                );
            })}
        </nav>
    );
}
