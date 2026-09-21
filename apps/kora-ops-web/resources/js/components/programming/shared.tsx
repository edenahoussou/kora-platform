import { http, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    Ban,
    Bus,
    CalendarDays,
    Check,
    CheckCircle2,
    ChevronDown,
    GitBranch,
    Search,
    Sparkles,
    User,
    Users,
    Wrench,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useId, useState } from 'react';
import type { ReactNode } from 'react';
import { Button as BaseButton } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input as BaseInput } from '@/components/ui/input';
import { cn, uuid } from '@/lib/utils';
import type { Option, PlanningItem, References, Slot, State } from './types';

// Keep Radix/shadcn behavior; use the Guichet and Caisse control treatment.
export function ProgrammingButton({
    className,
    variant = 'default',
    size,
    ...props
}: React.ComponentProps<typeof BaseButton>) {
    return (
        <BaseButton
            {...props}
            variant={variant}
            size={size}
            className={cn(
                'h-10 rounded-[10px] px-4 text-xs font-extrabold shadow-none transition-all duration-200 focus-visible:ring-2 focus-visible:ring-gold/40 active:scale-[0.98]',
                variant === 'default' &&
                    'bg-gold text-kora shadow-[0_4px_14px_rgba(246,176,0,0.22)] hover:bg-gold/90',
                (variant === 'outline' || variant === 'secondary') &&
                    'border border-border bg-card text-foreground hover:border-gold/60 hover:bg-muted/40 hover:text-foreground dark:bg-card dark:hover:bg-muted/30',
                variant === 'ghost' &&
                    'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                variant === 'destructive' &&
                    'bg-destructive text-destructive-foreground hover:bg-destructive/90',
                size === 'icon' && 'size-10 shrink-0 p-0',
                size === 'sm' && 'h-9 px-3 text-xs',
                className,
            )}
        />
    );
}
const Button = ProgrammingButton;

export function ProgrammingInput({
    className,
    ...props
}: React.ComponentProps<typeof BaseInput>) {
    return (
        <BaseInput
            {...props}
            className={cn(
                'h-10 min-w-0 rounded-[10px] border-border bg-background px-3 text-xs shadow-none transition-colors focus-visible:border-gold focus-visible:ring-2 focus-visible:ring-gold/20 md:text-xs dark:bg-background dark:scheme-dark',
                className,
            )}
        />
    );
}
const Input = ProgrammingInput;

export const states: Record<State, string> = {
    ready: 'Prêt à publier',
    blocked: 'À corriger',
    unassigned: 'À affecter',
    published: 'Publié',
    excluded: 'Exclu',
};

/**
 * Thème visuel par état de départ.
 *
 * `badge` sert au StateBadge compact, `chip` aux filtres du planning.
 * Chaque état a sa propre couleur pour guider l'œil instantanément :
 *  - ready      → or (prêt à publier)
 *  - blocked    → rouge destructif (à corriger)
 *  - unassigned → bleu digital (à affecter)
 *  - published  → émeraude (publié)
 *  - excluded   → ardoise discrète (exclu)
 */
export const stateTheme: Record<
    State,
    {
        icon: LucideIcon;
        badge: string;
        chip: string;
        chipActive: string;
        dot: string;
    }
> = {
    ready: {
        icon: Sparkles,
        badge: 'bg-gold/15 text-amber-900 ring-1 ring-gold/30 dark:text-amber-300 dark:bg-gold/20',
        chip: 'border-border bg-card text-muted-foreground hover:border-gold/70 hover:bg-gold/5',
        chipActive: 'border-gold bg-gold/15 text-amber-900 dark:text-amber-300',
        dot: 'bg-gold',
    },
    blocked: {
        icon: AlertTriangle,
        badge: 'bg-destructive/10 text-destructive ring-1 ring-destructive/25 dark:text-red-300',
        chip: 'border-border bg-card text-muted-foreground hover:border-destructive/60 hover:bg-destructive/5',
        chipActive:
            'border-destructive/60 bg-destructive/10 text-destructive dark:text-red-300',
        dot: 'bg-destructive',
    },
    unassigned: {
        icon: Bus,
        badge: 'bg-sky-500/10 text-sky-700 ring-1 ring-sky-500/25 dark:text-sky-300',
        chip: 'border-border bg-card text-muted-foreground hover:border-sky-500/60 hover:bg-sky-500/5',
        chipActive:
            'border-sky-500/60 bg-sky-500/10 text-sky-700 dark:text-sky-300',
        dot: 'bg-sky-500',
    },
    published: {
        icon: CheckCircle2,
        badge: 'bg-emerald-500/10 text-emerald-700 ring-1 ring-emerald-500/25 dark:text-emerald-400',
        chip: 'border-border bg-card text-muted-foreground hover:border-emerald-500/60 hover:bg-emerald-500/5',
        chipActive:
            'border-emerald-500/60 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        dot: 'bg-emerald-500',
    },
    excluded: {
        icon: Ban,
        badge: 'bg-slate-500/10 text-slate-600 ring-1 ring-slate-500/20 line-through decoration-slate-400/60 dark:text-slate-400',
        chip: 'border-border bg-card text-muted-foreground hover:border-slate-500/50 hover:bg-slate-500/5',
        chipActive:
            'border-slate-500/50 bg-slate-500/10 text-slate-600 dark:text-slate-400',
        dot: 'bg-slate-500',
    },
};
export const days = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
export const crewFields = [
    { key: 'driver_uuid', label: 'Chauffeur principal', role: 'bus_driver' },
    {
        key: 'secondary_driver_uuid',
        label: 'Chauffeur secondaire',
        role: 'bus_driver',
    },
    { key: 'conductor_uuid', label: 'Convoyeur', role: 'conductor' },
    { key: 'mechanic_uuid', label: 'Mécanicien', role: 'mechanic' },
] as const;
export const dateLabel = (date: string) =>
    new Date(`${date.slice(0, 10)}T12:00:00`).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'short',
    });
export function shiftDate(date: string, amount: number) {
    const value = new Date(`${date}T12:00:00Z`);
    value.setUTCDate(value.getUTCDate() + amount);

    return value.toISOString().slice(0, 10);
}
export function today() {
    const d = new Date();

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
export function freshSlot(timezone: string): Slot {
    return {
        key: uuid(),
        direction: 'outbound',
        line_uuid: null,
        timezone,
        time: '07:00',
        days: [1, 2, 3, 4, 5],
        interval_weeks: 1,
        duration_minutes: 180,
        preparation_minutes: 0,
        stops: [],
        bus_uuid: null,
        driver_uuid: null,
        secondary_driver_uuid: null,
        conductor_uuid: null,
        mechanic_uuid: null,
        return_key: null,
        return_day_offset: 0,
        turnaround_minutes: 30,
    };
}
export function StateBadge({ state }: { state: State }) {
    const theme = stateTheme[state];
    const Icon = theme.icon;

    return (
        <span
            className={cn(
                'inline-flex w-fit items-center gap-1.5 rounded-md px-2 py-1 text-[0.625rem] font-extrabold',
                theme.badge,
            )}
        >
            <Icon size={12} strokeWidth={1.75} />
            {states[state]}
        </span>
    );
}
export function Surface({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={cn(
                'rounded-2xl border border-border bg-card p-6 shadow-[0_8px_30px_rgba(0,29,78,0.04)] dark:shadow-none',
                className,
            )}
        >
            {children}
        </section>
    );
}

/**
 * Section directionnelle (Aller / Retour) avec code couleur fort et
 * repliage/dépliage pour gérer les longues listes d'arrêts.
 *
 *  - Aller  → accent bleu digital (border + fond teinté)
 *  - Retour → accent or (border + fond teinté)
 *
 * L'en-tête est toujours visible et sert de toggle de repliage.
 */
export function DirectionSection({
    direction,
    title,
    badge,
    actions,
    children,
    defaultOpen = true,
    summary,
}: {
    direction: 'outbound' | 'return';
    title: ReactNode;
    badge?: ReactNode;
    actions?: ReactNode;
    children: ReactNode;
    defaultOpen?: boolean;
    summary?: ReactNode;
}) {
    const [open, setOpen] = useState(defaultOpen);
    const isOutbound = direction === 'outbound';
    const Icon = isOutbound ? ArrowRight : ArrowLeft;

    return (
        <div
            className={cn(
                'overflow-hidden rounded-2xl border bg-card transition-colors',
                isOutbound
                    ? 'border-digital/30 bg-digital/[0.02]'
                    : 'border-gold/30 bg-gold/[0.02]',
            )}
        >
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                aria-expanded={open}
                className={cn(
                    'flex w-full items-center justify-between gap-3 px-5 py-4 text-left transition-colors',
                    isOutbound
                        ? 'bg-digital/5 hover:bg-digital/10'
                        : 'bg-gold/5 hover:bg-gold/10',
                )}
            >
                <div className="flex items-center gap-2.5">
                    <span
                        className={cn(
                            'grid size-7 place-items-center rounded-lg',
                            isOutbound
                                ? 'bg-digital/15 text-digital'
                                : 'bg-gold/20 text-kora dark:text-gold',
                        )}
                    >
                        <Icon size={15} strokeWidth={2.5} />
                    </span>
                    <div className="grid gap-0.5">
                        <span className="flex items-center gap-2 text-xs font-extrabold text-foreground">
                            {title}
                        </span>
                        {summary && (
                            <span className="text-[0.6875rem] font-semibold text-muted-foreground">
                                {summary}
                            </span>
                        )}
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    {badge}
                    {actions}
                    <ChevronDown
                        size={16}
                        className={cn(
                            'shrink-0 text-muted-foreground transition-transform duration-200',
                            open ? '' : '-rotate-90',
                        )}
                    />
                </div>
            </button>
            {open && (
                <div className="border-t border-border/60 p-5">{children}</div>
            )}
        </div>
    );
}
export function Field({
    label,
    children,
    hint,
    icon: Icon,
    required = false,
}: {
    label: string;
    children: ReactNode;
    hint?: string;
    icon?: LucideIcon;
    required?: boolean;
}) {
    return (
        <label className="grid gap-1.5 text-xs font-extrabold text-foreground">
            <span className="flex items-center gap-1.5 text-foreground">
                {Icon && (
                    <Icon
                        size={14}
                        strokeWidth={2}
                        className="text-kora dark:text-gold"
                    />
                )}
                {label}
                {required && (
                    <span className="text-destructive" aria-hidden="true">
                        *
                    </span>
                )}
            </span>
            {children}
            {hint && (
                <span className="text-xs leading-relaxed font-normal text-muted-foreground">
                    {hint}
                </span>
            )}
        </label>
    );
}
export function SelectField({
    label,
    value,
    options,
    onChange,
    empty = 'Choisir…',
    disabled = false,
    hint,
    icon: Icon,
    required = false,
}: {
    label: string;
    value?: string | null;
    options: (Option & { disabled?: boolean })[];
    onChange: (value: string) => void;
    empty?: string;
    disabled?: boolean;
    hint?: string;
    icon?: LucideIcon;
    required?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const id = useId();

    if (options.length > 12) {
        const matches = options.filter((o) =>
            o.label.toLocaleLowerCase().includes(query.toLocaleLowerCase()),
        );
        const selectedLabel = options.find((o) => o.uuid === value)?.label;

        return (
            <div className="grid gap-1.5">
                <span
                    id={id}
                    className="flex items-center gap-1.5 text-xs font-extrabold text-foreground"
                >
                    {Icon && (
                        <Icon
                            size={14}
                            strokeWidth={2}
                            className="text-kora dark:text-gold"
                        />
                    )}
                    {label}
                    {required && (
                        <span className="text-destructive" aria-hidden="true">
                            *
                        </span>
                    )}
                </span>
                <button
                    type="button"
                    aria-labelledby={id}
                    aria-haspopup="dialog"
                    disabled={disabled}
                    onClick={() => {
                        setQuery('');
                        setOpen(true);
                    }}
                    className="group flex h-10 min-w-0 items-center justify-between gap-2 rounded-[10px] border border-border bg-background px-3 text-xs font-normal text-foreground transition-all duration-200 outline-none hover:border-gold/60 hover:shadow-[0_0_0_3px_rgba(218,165,32,0.08)] focus:border-gold focus:ring-2 focus:ring-gold/20 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <span
                        className={cn(
                            'truncate',
                            !selectedLabel && 'text-muted-foreground',
                        )}
                    >
                        {selectedLabel ?? empty}
                    </span>
                    <ArrowRight className="size-4 shrink-0 text-muted-foreground transition-transform duration-200 group-hover:translate-x-0.5 group-hover:text-gold" />
                </button>
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogPortal>
                        <DialogOverlay className="bg-[rgba(0,29,78,0.32)] backdrop-blur-md" />
                    </DialogPortal>
                    <DialogContent
                        overlay={false}
                        showClose={false}
                        className="gap-0 overflow-hidden rounded-2xl border-border/50 bg-card p-0 shadow-[0_30px_80px_rgba(0,29,78,0.35)] sm:max-w-lg [&>button]:hidden"
                    >
                        {/* En-tête avec hiérarchie visuelle */}
                        <div className="flex items-start justify-between gap-4 border-b border-border/60 bg-gradient-to-br from-gold/[0.04] to-transparent p-5 pb-4">
                            <div className="flex items-start gap-3">
                                {Icon && (
                                    <span className="grid size-9 shrink-0 place-items-center rounded-[0.7rem] bg-gold/10 text-gold">
                                        <Icon size={17} strokeWidth={2.2} />
                                    </span>
                                )}
                                <div>
                                    <DialogTitle className="text-[1.0625rem] font-extrabold tracking-[-0.02em] text-foreground">
                                        {label}
                                        {required && (
                                            <span className="ml-0.5 text-destructive">
                                                *
                                            </span>
                                        )}
                                    </DialogTitle>
                                    <DialogDescription className="mt-0.5 text-xs text-muted-foreground">
                                        Recherchez puis sélectionnez une option.
                                    </DialogDescription>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setOpen(false)}
                                className="grid size-8 shrink-0 place-items-center rounded-[0.55rem] bg-muted text-muted-foreground transition-colors hover:bg-muted/70 hover:text-foreground"
                                aria-label="Fermer"
                            >
                                <X className="size-4" />
                            </button>
                        </div>

                        {/* Barre de recherche */}
                        <div className="px-5 pt-4">
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    autoFocus
                                    aria-label={`Rechercher — ${label}`}
                                    placeholder="Rechercher…"
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    className="h-10 rounded-[0.7rem] border-border bg-muted/40 pl-9 text-xs font-medium text-foreground placeholder:text-muted-foreground/70 focus:border-gold/50 focus:bg-background focus:ring-2 focus:ring-gold/15"
                                />
                            </div>
                        </div>

                        {/* Liste des options */}
                        <div
                            className="grid max-h-[20rem] gap-0.5 overflow-y-auto p-3"
                            aria-label={label}
                        >
                            <button
                                type="button"
                                onClick={() => {
                                    onChange('');
                                    setOpen(false);
                                }}
                                className={cn(
                                    'flex min-h-11 items-center gap-2.5 rounded-[0.6rem] px-3 text-left text-xs font-medium text-muted-foreground transition-all duration-150 hover:bg-muted/60',
                                    !value && 'bg-gold/[0.06] text-foreground',
                                )}
                            >
                                {!value && (
                                    <Check className="size-4 shrink-0 text-gold" />
                                )}
                                <span className={cn(!value && 'ml-0')}>
                                    {empty}
                                </span>
                            </button>
                            {matches.map((o, idx) => {
                                const isSelected = o.uuid === value;

                                return (
                                    <button
                                        key={o.uuid}
                                        type="button"
                                        disabled={o.disabled}
                                        style={{
                                            animationDelay: `${Math.min(idx * 18, 200)}ms`,
                                        }}
                                        className={cn(
                                            'flex min-h-11 animate-in items-center gap-2.5 rounded-[0.6rem] px-3 text-left text-xs font-medium transition-all duration-150 duration-300 fade-in slide-in-from-left-2 hover:translate-x-0.5 hover:bg-muted/60',
                                            isSelected
                                                ? 'bg-gold/[0.08] text-foreground ring-1 ring-gold/20'
                                                : 'text-foreground/90',
                                            o.disabled &&
                                                'cursor-not-allowed opacity-40 hover:translate-x-0 hover:bg-transparent',
                                        )}
                                        onClick={() => {
                                            onChange(o.uuid);
                                            setOpen(false);
                                        }}
                                    >
                                        <span
                                            className={cn(
                                                'grid size-5 shrink-0 place-items-center rounded-full transition-all duration-200',
                                                isSelected
                                                    ? 'scale-100 bg-gold text-[var(--kora)]'
                                                    : 'scale-90 bg-muted/60 opacity-0 group-hover:opacity-100',
                                            )}
                                        >
                                            {isSelected && (
                                                <Check
                                                    className="size-3.5"
                                                    strokeWidth={3}
                                                />
                                            )}
                                        </span>
                                        <span className="leading-relaxed whitespace-normal">
                                            {o.label}
                                        </span>
                                    </button>
                                );
                            })}
                            {matches.length === 0 && (
                                <div className="flex flex-col items-center gap-2 py-8 text-center">
                                    <Search className="size-7 text-muted-foreground/40" />
                                    <p className="text-xs text-muted-foreground">
                                        Aucun résultat pour «{' '}
                                        <strong className="text-foreground">
                                            {query}
                                        </strong>{' '}
                                        »
                                    </p>
                                </div>
                            )}
                        </div>
                    </DialogContent>
                </Dialog>
                {hint && (
                    <span className="text-xs leading-relaxed font-normal text-muted-foreground">
                        {hint}
                    </span>
                )}
            </div>
        );
    }

    return (
        <Field label={label} icon={Icon} hint={hint} required={required}>
            <select
                disabled={disabled}
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value)}
                className="h-10 w-full min-w-0 rounded-[10px] border border-border bg-background px-3 text-xs font-normal text-foreground transition-colors outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 disabled:opacity-50"
            >
                <option value="">{empty}</option>
                {options.map((o) => (
                    <option key={o.uuid} value={o.uuid} disabled={o.disabled}>
                        {o.label}
                    </option>
                ))}
            </select>
        </Field>
    );
}
export function Empty({
    title,
    children,
    action,
}: {
    title: string;
    children: ReactNode;
    action?: ReactNode;
}) {
    return (
        <div className="grid min-h-64 place-content-center justify-items-center gap-4 p-6 text-center">
            <span className="rounded-2xl bg-muted p-4">
                <CalendarDays size={28} strokeWidth={1.25} />
            </span>
            <h2 className="text-[1.0625rem] font-extrabold tracking-[-0.025em]">
                {title}
            </h2>
            <p className="max-w-md text-sm leading-6 text-muted-foreground">
                {children}
            </p>
            {action}
        </div>
    );
}
export function Errors({ errors }: { errors: Record<string, unknown> }) {
    const entries = Object.entries(errors);

    return entries.length ? (
        <div
            role="alert"
            className="rounded-xl bg-destructive/10 p-4 text-sm text-destructive"
        >
            <p className="mb-2 font-semibold">Vérifiez les points suivants</p>
            <ul className="list-inside list-disc space-y-1">
                {entries.map(([key, value]) => (
                    <li key={key}>
                        {Array.isArray(value) ? value.join(' ') : String(value)}
                    </li>
                ))}
            </ul>
        </div>
    ) : null;
}
export function Confirm({
    open,
    title,
    children,
    action,
    onClose,
    busy = false,
}: {
    open: boolean;
    title: string;
    children: ReactNode;
    action: ReactNode;
    onClose: () => void;
    busy?: boolean;
}) {
    return (
        <Dialog
            open={open}
            onOpenChange={(v) => {
                if (!v && !busy) {
                    onClose();
                }
            }}
        >
            <DialogPortal>
                <DialogOverlay className="bg-[rgba(0,29,78,0.24)] backdrop-blur-md" />
            </DialogPortal>
            <DialogContent
                overlay={false}
                className="max-h-[85dvh] overflow-y-auto rounded-2xl border-border/50 bg-card p-0 shadow-[0_30px_80px_rgba(0,29,78,0.35)] sm:max-w-2xl [&>button]:hidden"
            >
                <DialogTitle className="text-xl">{title}</DialogTitle>
                <DialogDescription>
                    Vérifiez le périmètre et les conséquences avant de
                    confirmer.
                </DialogDescription>
                <div className="grid gap-4 py-3">{children}</div>
                <div className="flex flex-wrap justify-end gap-3">
                    <Button variant="outline" onClick={onClose} disabled={busy}>
                        Fermer
                    </Button>
                    {action}
                </div>
            </DialogContent>
        </Dialog>
    );
}
export function useProgrammingRequest() {
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, unknown>>({});
    async function send<T>(
        url: string,
        data?: unknown,
        method: 'get' | 'post' = 'post',
    ): Promise<T | null> {
        setBusy(true);
        setErrors({});

        try {
            const result = await http.getClient().request({
                url,
                method,
                data,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            return JSON.parse(result.data) as T;
        } catch (error) {
            const response = error as { response?: { data: string } };

            try {
                const body = JSON.parse(response.response?.data ?? '{}') as {
                    errors?: Record<string, string>;
                    message?: string;
                };
                setErrors(
                    body.errors ?? {
                        request:
                            body.message ??
                            'Connexion interrompue. Vos saisies sont conservées ; réessayez.',
                    },
                );
            } catch {
                setErrors({
                    request:
                        'Connexion interrompue. Vos saisies sont conservées ; réessayez.',
                });
            }

            return null;
        } finally {
            setBusy(false);
        }
    }

    return { send, busy, errors, setErrors };
}
export function useUnsavedChanges(dirty: boolean) {
    useEffect(() => {
        if (!dirty) {
            return;
        }

        const before = (e: BeforeUnloadEvent) => {
            e.preventDefault();
            e.returnValue = '';
        };
        window.addEventListener('beforeunload', before);
        const remove = router.on('before', (e) => {
            if (
                e.detail.visit.method === 'get' &&
                !window.confirm(
                    'Des modifications ne sont pas enregistrées. Quitter cette page ?',
                )
            ) {
                e.preventDefault();
            }
        });

        return () => {
            window.removeEventListener('beforeunload', before);
            remove();
        };
    }, [dirty]);
}
export function SlotSummary({ slot, refs }: { slot: Slot; refs: References }) {
    const line = refs.lines.find((l) => l.uuid === slot.line_uuid);
    const isOutbound = slot.direction === 'outbound';

    return (
        <div
            className={cn(
                'flex items-center justify-between gap-3 rounded-xl border-l-4 p-3',
                isOutbound
                    ? 'border-l-digital/50 bg-digital/[0.03]'
                    : 'border-l-gold/50 bg-gold/[0.03]',
            )}
        >
            <div>
                <p className="flex items-center gap-1.5 text-[0.8125rem] font-bold">
                    <span
                        className={cn(
                            'grid size-4 place-items-center rounded text-[0.5rem] font-black',
                            isOutbound
                                ? 'bg-digital/15 text-digital'
                                : 'bg-gold/20 text-kora dark:text-gold',
                        )}
                    >
                        {isOutbound ? 'A' : 'R'}
                    </span>
                    {line?.label ?? 'Ligne à choisir'}
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                    {slot.days.map((d) => days[d - 1]).join(' · ')}
                    {slot.interval_weeks > 1
                        ? ` · Toutes les ${slot.interval_weeks} semaines`
                        : ''}
                </p>
            </div>
            <strong className="text-lg tabular-nums">{slot.time}</strong>
        </div>
    );
}
export function ResourceFields({
    slot,
    refs,
    onChange,
}: {
    slot: Slot;
    refs: References;
    onChange: (patch: Partial<Slot>) => void;
}) {
    const selectedBus = refs.buses.find((b) => b.uuid === slot.bus_uuid);
    const busUnavailable =
        selectedBus !== undefined && selectedBus.status !== 'active';

    return (
        <div className="grid gap-4">
            <SelectField
                label="Bus habituel"
                icon={Bus}
                hint="Le bus proposé par défaut pour les départs issus de ce modèle."
                value={slot.bus_uuid}
                empty="À affecter plus tard"
                options={refs.buses.map((b) => ({
                    ...b,
                    label: `${b.label} · ${b.capacity} places${b.status !== 'active' ? ' · Indisponible' : ''}`,
                    disabled: b.status !== 'active',
                }))}
                onChange={(v) => onChange({ bus_uuid: v || null })}
            />
            {busUnavailable && (
                <div className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-xs font-semibold text-destructive">
                    <AlertTriangle size={14} className="mt-0.5 shrink-0" />
                    <span>
                        Le bus sélectionné « {selectedBus?.label} » est
                        actuellement indisponible. Il faudra réaffecter un bus
                        disponible avant la publication.
                    </span>
                </div>
            )}
            <div className="grid gap-4 sm:grid-cols-2">
                {crewFields.map((c) => {
                    const usedElsewhere = crewFields
                        .filter((other) => other.key !== c.key)
                        .map((other) => slot[other.key])
                        .filter(Boolean);

                    return (
                        <SelectField
                            key={c.key}
                            label={c.label}
                            icon={c.role === 'mechanic' ? Wrench : Users}
                            hint="Facultatif : cette ressource pourra être ajustée avant publication."
                            value={slot[c.key]}
                            empty="À affecter plus tard"
                            options={refs.people
                                .filter((p) => p.roles.includes(c.role))
                                .map((p) => ({
                                    ...p,
                                    disabled: usedElsewhere.includes(p.uuid),
                                    label: usedElsewhere.includes(p.uuid)
                                        ? `${p.label} · déjà assigné`
                                        : p.label,
                                }))}
                            onChange={(v) => onChange({ [c.key]: v || null })}
                        />
                    );
                })}
            </div>
        </div>
    );
}
export function FillGauge({
    occupancy,
    capacity,
    className,
}: {
    occupancy: {
        booked: number;
        capacity: number | null;
        ratio: number | null;
    } | null;
    capacity?: number | null;
    className?: string;
}) {
    const busCapacity = occupancy?.capacity ?? capacity ?? null;
    const booked = occupancy?.booked ?? 0;
    const ratio =
        occupancy?.ratio ??
        (busCapacity && busCapacity > 0 ? booked / busCapacity : null);

    if (occupancy === null) {
        if (busCapacity === null) {
            return null;
        }

        return (
            <span
                className={cn(
                    'text-[0.625rem] font-bold text-muted-foreground tabular-nums',
                    className,
                )}
                aria-label={`Capacité du bus : ${busCapacity} places`}
            >
                {busCapacity} pl.
            </span>
        );
    }

    const pct = ratio !== null ? Math.min(100, Math.round(ratio * 100)) : 0;
    const barColor =
        pct >= 70 ? 'bg-emerald-500' : pct >= 30 ? 'bg-gold' : 'bg-digital';
    const textColor =
        pct >= 70
            ? 'text-emerald-600 dark:text-emerald-400'
            : pct >= 30
              ? 'text-gold'
              : 'text-digital';

    return (
        <div className={cn('flex items-center gap-2', className)}>
            <div
                className="relative h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                aria-valuenow={booked}
                aria-valuemin={0}
                aria-valuemax={busCapacity ?? 0}
                aria-label={`${booked} sièges réservés sur ${busCapacity ?? '?'}`}
            >
                <div
                    className={cn(
                        'h-full rounded-full transition-all duration-700 ease-out',
                        barColor,
                    )}
                    style={{ width: `${pct}%` }}
                />
            </div>
            <span
                className={cn(
                    'text-[0.625rem] font-bold whitespace-nowrap tabular-nums',
                    textColor,
                )}
            >
                {booked}/{busCapacity ?? '?'}
            </span>
        </div>
    );
}

export function OccupancyRing({
    occupancy,
    capacity,
    size = 84,
    className,
}: {
    occupancy: PlanningItem['occupancy'];
    capacity?: number | null;
    size?: number;
    className?: string;
}) {
    const busCapacity = occupancy?.capacity ?? capacity ?? null;
    const booked = occupancy?.booked ?? 0;
    const ratio =
        occupancy?.ratio ??
        (busCapacity && busCapacity > 0 ? booked / busCapacity : null);
    const pct = ratio !== null ? Math.min(100, Math.round(ratio * 100)) : null;

    if (occupancy === null && busCapacity === null) {
        return null;
    }

    const stroke = 7;
    const radius = (size - stroke) / 2;
    const circumference = 2 * Math.PI * radius;
    const arcTone =
        pct === null
            ? 'stroke-white/20'
            : pct >= 70
              ? 'stroke-emerald-400'
              : pct >= 30
                ? 'stroke-gold'
                : 'stroke-sky-300';

    return (
        <div
            className={cn(
                'relative grid shrink-0 place-items-center',
                className,
            )}
            style={{ width: size, height: size }}
            role="img"
            aria-label={
                pct !== null
                    ? `Remplissage : ${pct}% (${booked} sièges sur ${busCapacity ?? '?'})`
                    : `Capacité : ${busCapacity} places`
            }
        >
            <svg width={size} height={size} className="-rotate-90">
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    strokeWidth={stroke}
                    className="stroke-white/15"
                />
                {pct !== null && pct > 0 && (
                    <circle
                        cx={size / 2}
                        cy={size / 2}
                        r={radius}
                        fill="none"
                        strokeWidth={stroke}
                        strokeLinecap="round"
                        strokeDasharray={circumference}
                        strokeDashoffset={circumference * (1 - pct / 100)}
                        className={cn(
                            'transition-[stroke-dashoffset] duration-700 ease-out',
                            arcTone,
                        )}
                    />
                )}
            </svg>
            <div className="absolute inset-0 grid place-items-center text-center">
                <div>
                    <span className="block text-lg leading-none font-extrabold text-white tabular-nums">
                        {pct !== null ? `${pct}%` : (busCapacity ?? '—')}
                    </span>
                    <span className="mt-1 block text-[0.5625rem] font-bold tracking-[0.08em] text-white/60 uppercase">
                        {pct !== null
                            ? `${booked}/${busCapacity ?? '?'} pl.`
                            : 'places'}
                    </span>
                </div>
            </div>
        </div>
    );
}

export function PlanningCard({
    item,
    refs,
    onOpen,
}: {
    item: PlanningItem;
    refs: References;
    onOpen: () => void;
}) {
    const bus = refs.buses.find((b) => b.uuid === item.snapshot.bus_uuid);
    const busLabel = bus?.label ?? 'Bus à affecter';
    const hasBus = item.snapshot.bus_uuid !== null;
    const DirectionIcon =
        item.snapshot.direction === 'outbound' ? ArrowRight : ArrowLeft;
    const isOutbound = item.snapshot.direction === 'outbound';
    const driver = refs.people.find(
        (p) => p.uuid === item.snapshot.driver_uuid,
    );
    const conductor = refs.people.find(
        (p) => p.uuid === item.snapshot.conductor_uuid,
    );

    return (
        <button
            onClick={onOpen}
            className={cn(
                'group grid w-full gap-2.5 rounded-[10px] border bg-card p-3 text-left transition-all duration-200 hover:-translate-y-0.5 hover:shadow-[0_8px_30px_rgba(0,29,78,0.08)] focus-visible:outline-2 focus-visible:outline-ring',
                item.state === 'blocked'
                    ? 'border-destructive/40 hover:border-destructive/60 hover:shadow-[0_8px_30px_rgba(220,38,38,0.08)]'
                    : 'border-border hover:border-gold hover:ring-1 hover:ring-gold/20',
            )}
        >
            {/* En-tête : direction + heure + exception */}
            <div className="flex items-center justify-between gap-2">
                <span className="flex items-center gap-1.5">
                    <span
                        className={cn(
                            'grid size-5 place-items-center rounded-md transition-transform duration-200',
                            isOutbound
                                ? 'bg-digital/15 text-digital group-hover:translate-x-0.5'
                                : 'bg-gold/20 text-kora group-hover:-translate-x-0.5 dark:text-gold',
                        )}
                    >
                        <DirectionIcon size={12} strokeWidth={2.5} />
                    </span>
                    <strong className="text-base text-foreground tabular-nums">
                        {item.snapshot.time}
                    </strong>
                </span>
                <div className="flex items-center gap-1.5">
                    {item.isException && (
                        <GitBranch
                            size={13}
                            className="text-violet-500"
                            aria-label="Exception"
                        />
                    )}
                    {item.blockers.length > 0 && (
                        <AlertTriangle
                            size={12}
                            className="text-destructive"
                            aria-label={`${item.blockers.length} alerte(s)`}
                        />
                    )}
                </div>
            </div>

            {/* Bus + équipage compact */}
            <div className="grid gap-1">
                <span
                    className={cn(
                        'flex items-center gap-1.5 truncate text-xs',
                        hasBus
                            ? 'text-muted-foreground'
                            : 'font-semibold text-sky-700 dark:text-sky-300',
                    )}
                >
                    <Bus
                        size={12}
                        strokeWidth={1.75}
                        className={hasBus ? '' : 'animate-pulse'}
                    />
                    {busLabel}
                    {bus && hasBus && (
                        <span className="text-muted-foreground/60">
                            · {bus.capacity} pl.
                        </span>
                    )}
                </span>
                {(driver || conductor) && (
                    <div className="flex items-center gap-2.5 text-[0.625rem] text-muted-foreground">
                        {driver && (
                            <span className="flex items-center gap-1 truncate">
                                <User
                                    size={10}
                                    strokeWidth={2}
                                    className="text-kora dark:text-gold"
                                />
                                {driver.label}
                            </span>
                        )}
                        {conductor && (
                            <span className="flex items-center gap-1 truncate">
                                <Users
                                    size={10}
                                    strokeWidth={2}
                                    className="text-kora dark:text-gold"
                                />
                                {conductor.label}
                            </span>
                        )}
                    </div>
                )}
            </div>

            {/* Jauge de remplissage */}
            {item.occupancy !== null && (
                <FillGauge occupancy={item.occupancy} />
            )}

            {/* État + premier blocage */}
            <span className="flex items-center justify-between gap-2">
                <StateBadge state={item.state} />
                {item.occupancy === null && bus && hasBus && (
                    <span className="text-[0.625rem] font-bold text-muted-foreground tabular-nums">
                        {bus.capacity} pl.
                    </span>
                )}
            </span>
            {item.blockers.length > 0 && (
                <p className="line-clamp-2 text-[0.625rem] leading-4 text-destructive/90">
                    {item.blockers[0]}
                </p>
            )}
        </button>
    );
}
export function SearchInput({
    value,
    onChange,
    placeholder,
}: {
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
}) {
    const id = useId();

    return (
        <div className="relative">
            <label htmlFor={id} className="sr-only">
                {placeholder}
            </label>
            <Input
                id={id}
                className="h-10 rounded-[10px] pr-10"
                value={value}
                placeholder={placeholder}
                onChange={(e) => onChange(e.target.value)}
            />
            {value && (
                <button
                    className="absolute top-3 right-3"
                    onClick={() => onChange('')}
                    aria-label="Effacer la recherche"
                >
                    <X size={16} />
                </button>
            )}
        </div>
    );
}
export function PrimaryButton(props: React.ComponentProps<typeof BaseButton>) {
    return <ProgrammingButton {...props} />;
}
