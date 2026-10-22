import { CheckCircle2, Info, TriangleAlert } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { ProfitabilityControl } from './profitability-types';

type Props = {
    controls: ProfitabilityControl[];
};

export function ProfitabilityControls({ controls }: Props) {
    return (
        <div className="profit-controls">
            {controls.map((control) => {
                const Icon =
                    control.status === 'ok'
                        ? CheckCircle2
                        : control.status === 'warning'
                          ? TriangleAlert
                          : Info;

                return (
                    <article
                        key={control.key}
                        className={cn(
                            'profit-control',
                            `profit-control-${control.status}`,
                        )}
                    >
                        <Icon className="size-4" aria-hidden="true" />
                        <div>
                            <span>{control.label}</span>
                            <strong>{control.value}</strong>
                            <p>{control.detail}</p>
                        </div>
                    </article>
                );
            })}
        </div>
    );
}
