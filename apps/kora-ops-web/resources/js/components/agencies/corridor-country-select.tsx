import { useId, useMemo, useState } from 'react';
import type { CorridorCountryCode } from '@/components/corridor-flags';
import { CORRIDOR_COUNTRIES } from '@/components/corridor-flags';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

interface CorridorCountrySelectProps {
    id?: string;
    name?: string;
    defaultValue?: string;
    value?: string;
    onChange?: (value: string) => void;
    disabled?: boolean;
    className?: string;
}

export function CorridorCountrySelect({
    id,
    name = 'country',
    defaultValue = 'BJ',
    value,
    onChange,
    disabled = false,
    className,
}: CorridorCountrySelectProps) {
    const generatedId = useId();
    const inputId = id ?? generatedId;
    const [internalValue, setInternalValue] = useState<string>(
        value ?? defaultValue ?? 'BJ',
    );

    const currentValue = value ?? internalValue;

    const activeCountry = useMemo(() => {
        return (
            CORRIDOR_COUNTRIES.find(
                (c) => c.code.toUpperCase() === currentValue.toUpperCase(),
            ) ?? {
                code: currentValue.toUpperCase() as CorridorCountryCode,
                name: currentValue,
                capital: '',
                currency: '',
                timezone: '',
                hub: 'Corridor KORA',
                flag: CORRIDOR_COUNTRIES[0].flag,
            }
        );
    }, [currentValue]);

    const handleSelect = (nextCode: string) => {
        setInternalValue(nextCode);
        onChange?.(nextCode);
    };

    const ActiveFlag = activeCountry.flag;

    return (
        <div className="relative">
            <input
                type="hidden"
                id={inputId}
                name={name}
                value={currentValue}
            />

            <Select
                value={currentValue}
                onValueChange={handleSelect}
                disabled={disabled}
            >
                <SelectTrigger
                    className={cn(
                        'h-11 min-h-[42px] w-full rounded-[10px] border border-border bg-card px-3 text-xs font-bold text-foreground shadow-xs transition-colors hover:border-gold/60 focus-visible:border-gold focus-visible:ring-2 focus-visible:ring-gold/20',
                        className,
                    )}
                >
                    <div className="flex items-center gap-2.5 truncate">
                        <span className="size-5 shrink-0 overflow-hidden rounded-full shadow-xs ring-1 ring-border">
                            <ActiveFlag className="size-full" />
                        </span>
                        <span className="font-bold text-foreground">
                            {activeCountry.name}
                        </span>
                        <span className="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] font-extrabold text-gold">
                            {activeCountry.code}
                        </span>
                    </div>
                </SelectTrigger>

                <SelectContent className="rounded-xl border border-border bg-card p-1 shadow-[0_16px_36px_rgba(0,29,78,0.2)]">
                    {CORRIDOR_COUNTRIES.map((country) => {
                        const Flag = country.flag;
                        const isSelected = country.code === currentValue;

                        return (
                            <SelectItem
                                key={country.code}
                                value={country.code}
                                className={cn(
                                    'cursor-pointer rounded-lg px-2.5 py-2 transition-colors',
                                    isSelected
                                        ? 'bg-gold/15 font-semibold text-foreground'
                                        : 'hover:bg-muted/70',
                                )}
                            >
                                <div className="flex items-center gap-2.5">
                                    <span className="size-5 shrink-0 overflow-hidden rounded-full shadow-xs ring-1 ring-border">
                                        <Flag className="size-full" />
                                    </span>
                                    <div className="flex flex-col text-left">
                                        <div className="flex items-center gap-1.5 leading-tight">
                                            <span className="text-sm font-medium">
                                                {country.name}
                                            </span>
                                            <span className="font-mono text-[11px] font-bold text-gold">
                                                ({country.code})
                                            </span>
                                        </div>
                                        <span className="text-[10px] text-muted-foreground">
                                            {country.hub} · {country.currency}
                                        </span>
                                    </div>
                                </div>
                            </SelectItem>
                        );
                    })}
                </SelectContent>
            </Select>
        </div>
    );
}
