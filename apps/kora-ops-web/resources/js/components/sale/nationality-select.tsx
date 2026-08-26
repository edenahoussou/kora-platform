import { Check, ChevronDown, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import flags from 'react-phone-number-input/flags';
import phoneLabels from 'react-phone-number-input/locale/fr.json';
import { cn } from '@/lib/utils';

type NationalityOption = {
    code: string;
    label: string;
};

type Props = {
    id: string;
    value: string;
    onChange: (value: string) => void;
    defaultCountry?: string;
};

export function NationalitySelect({
    id,
    value,
    onChange,
    defaultCountry = 'BJ',
}: Props) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);
    const searchInputRef = useRef<HTMLInputElement>(null);

    const options = useMemo<NationalityOption[]>(
        () =>
            Object.entries(phoneLabels)
                .filter(([code]) => code.length === 2)
                .map(([code, label]) => ({ code, label }))
                .sort((a, b) => a.label.localeCompare(b.label, 'fr')),
        [],
    );

    const selected = options.find((option) => option.code === value) ?? null;

    const filtered = useMemo(() => {
        const normalized = query.trim().toLowerCase();

        if (normalized === '') {
            return options;
        }

        return options.filter(
            (option) =>
                option.label.toLowerCase().includes(normalized) ||
                option.code.toLowerCase().includes(normalized),
        );
    }, [options, query]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const timeout = window.setTimeout(() => {
            searchInputRef.current?.focus();
        }, 10);

        return () => window.clearTimeout(timeout);
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const handleClickOutside = (event: MouseEvent) => {
            if (
                containerRef.current &&
                !containerRef.current.contains(event.target as Node)
            ) {
                setOpen(false);
                setQuery('');
            }
        };

        document.addEventListener('mousedown', handleClickOutside);

        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, [open]);

    const handleKeyDown = (event: React.KeyboardEvent) => {
        if (event.key === 'Escape') {
            setOpen(false);
            setQuery('');
        }
    };

    const handleToggle = () => {
        setOpen((current) => {
            if (current) {
                setQuery('');
            }

            return !current;
        });
    };

    const handleSelect = (code: string) => {
        onChange(code);
        setOpen(false);
        setQuery('');
    };

    const renderFlag = (code: string) => {
        const Flag = (flags as Record<string, React.FC<{ title: string }>>)[
            code
        ];

        if (!Flag) {
            return null;
        }

        return <Flag title={code} />;
    };

    return (
        <div
            ref={containerRef}
            className={cn('nationality-select', open && 'is-open')}
            onKeyDown={handleKeyDown}
        >
            <button
                type="button"
                id={id}
                className="control nationality-trigger"
                onClick={handleToggle}
                aria-haspopup="listbox"
                aria-expanded={open}
            >
                {value ? (
                    <span className="nationality-trigger-value">
                        <span className="nationality-flag nationality-flag-sm">
                            {renderFlag(value)}
                        </span>
                        <span>{selected?.label ?? value}</span>
                    </span>
                ) : (
                    <span className="nationality-trigger-placeholder">
                        Sélectionnez une nationalité
                    </span>
                )}
                <ChevronDown
                    className={cn(
                        'nationality-chevron size-3.5',
                        open && 'is-open',
                    )}
                />
            </button>
            {open && (
                <div className="nationality-dropdown" role="listbox">
                    <div className="nationality-search">
                        <Search className="size-3.5" />
                        <input
                            ref={searchInputRef}
                            type="text"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Rechercher un pays…"
                            className="nationality-search-input"
                        />
                    </div>
                    <div className="nationality-options">
                        {filtered.length === 0 ? (
                            <div className="nationality-empty">
                                Aucun pays trouvé.
                            </div>
                        ) : (
                            filtered.map((option) => (
                                <button
                                    key={option.code}
                                    type="button"
                                    className={cn(
                                        'nationality-option',
                                        option.code === value && 'is-selected',
                                    )}
                                    role="option"
                                    aria-selected={option.code === value}
                                    onClick={() => handleSelect(option.code)}
                                >
                                    <span className="nationality-flag nationality-flag-sm">
                                        {renderFlag(option.code)}
                                    </span>
                                    <span className="nationality-option-label">
                                        {option.label}
                                    </span>
                                    <small className="nationality-option-code">
                                        {option.code}
                                    </small>
                                    {option.code === value && (
                                        <Check className="nationality-option-check size-3.5" />
                                    )}
                                </button>
                            ))
                        )}
                    </div>
                </div>
            )}
            {!value && (
                <input
                    type="hidden"
                    name={id}
                    value={defaultCountry}
                    data-default-nationality={defaultCountry}
                />
            )}
        </div>
    );
}
