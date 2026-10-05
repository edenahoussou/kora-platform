import { Link } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    CircleAlert,
    EyeOff,
    Globe2,
    Info,
    LockKeyhole,
    PlugZap,
    RotateCcw,
    Route,
    Save,
    ShieldCheck,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import PhoneInput from 'react-phone-number-input';
import phoneLabels from 'react-phone-number-input/locale/fr.json';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/hooks/use-permissions';
import { cn } from '@/lib/utils';
import administration from '@/routes/administration';
import type { ConnectionProbeResult, SettingsGroup } from '@/types/settings';

const navigation: Array<{
    key: SettingsGroup;
    label: string;
    description: string;
    icon: LucideIcon;
    permission: string;
    href: string;
}> = [
    {
        key: 'general',
        label: 'Général',
        description: 'Identité et contexte régional',
        icon: Globe2,
        permission: 'settings.general.view',
        href: administration.settings.general.url(),
    },
    {
        key: 'operations',
        label: 'Exploitation',
        description: 'Règles de vente et de départ',
        icon: Route,
        permission: 'settings.operations.view',
        href: administration.settings.operations.url(),
    },
    {
        key: 'integrations',
        label: 'Intégrations',
        description: 'Fournisseurs et connexions',
        icon: PlugZap,
        permission: 'settings.integrations.view',
        href: administration.settings.integrations.url(),
    },
];

export function SettingsTabs({ active }: { active: SettingsGroup }) {
    const { can } = usePermissions();

    return (
        <nav
            aria-label="Groupes de paramètres"
            className="grid grid-cols-1 gap-1.5 rounded-2xl border border-border bg-card p-1.5 shadow-[0_7px_28px_rgba(0,29,78,0.04)] sm:grid-cols-3"
        >
            {navigation
                .filter((item) => can(item.permission))
                .map((item) => {
                    const isActive = item.key === active;
                    const Icon = item.icon;

                    return (
                        <Link
                            key={item.key}
                            href={item.href}
                            aria-current={isActive ? 'page' : undefined}
                            className={cn(
                                'group flex items-center gap-3 rounded-xl px-3.5 py-2.5 transition-[background-color,box-shadow,transform] duration-200 outline-none focus-visible:ring-[3px] focus-visible:ring-gold/40',
                                isActive
                                    ? 'bg-kora text-white shadow-[0_10px_26px_rgba(0,29,78,0.22)]'
                                    : 'text-foreground hover:-translate-y-px hover:bg-muted/70',
                            )}
                        >
                            <span
                                className={cn(
                                    'grid size-9 shrink-0 place-items-center rounded-lg transition-colors duration-200',
                                    isActive
                                        ? 'bg-gold text-kora'
                                        : 'bg-muted text-muted-foreground group-hover:bg-gold/15 group-hover:text-gold',
                                )}
                            >
                                <Icon className="size-4" strokeWidth={2} />
                            </span>
                            <span className="min-w-0">
                                <span className="block truncate text-xs font-extrabold tracking-[-0.01em]">
                                    {item.label}
                                </span>
                                <span
                                    className={cn(
                                        'mt-0.5 block truncate text-[10px] leading-4',
                                        isActive
                                            ? 'text-white/60'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {item.description}
                                </span>
                            </span>
                            {isActive && (
                                <span className="ml-auto size-1.5 shrink-0 rounded-full bg-gold" />
                            )}
                        </Link>
                    );
                })}
        </nav>
    );
}

export function SettingsPageHeader({
    title,
    description,
    group,
}: {
    title: string;
    description: string;
    group: SettingsGroup;
}) {
    const groupLabel =
        group === 'general'
            ? 'Général'
            : group === 'operations'
              ? 'Exploitation'
              : 'Intégrations';

    return (
        <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div className="max-w-4xl">
                <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                    KORA OPS / Administration / Paramètres / {groupLabel}
                </div>
                <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                    {title}
                </h1>
                <p className="mt-2 max-w-3xl text-[0.8125rem] leading-6 text-muted-foreground">
                    {description}
                </p>
            </div>
            {group !== 'integrations' && (
                <div className="hidden items-center gap-3 rounded-2xl border border-border bg-card px-4 py-3 shadow-[0_7px_28px_rgba(0,29,78,0.04)] lg:flex">
                    <span className="grid size-9 place-items-center rounded-lg bg-kora text-gold">
                        <ShieldCheck className="size-4" strokeWidth={1.9} />
                    </span>
                    <div className="text-[11px] leading-4">
                        <span className="block font-bold text-foreground">
                            Configuration runtime
                        </span>
                        <span className="mt-0.5 block text-muted-foreground">
                            Appliquée aux prochaines requêtes et jobs.
                        </span>
                    </div>
                </div>
            )}
        </div>
    );
}

export function SettingsSection({
    title,
    description,
    children,
    className,
    actions,
}: {
    title: string;
    description?: string;
    children: React.ReactNode;
    className?: string;
    actions?: React.ReactNode;
}) {
    return (
        <section
            className={cn(
                'overflow-hidden rounded-2xl border border-border bg-card shadow-[0_7px_28px_rgba(0,29,78,0.04)]',
                className,
            )}
        >
            <header className="flex flex-wrap items-start justify-between gap-3 border-b border-border/70 px-5 py-4">
                <div className="flex min-w-0 items-start gap-3">
                    <span className="mt-[5px] h-4 w-1 shrink-0 rounded-full bg-gold" />
                    <div className="min-w-0">
                        <h3 className="text-[15px] font-extrabold tracking-[-0.02em] text-foreground">
                            {title}
                        </h3>
                        {description && (
                            <p className="mt-1 max-w-2xl text-[11px] leading-5 text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                </div>
                {actions && (
                    <div className="flex flex-wrap items-center gap-2.5">
                        {actions}
                    </div>
                )}
            </header>
            <div className="p-5">{children}</div>
        </section>
    );
}

export function SettingsField({
    label,
    hint,
    value,
    onChange,
    disabled,
    error,
    type = 'text',
    suffix,
    icon: Icon,
    placeholder,
    required = false,
    source,
    configured = false,
    showConfigured = false,
    step,
}: {
    label: string;
    hint?: string;
    value: string | number | null | undefined;
    onChange: (value: string) => void;
    disabled: boolean;
    error?: string;
    type?: 'text' | 'number' | 'url';
    suffix?: string;
    icon?: LucideIcon;
    placeholder?: string;
    required?: boolean;
    source?: string;
    configured?: boolean;
    showConfigured?: boolean;
    step?: string | number;
}) {
    return (
        <label className="grid content-start gap-1.5">
            <span className="flex flex-wrap items-center gap-1.5 text-xs font-bold text-foreground">
                <span className="flex items-center gap-1.5">
                    {Icon && (
                        <Icon
                            className="size-3.5 text-muted-foreground"
                            strokeWidth={2}
                        />
                    )}
                    {label}
                    {required && (
                        <span className="rounded-full bg-amber-500/10 px-1.5 py-0.5 text-[9px] font-extrabold tracking-wide text-amber-700 dark:text-amber-300">
                            Requis
                        </span>
                    )}
                </span>
                {showConfigured && (
                    <span
                        className={cn(
                            'rounded-full px-1.5 py-0.5 text-[9px] font-extrabold tracking-wide',
                            configured
                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                : 'bg-muted text-muted-foreground',
                        )}
                    >
                        {configured ? 'Configuré' : 'À configurer'}
                    </span>
                )}
            </span>
            <span className="relative block">
                <Input
                    type={type}
                    step={step}
                    value={value ?? ''}
                    disabled={disabled}
                    placeholder={placeholder}
                    onChange={(event) => onChange(event.target.value)}
                    aria-invalid={Boolean(error)}
                    className={cn(
                        'h-10 rounded-[10px] border-border bg-background text-[13px] shadow-none transition-[border-color,box-shadow] focus-visible:border-gold focus-visible:ring-[3px] focus-visible:ring-gold/15',
                        suffix && 'pr-16',
                    )}
                />
                {suffix && (
                    <span className="pointer-events-none absolute top-1/2 right-2.5 -translate-y-1/2 rounded-md bg-muted px-1.5 py-1 text-[9px] font-extrabold tracking-[0.08em] text-muted-foreground uppercase">
                        {suffix}
                    </span>
                )}
            </span>
            {source && (
                <span className="text-[10px] leading-4 text-muted-foreground/80">
                    Où le trouver : {source}
                </span>
            )}
            {error ? (
                <span className="text-[11px] font-semibold text-destructive">
                    {error}
                </span>
            ) : (
                hint && (
                    <span className="text-[11px] leading-4 text-muted-foreground">
                        {hint}
                    </span>
                )
            )}
        </label>
    );
}

export function SettingsSelectField({
    label,
    hint,
    value,
    onChange,
    disabled,
    error,
    options,
    icon: Icon,
}: {
    label: string;
    hint?: string;
    value: string;
    onChange: (value: string) => void;
    disabled: boolean;
    error?: string;
    options: Array<{ value: string; label: string }>;
    icon?: LucideIcon;
}) {
    return (
        <label className="grid content-start gap-1.5">
            <span className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                {Icon && (
                    <Icon
                        className="size-3.5 text-muted-foreground"
                        strokeWidth={2}
                    />
                )}
                {label}
            </span>
            <select
                value={value}
                disabled={disabled}
                onChange={(event) => onChange(event.target.value)}
                aria-invalid={Boolean(error)}
                className="h-10 rounded-[10px] border border-border bg-background px-3 text-[13px] text-foreground transition-[border-color,box-shadow] outline-none focus:border-gold focus:ring-[3px] focus:ring-gold/15 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            {error ? (
                <span className="text-[11px] font-semibold text-destructive">
                    {error}
                </span>
            ) : (
                hint && (
                    <span className="text-[11px] leading-4 text-muted-foreground">
                        {hint}
                    </span>
                )
            )}
        </label>
    );
}

export function SettingsPhoneField({
    label,
    hint,
    value,
    onChange,
    disabled,
    error,
    icon: Icon,
    placeholder,
}: {
    label: string;
    hint?: string;
    value: string | null | undefined;
    onChange: (value: string | null) => void;
    disabled: boolean;
    error?: string;
    icon?: LucideIcon;
    placeholder?: string;
}) {
    return (
        <div className="grid content-start gap-1.5">
            <span className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                {Icon && (
                    <Icon
                        className="size-3.5 text-muted-foreground"
                        strokeWidth={2}
                    />
                )}
                {label}
            </span>
            <PhoneInput
                className={cn(
                    'phone-field-control h-10 rounded-[10px] border border-border bg-background text-[13px] transition-[border-color,box-shadow] focus-within:border-gold',
                    disabled && 'cursor-not-allowed opacity-50',
                )}
                defaultCountry="BJ"
                international
                labels={phoneLabels}
                value={value ?? undefined}
                onChange={(nextValue) => onChange(nextValue ?? null)}
                placeholder={placeholder}
                disabled={disabled}
                numberInputProps={{
                    'aria-label': label,
                    'aria-invalid': Boolean(error),
                    autoComplete: 'tel',
                }}
            />
            {error ? (
                <span className="text-[11px] font-semibold text-destructive">
                    {error}
                </span>
            ) : (
                hint && (
                    <span className="text-[11px] leading-4 text-muted-foreground">
                        {hint}
                    </span>
                )
            )}
        </div>
    );
}

export function SettingsToggle({
    label,
    hint,
    checked,
    onChange,
    disabled,
    icon: Icon,
    required = false,
}: {
    label: string;
    hint?: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    disabled: boolean;
    icon?: LucideIcon;
    required?: boolean;
}) {
    return (
        <div
            className={cn(
                'flex items-center justify-between gap-4 rounded-xl border border-border/70 bg-background/50 px-3.5 py-3 transition-colors duration-200',
                !disabled && 'hover:border-gold/40',
            )}
        >
            <span className="min-w-0">
                <span className="block text-xs font-bold text-foreground">
                    {Icon && (
                        <Icon
                            className="mr-1.5 inline size-3.5 align-[-2px] text-muted-foreground"
                            strokeWidth={2}
                        />
                    )}
                    {label}
                    {required && (
                        <span className="ml-1.5 rounded-full bg-amber-500/10 px-1.5 py-0.5 text-[9px] font-extrabold tracking-wide text-amber-700 dark:text-amber-300">
                            Requis
                        </span>
                    )}
                </span>
                {hint && (
                    <span className="mt-0.5 block text-[11px] leading-4 text-muted-foreground">
                        {hint}
                    </span>
                )}
            </span>
            <button
                type="button"
                role="switch"
                aria-checked={checked}
                aria-label={label}
                disabled={disabled}
                onClick={() => onChange(!checked)}
                className={cn(
                    'relative h-[22px] w-10 shrink-0 rounded-full border transition-colors duration-200 outline-none focus-visible:ring-[3px] focus-visible:ring-gold/40 disabled:cursor-not-allowed disabled:opacity-50',
                    checked ? 'border-gold bg-gold' : 'border-border bg-muted',
                )}
            >
                <span
                    className={cn(
                        'absolute top-1/2 size-4 -translate-y-1/2 rounded-full shadow-sm transition-[left,background-color] duration-200',
                        checked
                            ? 'left-[calc(100%-1.125rem)] bg-kora'
                            : 'left-[3px] bg-muted-foreground/60',
                    )}
                />
            </button>
        </div>
    );
}

export function SettingsSecretField({
    label,
    hint,
    value,
    configured,
    disabled,
    cleared,
    error,
    multiline = false,
    icon: Icon,
    required = false,
    source,
    onChange,
    onClear,
}: {
    label: string;
    hint?: string;
    value: string;
    configured: boolean;
    disabled: boolean;
    cleared: boolean;
    error?: string;
    multiline?: boolean;
    icon?: LucideIcon;
    required?: boolean;
    source?: string;
    onChange: (value: string) => void;
    onClear: () => void;
}) {
    return (
        <div className="grid content-start gap-1.5">
            <span className="flex items-center justify-between gap-2">
                <span className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                    {Icon ? (
                        <Icon
                            className="size-3.5 text-muted-foreground"
                            strokeWidth={2}
                        />
                    ) : (
                        <LockKeyhole
                            className="size-3.5 text-muted-foreground"
                            strokeWidth={2}
                        />
                    )}
                    {label}
                    {required && (
                        <span className="rounded-full bg-amber-500/10 px-1.5 py-0.5 text-[9px] font-extrabold tracking-wide text-amber-700 dark:text-amber-300">
                            Requis
                        </span>
                    )}
                </span>
                <span
                    className={cn(
                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-extrabold tracking-[0.08em] uppercase',
                        cleared
                            ? 'bg-destructive/10 text-destructive'
                            : configured
                              ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                              : 'bg-muted text-muted-foreground',
                    )}
                >
                    {cleared
                        ? 'Suppression demandée'
                        : configured
                          ? 'Configuré'
                          : 'Non configuré'}
                </span>
            </span>
            {multiline ? (
                <textarea
                    value={value}
                    disabled={disabled}
                    placeholder={
                        configured
                            ? 'Secret enregistré — saisir pour remplacer'
                            : 'Non configuré'
                    }
                    onChange={(event) => onChange(event.target.value)}
                    aria-invalid={Boolean(error)}
                    rows={6}
                    className="min-h-36 w-full rounded-[10px] border border-border bg-background px-3 py-2 font-mono text-xs shadow-none transition-[border-color,box-shadow] outline-none focus-visible:border-gold focus-visible:ring-[3px] focus-visible:ring-gold/15 disabled:cursor-not-allowed disabled:opacity-50"
                />
            ) : (
                <Input
                    type="password"
                    value={value}
                    disabled={disabled}
                    placeholder={
                        configured
                            ? 'Secret enregistré — saisir pour remplacer'
                            : 'Non configuré'
                    }
                    onChange={(event) => onChange(event.target.value)}
                    aria-invalid={Boolean(error)}
                    className="h-10 rounded-[10px] border-border bg-background text-[13px] shadow-none transition-[border-color,box-shadow] focus-visible:border-gold focus-visible:ring-[3px] focus-visible:ring-gold/15"
                />
            )}
            {source && (
                <span className="text-[10px] leading-4 text-muted-foreground/80">
                    Où le trouver : {source}
                </span>
            )}
            <span className="flex flex-wrap items-center justify-between gap-2">
                {hint ? (
                    <span className="text-[11px] leading-4 text-muted-foreground">
                        {hint}
                    </span>
                ) : (
                    <span />
                )}
                {!disabled && configured && !cleared && (
                    <button
                        type="button"
                        onClick={onClear}
                        className="inline-flex items-center gap-1 text-[11px] font-bold text-destructive underline-offset-4 transition hover:underline"
                    >
                        <EyeOff className="size-3" /> Demander la suppression
                    </button>
                )}
            </span>
            {error && (
                <span className="text-[11px] font-semibold text-destructive">
                    {error}
                </span>
            )}
        </div>
    );
}

export function SettingsSaveBar({
    dirty,
    processing,
    canManage,
    onReset,
    onSave,
}: {
    dirty: boolean;
    processing: boolean;
    canManage: boolean;
    onReset: () => void;
    onSave: () => void;
}) {
    return (
        <div className="sticky bottom-4 z-20 flex flex-col gap-3 rounded-2xl bg-kora/95 px-4 py-3 text-white shadow-[0_18px_45px_rgba(0,29,78,0.35)] backdrop-blur sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-2.5 text-[11px] font-semibold">
                {dirty ? (
                    <>
                        <span className="relative flex size-2">
                            <span className="absolute inline-flex size-full animate-ping rounded-full bg-gold opacity-60" />
                            <span className="relative inline-flex size-2 rounded-full bg-gold" />
                        </span>
                        <span className="text-white/85">
                            Modifications non enregistrées
                        </span>
                    </>
                ) : (
                    <>
                        <CheckCircle2 className="size-4 text-gold" />
                        <span className="text-white/60">
                            Paramètres synchronisés
                        </span>
                    </>
                )}
            </div>
            {canManage && (
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        disabled={!dirty || processing}
                        onClick={onReset}
                        className="inline-flex h-[38px] items-center justify-center gap-[7px] rounded-[10px] border border-white/15 px-[13px] text-[10px] font-black text-white/75 transition-[border-color,color,transform] duration-200 hover:-translate-y-px hover:border-gold/60 hover:text-white disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:translate-y-0"
                    >
                        <RotateCcw className="size-3.5" /> Réinitialiser
                    </button>
                    <Button
                        type="button"
                        variant="kora"
                        size="kora"
                        disabled={!dirty || processing}
                        onClick={onSave}
                    >
                        <Save className="size-3.5" />{' '}
                        {processing ? 'Enregistrement…' : 'Enregistrer'}
                    </Button>
                </div>
            )}
        </div>
    );
}

export function SettingsHelpRail({
    tips,
}: {
    tips: Array<{ title: string; text: string }>;
}) {
    const [index, setIndex] = useState(0);
    const current = tips[index] ?? tips[0];

    if (!current) {
        return null;
    }

    return (
        <aside className="h-fit overflow-hidden rounded-2xl bg-kora bg-[radial-gradient(circle_at_88%_-5%,rgba(0,87,184,0.55),transparent_42%)] p-5 text-white shadow-[0_20px_48px_rgba(0,29,78,0.18)] ring-1 ring-black/10 lg:sticky lg:top-24 dark:ring-white/10">
            <span className="text-[9px] font-black tracking-[0.14em] text-gold uppercase">
                À retenir
            </span>
            <p className="mt-3 text-[13px] font-extrabold tracking-[-0.01em] text-white">
                {current.title}
            </p>
            <p className="mt-1.5 text-[11px] leading-5 text-white/65">
                {current.text}
            </p>
            {tips.length > 1 && (
                <div className="mt-5 flex items-center justify-between border-t border-white/10 pt-4">
                    <span className="font-mono text-[10px] font-semibold text-white/45">
                        {index + 1} / {tips.length}
                    </span>
                    <div className="flex gap-1">
                        <button
                            type="button"
                            aria-label="Conseil précédent"
                            onClick={() =>
                                setIndex(
                                    (value) =>
                                        (value - 1 + tips.length) % tips.length,
                                )
                            }
                            className="grid size-7 place-items-center rounded-lg text-white/70 transition hover:bg-white/10 hover:text-white"
                        >
                            <ChevronLeft className="size-4" />
                        </button>
                        <button
                            type="button"
                            aria-label="Conseil suivant"
                            onClick={() =>
                                setIndex((value) => (value + 1) % tips.length)
                            }
                            className="grid size-7 place-items-center rounded-lg text-white/70 transition hover:bg-white/10 hover:text-white"
                        >
                            <ChevronRight className="size-4" />
                        </button>
                    </div>
                </div>
            )}
        </aside>
    );
}

export function SettingsProbeStatus({
    result,
}: {
    result?: ConnectionProbeResult;
}) {
    if (!result) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-1 text-[10px] font-bold text-muted-foreground">
                <Info className="size-3" /> Aucun test récent
            </span>
        );
    }

    const success = result.status === 'success';
    const unavailable = result.status === 'unavailable';
    const Icon = success ? CheckCircle2 : unavailable ? CircleAlert : XCircle;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-bold',
                success
                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                    : unavailable
                      ? 'bg-gold/15 text-gold'
                      : 'bg-destructive/10 text-destructive',
            )}
        >
            <Icon className="size-3.5" />
            {result.safeMessage}
            {result.checkedAt
                ? ` · ${new Date(result.checkedAt).toLocaleString('fr-FR')}`
                : ''}
        </span>
    );
}
