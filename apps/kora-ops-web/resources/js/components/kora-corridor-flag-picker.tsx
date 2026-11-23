import { ChevronDown, Globe2, MapPin, Radio } from 'lucide-react';
import { useState } from 'react';
import type { CorridorCountryCode } from '@/components/corridor-flags';
import { CORRIDOR_COUNTRIES } from '@/components/corridor-flags';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

interface KoraCorridorFlagPickerProps {
    className?: string;
    defaultCountry?: CorridorCountryCode;
    onCountryChange?: (code: CorridorCountryCode) => void;
}

/**
 * Sélecteur visuel du corridor transnational KORA (Bénin · Togo · Ghana · Côte d'Ivoire).
 * Présente un groupe d'avatars circulaires superposés (Avatar Group shadcn-style)
 * avec anneaux de séparation, relief et sélection dynamique.
 */
export function KoraCorridorFlagPicker({
    className,
    defaultCountry = 'BJ',
    onCountryChange,
}: KoraCorridorFlagPickerProps) {
    const [selectedCode, setSelectedCode] =
        useState<CorridorCountryCode>(defaultCountry);

    const activeCountry =
        CORRIDOR_COUNTRIES.find((c) => c.code === selectedCode) ??
        CORRIDOR_COUNTRIES[0];

    const handleSelect = (code: CorridorCountryCode) => {
        setSelectedCode(code);
        onCountryChange?.(code);
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className={cn(
                        'group flex h-[2.625rem] items-center gap-2 rounded-full border border-border bg-card px-2 text-xs font-bold text-foreground shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-gold/60 hover:shadow-sm focus-visible:ring-2 focus-visible:ring-gold/30 focus-visible:outline-none',
                        className,
                    )}
                    aria-label={`Corridor KORA — ${activeCountry.name} actif`}
                >
                    {/* Groupe d'avatars circulaires superposés (AvatarGroup Shadcn Style) */}
                    <div className="flex items-center -space-x-2 overflow-visible py-0.5">
                        {CORRIDOR_COUNTRIES.map((country, idx) => {
                            const Flag = country.flag;
                            const isCurrent =
                                country.code === activeCountry.code;

                            return (
                                <div
                                    key={country.code}
                                    style={{ zIndex: isCurrent ? 10 : 4 - idx }}
                                    className={cn(
                                        'relative size-6 shrink-0 overflow-hidden rounded-full shadow-xs ring-2 transition-transform duration-200 group-hover:scale-105',
                                        isCurrent
                                            ? 'scale-110 shadow-[0_0_10px_rgba(246,176,0,0.45)] ring-gold'
                                            : 'hidden opacity-90 ring-card group-hover:opacity-100 min-[400px]:block',
                                    )}
                                    title={`${country.name} (${country.code})`}
                                >
                                    <Flag className="size-full" />
                                </div>
                            );
                        })}
                    </div>

                    {/* Kicker corridor actif */}
                    <span className="hidden items-center gap-1.5 pl-1 font-mono text-[11px] font-black tracking-wider text-foreground sm:inline-flex">
                        <span className="text-gold">{activeCountry.code}</span>
                        <span className="font-normal text-muted-foreground/40">
                            /
                        </span>
                        <span className="text-[10px] font-bold text-muted-foreground">
                            Corridor
                        </span>
                    </span>

                    <ChevronDown className="hidden size-3 text-muted-foreground transition-transform duration-200 group-data-[state=open]:rotate-180 min-[400px]:block" />
                </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent
                align="end"
                sideOffset={8}
                className="w-80 rounded-2xl border-border bg-card p-2 shadow-[0_18px_45px_rgba(0,29,78,0.18)]"
            >
                <DropdownMenuLabel className="px-2.5 py-2">
                    <div className="flex items-center justify-between">
                        <span className="flex items-center gap-1.5 text-xs font-extrabold tracking-[-0.01em] text-foreground">
                            <Globe2 className="size-3.5 text-gold" />
                            Corridor Transnational KORA
                        </span>
                        <span className="rounded-full bg-gold/15 px-2 py-0.5 font-mono text-[9px] font-black text-gold">
                            4 Pays
                        </span>
                    </div>
                    <p className="mt-1 text-[11px] leading-4 font-normal text-muted-foreground">
                        Axes d'exploitation interconnectés du réseau de
                        transport KORA.
                    </p>
                </DropdownMenuLabel>

                <DropdownMenuSeparator className="my-1 border-border/70" />

                <div className="space-y-1">
                    {CORRIDOR_COUNTRIES.map((country) => {
                        const Flag = country.flag;
                        const isCurrent = country.code === activeCountry.code;

                        return (
                            <DropdownMenuItem
                                key={country.code}
                                onClick={() => handleSelect(country.code)}
                                className={cn(
                                    'flex cursor-pointer items-center justify-between rounded-xl px-2.5 py-2 transition-colors',
                                    isCurrent
                                        ? 'bg-kora text-white dark:bg-gold/15 dark:text-gold'
                                        : 'hover:bg-muted/70',
                                )}
                            >
                                <div className="flex items-center gap-2.5">
                                    <span className="size-7 shrink-0 overflow-hidden rounded-full shadow-xs ring-2 ring-black/10 dark:ring-white/10">
                                        <Flag className="size-full" />
                                    </span>
                                    <div>
                                        <div className="flex items-center gap-1.5">
                                            <span className="text-xs leading-tight font-bold">
                                                {country.name}
                                            </span>
                                            <span
                                                className={cn(
                                                    'font-mono text-[10px] font-extrabold',
                                                    isCurrent
                                                        ? 'text-gold dark:text-gold'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                ({country.code})
                                            </span>
                                        </div>
                                        <p
                                            className={cn(
                                                'flex items-center gap-1 text-[10px] leading-tight',
                                                isCurrent
                                                    ? 'text-white/70 dark:text-gold/80'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            <MapPin className="size-2.5" />
                                            {country.hub}
                                        </p>
                                    </div>
                                </div>

                                <div className="text-right">
                                    <span
                                        className={cn(
                                            'block font-mono text-[10px] font-extrabold',
                                            isCurrent
                                                ? 'text-white dark:text-gold'
                                                : 'text-foreground',
                                        )}
                                    >
                                        {country.currency}
                                    </span>
                                    <span
                                        className={cn(
                                            'block text-[9px]',
                                            isCurrent
                                                ? 'text-white/60 dark:text-gold/70'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {country.timezone}
                                    </span>
                                </div>
                            </DropdownMenuItem>
                        );
                    })}
                </div>

                <DropdownMenuSeparator className="my-1 border-border/70" />

                <div className="flex items-center justify-between px-2.5 py-1.5 text-[10px] text-muted-foreground">
                    <span className="flex items-center gap-1">
                        <Radio className="size-2.5 animate-pulse text-emerald-500" />
                        Liaisons transfrontalières actives
                    </span>
                    <span className="font-mono font-bold text-gold">
                        FR / EN
                    </span>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
