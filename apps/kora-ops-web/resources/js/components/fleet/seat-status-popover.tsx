import { Ban, CheckCircle2, History, Wrench, X } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import type { OperationalSeatStatus } from '@/components/seat-map/types';
import { cn } from '@/lib/utils';

export type SeatStatusChange = {
    status: OperationalSeatStatus;
    reason: string;
    at: string;
};

const STATUS_OPTIONS: {
    value: OperationalSeatStatus;
    label: string;
    icon: React.ComponentType<{ className?: string }>;
    dot: string;
}[] = [
    {
        value: 'active',
        label: 'Opérationnel',
        icon: CheckCircle2,
        dot: 'bg-emerald-500',
    },
    {
        value: 'maintenance',
        label: 'En maintenance',
        icon: Wrench,
        dot: 'bg-amber-500',
    },
    { value: 'blocked', label: 'Bloqué', icon: Ban, dot: 'bg-graphite' },
];

export function SeatStatusPopover({
    seatCode,
    anchor,
    status,
    history,
    onApply,
    onClose,
}: {
    seatCode: string;
    anchor: DOMRect;
    status: OperationalSeatStatus;
    history: SeatStatusChange[];
    onApply: (status: OperationalSeatStatus, reason: string) => void;
    onClose: () => void;
}) {
    const panel = useRef<HTMLDivElement>(null);
    const [position, setPosition] = useState<{
        top: number;
        left: number;
    } | null>(null);
    const [selected, setSelected] = useState<OperationalSeatStatus>(status);
    const [reason, setReason] = useState('');

    useLayoutEffect(() => {
        const el = panel.current;

        if (!el) {
            return;
        }

        const rect = el.getBoundingClientRect();
        let top = anchor.bottom + 8;

        if (top + rect.height > window.innerHeight - 8) {
            top = Math.max(8, anchor.top - rect.height - 8);
        }

        const left = Math.min(
            Math.max(8, anchor.left + anchor.width / 2 - rect.width / 2),
            window.innerWidth - rect.width - 8,
        );

        setPosition({ top, left });
    }, [anchor]);

    useEffect(() => {
        const onPointerDown = (event: PointerEvent) => {
            if (
                panel.current &&
                !panel.current.contains(event.target as Node)
            ) {
                onClose();
            }
        };
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };

        window.addEventListener('pointerdown', onPointerDown);
        window.addEventListener('keydown', onKey);

        return () => {
            window.removeEventListener('pointerdown', onPointerDown);
            window.removeEventListener('keydown', onKey);
        };
    }, [onClose]);

    const needsReason = selected !== 'active';

    return (
        <div
            ref={panel}
            className="seat-status-popover"
            style={{
                top: position?.top ?? anchor.bottom + 8,
                left: position?.left ?? anchor.left,
                visibility: position ? 'visible' : 'hidden',
            }}
            role="dialog"
            aria-label={`Statut du siège ${seatCode}`}
        >
            <div className="mb-3 flex items-center justify-between">
                <div>
                    <span className="block text-[0.5625rem] font-black tracking-[0.12em] text-muted-foreground uppercase">
                        Siège {seatCode}
                    </span>
                    <strong className="text-sm font-bold text-foreground">
                        Disponibilité
                    </strong>
                </div>
                <button
                    type="button"
                    onClick={onClose}
                    className="grid size-7 place-items-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    aria-label="Fermer"
                >
                    <X className="size-3.5" />
                </button>
            </div>

            <div className="mb-3 space-y-1">
                {STATUS_OPTIONS.map(({ value, label, icon: Icon, dot }) => (
                    <button
                        key={value}
                        type="button"
                        onClick={() => setSelected(value)}
                        className={cn(
                            'seat-status-choice',
                            selected === value && 'is-active',
                        )}
                    >
                        <span className={cn('size-2 rounded-full', dot)} />
                        <Icon className="size-3.5 text-muted-foreground" />
                        {label}
                    </button>
                ))}
            </div>

            <label className="mb-1 block text-[0.625rem] font-bold tracking-wide text-muted-foreground uppercase">
                Motif {needsReason ? '' : '(optionnel)'}
            </label>
            <textarea
                value={reason}
                onChange={(event) => setReason(event.target.value)}
                rows={2}
                placeholder="Ex. accoudoir cassé, ceinture HS…"
                className="mb-3 w-full rounded-[0.625rem] border border-border bg-background px-3 py-2 text-xs text-foreground placeholder:text-muted-foreground/60 focus:border-gold focus:outline-none"
            />

            {history.length > 0 && (
                <div className="mb-3 rounded-[0.625rem] bg-muted/50 p-2.5">
                    <span className="mb-1.5 flex items-center gap-1.5 text-[0.5625rem] font-black tracking-[0.12em] text-muted-foreground uppercase">
                        <History className="size-3" />
                        Historique
                    </span>
                    {history.slice(0, 3).map((entry, index) => (
                        <p
                            key={index}
                            className="text-[0.625rem] leading-relaxed text-muted-foreground"
                        >
                            <strong className="text-foreground">
                                {STATUS_OPTIONS.find(
                                    (option) => option.value === entry.status,
                                )?.label ?? entry.status}
                            </strong>
                            {entry.reason ? ` — ${entry.reason}` : ''} ·{' '}
                            {new Date(entry.at).toLocaleDateString('fr-FR', {
                                day: '2-digit',
                                month: 'short',
                            })}
                        </p>
                    ))}
                </div>
            )}

            <button
                type="button"
                disabled={needsReason && !reason.trim()}
                onClick={() => onApply(selected, reason.trim())}
                className="btn btn-primary w-full"
            >
                Appliquer
            </button>
        </div>
    );
}
