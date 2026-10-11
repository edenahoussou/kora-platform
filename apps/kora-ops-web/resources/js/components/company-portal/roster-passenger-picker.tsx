import {
    AlertCircle,
    Armchair,
    CalendarDays,
    CheckCircle2,
    ChevronDown,
    FileText,
    Globe,
    Hash,
    Mail,
    Phone,
    Search,
    Syringe,
    UserRound,
    X,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { NationalitySelect } from '@/components/sale/nationality-select';
import { cn } from '@/lib/utils';

export type CompanyPortalTraveler = {
    uuid: string;
    firstName: string;
    lastName: string;
    phone: string;
    email: string | null;
    documentType: string | null;
    documentTypeLabel: string | null;
    documentNumber: string | null;
    nationality: string | null;
    birthDate: string | null;
    yellowFeverVaccinated: boolean | null;
};

export type CompanyPortalPassengerSelection = {
    passengerIndex: number;
    travelerUuid: string;
    documentType: string;
    documentNumber: string;
    nationality: string;
    birthDate: string;
    yellowFeverVaccinated: boolean | null;
};

export type PassengerSeat = {
    code: string;
    direction?: 'outbound' | 'inbound';
};

type Props = {
    roster: CompanyPortalTraveler[];
    selections: CompanyPortalPassengerSelection[];
    seats?: Map<number, PassengerSeat[]>;
    onChange: (selections: CompanyPortalPassengerSelection[]) => void;
};

const documentTypes = [
    ['national_id', 'Carte nationale'],
    ['biometric_card', 'Carte biométrique'],
    ['passport', 'Passeport'],
    ['consular_card', 'Carte consulaire'],
];

const MAX_SUGGESTIONS = 10;

function normalize(value: string): string {
    return value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function matchesQuery(traveler: CompanyPortalTraveler, query: string): boolean {
    const haystack = normalize(
        `${traveler.firstName} ${traveler.lastName} ${traveler.phone} ${traveler.email ?? ''}`,
    );

    return normalize(query)
        .split(/\s+/)
        .filter(Boolean)
        .every((token) => haystack.includes(token));
}

function formatBirthDate(dateStr: string | null | undefined): string {
    if (!dateStr) {
        return '';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(dateStr.trim());

    if (match) {
        const [, year, month, day] = match;

        return `${day}/${month}/${year}`;
    }

    try {
        const date = new Date(dateStr);

        if (!isNaN(date.getTime())) {
            const d = String(date.getDate()).padStart(2, '0');
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const y = date.getFullYear();

            return `${d}/${m}/${y}`;
        }
    } catch {
        // Fallback
    }

    return dateStr;
}

function seatChipLabel(seat: PassengerSeat, isMultiSegment: boolean): string {
    if (!isMultiSegment) {
        return seat.code;
    }

    if (seat.direction === 'outbound') {
        return `${seat.code} · Aller`;
    }

    if (seat.direction === 'inbound') {
        return `${seat.code} · Retour`;
    }

    return seat.code;
}

function isRowComplete(
    traveler: CompanyPortalTraveler | null | undefined,
    selection: CompanyPortalPassengerSelection,
): boolean {
    if (!traveler) {
        return false;
    }

    const hasDocType = Boolean(
        traveler.documentType || selection.documentType.trim(),
    );
    const hasDocNumber = Boolean(
        traveler.documentNumber || selection.documentNumber.trim(),
    );
    const hasNationality = Boolean(
        traveler.nationality || selection.nationality.trim(),
    );
    const hasBirthDate = Boolean(
        traveler.birthDate || selection.birthDate.trim(),
    );
    const hasVaccine =
        traveler.yellowFeverVaccinated !== null ||
        selection.yellowFeverVaccinated !== null;

    return (
        hasDocType &&
        hasDocNumber &&
        hasNationality &&
        hasBirthDate &&
        hasVaccine
    );
}

function hasMissingDetails(
    traveler: CompanyPortalTraveler | null | undefined,
    selection: CompanyPortalPassengerSelection,
): boolean {
    if (!traveler) {
        return false;
    }

    return !isRowComplete(traveler, selection);
}

export function RosterPassengerPicker({
    roster,
    selections,
    seats,
    onChange,
}: Props) {
    const update = (
        passengerIndex: number,
        changes: Partial<CompanyPortalPassengerSelection>,
    ) => {
        onChange(
            selections.map((selection) =>
                selection.passengerIndex === passengerIndex
                    ? { ...selection, ...changes }
                    : selection,
            ),
        );
    };

    return (
        <section
            className="divide-y divide-border rounded-2xl border border-border bg-card"
            aria-label="Voyageurs de la réservation"
        >
            {selections.map((selection) => {
                const traveler = roster.find(
                    (candidate) => candidate.uuid === selection.travelerUuid,
                );
                const chosenElsewhere = new Set(
                    selections
                        .filter(
                            (candidate) =>
                                candidate.passengerIndex !==
                                selection.passengerIndex,
                        )
                        .map((candidate) => candidate.travelerUuid)
                        .filter(Boolean),
                );

                return (
                    <PassengerSeatRow
                        key={selection.passengerIndex}
                        selection={selection}
                        traveler={traveler}
                        roster={roster}
                        chosenElsewhere={chosenElsewhere}
                        passengerSeats={
                            seats?.get(selection.passengerIndex) ?? []
                        }
                        onUpdate={(changes) =>
                            update(selection.passengerIndex, changes)
                        }
                    />
                );
            })}
        </section>
    );
}

function PassengerSeatRow({
    selection,
    traveler,
    roster,
    chosenElsewhere,
    passengerSeats,
    onUpdate,
}: {
    selection: CompanyPortalPassengerSelection;
    traveler: CompanyPortalTraveler | null | undefined;
    roster: CompanyPortalTraveler[];
    chosenElsewhere: Set<string>;
    passengerSeats: PassengerSeat[];
    onUpdate: (changes: Partial<CompanyPortalPassengerSelection>) => void;
}) {
    const isComplete = isRowComplete(traveler, selection);
    const isMissing = traveler ? !isComplete : false;
    const [expanded, setExpanded] = useState(() => isMissing);

    const handleSelectTraveler = (
        selectedTraveler: CompanyPortalTraveler | null,
    ) => {
        const changes: Partial<CompanyPortalPassengerSelection> = {
            travelerUuid: selectedTraveler?.uuid ?? '',
            documentType: selectedTraveler?.documentType ?? '',
            documentNumber: selectedTraveler?.documentNumber ?? '',
            nationality: selectedTraveler?.nationality ?? '',
            birthDate: selectedTraveler?.birthDate ?? '',
            yellowFeverVaccinated:
                selectedTraveler?.yellowFeverVaccinated ?? null,
        };

        onUpdate(changes);

        if (selectedTraveler) {
            const nextSelection: CompanyPortalPassengerSelection = {
                ...selection,
                ...changes,
            };

            if (hasMissingDetails(selectedTraveler, nextSelection)) {
                setExpanded(true);
            } else {
                setExpanded(false);
            }
        } else {
            setExpanded(false);
        }
    };

    const isVaccinated =
        selection.yellowFeverVaccinated ??
        traveler?.yellowFeverVaccinated ??
        false;

    return (
        <article className="p-4 transition-colors">
            <div className="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:gap-3">
                <div className="flex shrink-0 items-center gap-1.5">
                    {passengerSeats.length > 0 ? (
                        passengerSeats.map((seat, seatIdx) => (
                            <span
                                key={seatIdx}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-gold/20 bg-gold/10 px-2.5 py-1.5 text-xs font-bold text-gold"
                            >
                                <Armchair className="size-3.5 shrink-0" />
                                <span>
                                    {seatChipLabel(
                                        seat,
                                        passengerSeats.length > 1,
                                    )}
                                </span>
                            </span>
                        ))
                    ) : (
                        <span className="inline-flex items-center gap-1.5 rounded-lg border border-gold/20 bg-gold/10 px-2.5 py-1.5 text-xs font-bold text-gold">
                            <UserRound className="size-3.5 shrink-0" />
                            <span>Siège {selection.passengerIndex + 1}</span>
                        </span>
                    )}
                </div>

                <div className="min-w-0 flex-1">
                    <TravelerSearch
                        roster={roster}
                        excludedUuids={chosenElsewhere}
                        selected={traveler ?? null}
                        onSelect={handleSelectTraveler}
                    />
                </div>

                {traveler ? (
                    <button
                        type="button"
                        onClick={() => setExpanded((prev) => !prev)}
                        className="flex shrink-0 items-center gap-2 rounded-lg border border-border/60 bg-muted/40 px-2.5 py-1.5 text-xs font-semibold transition-all hover:border-border hover:bg-muted"
                        aria-expanded={expanded}
                        aria-label={
                            expanded
                                ? 'Masquer les informations du voyageur'
                                : 'Afficher les informations du voyageur'
                        }
                    >
                        {isComplete ? (
                            <span className="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                                <CheckCircle2 className="size-4 shrink-0" />
                                <span className="hidden sm:inline">
                                    Complet
                                </span>
                            </span>
                        ) : (
                            <span className="flex items-center gap-1.5 text-amber-600 dark:text-amber-400">
                                <AlertCircle className="size-4 shrink-0" />
                                <span>À compléter</span>
                            </span>
                        )}
                        <ChevronDown
                            className={cn(
                                'size-4 text-muted-foreground transition-transform duration-200',
                                expanded && 'rotate-180',
                            )}
                        />
                    </button>
                ) : (
                    <div className="hidden sm:block sm:w-2" />
                )}
            </div>

            {/* Expandable details panel with smooth transition */}
            <div
                className={cn(
                    'grid transition-[grid-template-rows,opacity] duration-300 ease-out',
                    expanded && traveler
                        ? 'grid-rows-[1fr] opacity-100'
                        : 'pointer-events-none grid-rows-[0fr] opacity-0',
                )}
            >
                <div
                    className={cn(
                        'min-h-0',
                        expanded ? 'overflow-visible' : 'overflow-hidden',
                    )}
                >
                    {traveler && (
                        <div className="mt-3.5 space-y-3.5 border-t border-border/60 pt-3.5">
                            <div className="grid gap-2.5 rounded-xl border border-border/60 bg-muted/40 p-3 sm:grid-cols-2">
                                <div className="flex items-center gap-2 text-xs">
                                    <UserRound className="size-3.5 shrink-0 text-gold" />
                                    <span className="text-muted-foreground">
                                        Identité :
                                    </span>
                                    <strong className="font-semibold text-foreground">
                                        {traveler.firstName} {traveler.lastName}
                                    </strong>
                                </div>
                                <div className="flex items-center gap-2 text-xs">
                                    <Phone className="size-3.5 shrink-0 text-gold" />
                                    <span className="text-muted-foreground">
                                        Téléphone :
                                    </span>
                                    <strong className="font-semibold text-foreground">
                                        {traveler.phone}
                                    </strong>
                                </div>
                                {traveler.email && (
                                    <div className="flex items-center gap-2 text-xs sm:col-span-2">
                                        <Mail className="size-3.5 shrink-0 text-gold" />
                                        <span className="text-muted-foreground">
                                            Email :
                                        </span>
                                        <span className="text-foreground">
                                            {traveler.email}
                                        </span>
                                    </div>
                                )}
                            </div>

                            <div className="grid gap-3.5 sm:grid-cols-2">
                                <div className="field">
                                    <span className="field-label flex items-center justify-between text-xs font-semibold text-foreground">
                                        <span className="flex items-center gap-1.5">
                                            <FileText className="size-3.5 text-muted-foreground" />
                                            Type de document
                                        </span>
                                        <span className="text-[10px] font-normal text-muted-foreground">
                                            {selection.documentNumber.trim() ||
                                            traveler.documentNumber
                                                ? 'Requis avec numéro'
                                                : 'Optionnel'}
                                        </span>
                                    </span>
                                    {traveler.documentType ? (
                                        <div className="control flex items-center justify-between bg-muted/30 px-3 py-2 text-sm text-foreground">
                                            <span>
                                                {traveler.documentTypeLabel ??
                                                    traveler.documentType}
                                            </span>
                                            <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground">
                                                Profil
                                            </span>
                                        </div>
                                    ) : (
                                        <select
                                            className="control"
                                            value={selection.documentType}
                                            onChange={(event) =>
                                                onUpdate({
                                                    documentType:
                                                        event.target.value,
                                                })
                                            }
                                        >
                                            <option value="">
                                                Sélectionner un type…
                                            </option>
                                            {documentTypes.map(
                                                ([value, label]) => (
                                                    <option
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                    )}
                                    <small className="text-[11px] text-muted-foreground">
                                        Carte nationale, biométrique, passeport
                                        ou consulaire.
                                    </small>
                                </div>

                                <div className="field">
                                    <span className="field-label flex items-center justify-between text-xs font-semibold text-foreground">
                                        <span className="flex items-center gap-1.5">
                                            <Hash className="size-3.5 text-muted-foreground" />
                                            Numéro de pièce
                                        </span>
                                        <span className="text-[10px] font-normal text-muted-foreground">
                                            {selection.documentType ||
                                            traveler.documentType
                                                ? 'Requis avec type'
                                                : 'Optionnel'}
                                        </span>
                                    </span>
                                    {traveler.documentNumber ? (
                                        <div className="control flex items-center justify-between bg-muted/30 px-3 py-2 font-mono text-sm text-foreground">
                                            <span>
                                                {traveler.documentNumber}
                                            </span>
                                            <span className="rounded bg-muted px-1.5 py-0.5 font-sans text-[10px] font-semibold text-muted-foreground">
                                                Profil
                                            </span>
                                        </div>
                                    ) : (
                                        <input
                                            className="control font-mono"
                                            placeholder="Ex : CI0029384729"
                                            value={selection.documentNumber}
                                            onChange={(event) =>
                                                onUpdate({
                                                    documentNumber:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    )}
                                    <small className="text-[11px] text-muted-foreground">
                                        Numéro officiel figurant sur la pièce
                                        d’identité.
                                    </small>
                                </div>

                                <div className="field">
                                    <span className="field-label flex items-center justify-between text-xs font-semibold text-foreground">
                                        <span className="flex items-center gap-1.5">
                                            <Globe className="size-3.5 text-muted-foreground" />
                                            Nationalité
                                        </span>
                                        <span className="text-[10px] font-normal text-muted-foreground">
                                            Optionnelle
                                        </span>
                                    </span>
                                    <NationalitySelect
                                        id={`passenger-${selection.passengerIndex}-nationality`}
                                        value={
                                            selection.nationality ||
                                            traveler.nationality ||
                                            ''
                                        }
                                        onChange={(value) =>
                                            onUpdate({ nationality: value })
                                        }
                                        defaultCountry={
                                            traveler.nationality || 'BJ'
                                        }
                                    />
                                    <small className="text-[11px] text-muted-foreground">
                                        Sélectionnez le pays pour associer le
                                        drapeau et le code.
                                    </small>
                                </div>

                                <div className="field">
                                    <span className="field-label flex items-center justify-between text-xs font-semibold text-foreground">
                                        <span className="flex items-center gap-1.5">
                                            <CalendarDays className="size-3.5 text-muted-foreground" />
                                            Date de naissance
                                        </span>
                                        <span className="text-[10px] font-normal text-muted-foreground">
                                            Optionnelle
                                        </span>
                                    </span>
                                    {traveler.birthDate ? (
                                        <div className="control flex items-center justify-between bg-muted/30 px-3 py-2 text-sm text-foreground">
                                            <span className="font-medium">
                                                {formatBirthDate(
                                                    traveler.birthDate,
                                                )}
                                            </span>
                                            <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground">
                                                Profil
                                            </span>
                                        </div>
                                    ) : (
                                        <input
                                            id={`passenger-${selection.passengerIndex}-birth-date`}
                                            className="control"
                                            type="date"
                                            value={selection.birthDate}
                                            onChange={(event) =>
                                                onUpdate({
                                                    birthDate:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    )}
                                    <small className="text-[11px] text-muted-foreground">
                                        {traveler.birthDate
                                            ? 'Date renseignée dans le profil voyageur (JJ/MM/AAAA).'
                                            : 'Format : Jour / Mois / Année (JJ/MM/AAAA).'}
                                    </small>
                                </div>

                                <div className="sm:col-span-2">
                                    <div className="flex items-center justify-between rounded-xl border border-border/80 bg-background/50 p-3 transition-colors hover:border-border">
                                        <div className="flex items-start gap-2.5">
                                            <Syringe className="mt-0.5 size-4 shrink-0 text-gold" />
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-xs font-bold text-foreground">
                                                        Vaccination fièvre jaune
                                                    </span>
                                                    <span className="text-[10px] font-semibold text-muted-foreground">
                                                        · Évolutif
                                                    </span>
                                                </div>
                                                <p className="mt-0.5 text-[11px] text-muted-foreground">
                                                    {isVaccinated
                                                        ? 'Carnet de vaccination international valide et à jour'
                                                        : 'Non vacciné ou attestation non renseignée'}
                                                </p>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            role="switch"
                                            aria-checked={Boolean(isVaccinated)}
                                            aria-label="Statut vaccination fièvre jaune"
                                            onClick={() =>
                                                onUpdate({
                                                    yellowFeverVaccinated:
                                                        !isVaccinated,
                                                })
                                            }
                                            className={cn(
                                                'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus-visible:ring-2 focus-visible:ring-gold/50 focus-visible:outline-none',
                                                isVaccinated
                                                    ? 'bg-gold'
                                                    : 'bg-muted-foreground/30',
                                            )}
                                        >
                                            <span
                                                className={cn(
                                                    'pointer-events-none inline-block size-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out',
                                                    isVaccinated
                                                        ? 'translate-x-5'
                                                        : 'translate-x-0',
                                                )}
                                            />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </article>
    );
}

function TravelerSearch({
    roster,
    excludedUuids,
    selected,
    onSelect,
}: {
    roster: CompanyPortalTraveler[];
    excludedUuids: Set<string>;
    selected: CompanyPortalTraveler | null;
    onSelect: (traveler: CompanyPortalTraveler | null) => void;
}) {
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const blurTimeout = useRef<number | null>(null);

    const candidates = useMemo(() => {
        const available = roster.filter(
            (candidate) =>
                candidate.uuid === selected?.uuid ||
                !excludedUuids.has(candidate.uuid),
        );

        if (!query.trim()) {
            return available;
        }

        return available.filter((candidate) => matchesQuery(candidate, query));
    }, [roster, excludedUuids, selected?.uuid, query]);

    const visible = candidates.slice(0, MAX_SUGGESTIONS);
    const hiddenCount = candidates.length - visible.length;

    const choose = (traveler: CompanyPortalTraveler) => {
        onSelect(traveler);
        setQuery('');
        setOpen(false);
    };

    return (
        <div className="relative">
            <div
                className={cn(
                    'group relative flex items-center gap-2.5 rounded-xl border border-border/80 bg-background/80 px-3 py-1.5 transition-all duration-200 ease-out',
                    'hover:border-gold/40 hover:bg-background',
                    'focus-within:border-gold focus-within:bg-background focus-within:shadow-[0_0_12px_rgba(246,176,0,0.12)] focus-within:ring-2 focus-within:ring-gold/20',
                    selected && 'border-border/60 bg-muted/20',
                )}
            >
                <Search
                    className={cn(
                        'size-4 shrink-0 text-muted-foreground transition-colors duration-200',
                        'group-focus-within:text-gold',
                    )}
                />
                <input
                    type="text"
                    className="min-w-0 flex-1 bg-transparent p-0 font-sans text-sm text-foreground outline-none placeholder:text-muted-foreground/70"
                    placeholder="Rechercher par nom, prénom ou téléphone…"
                    value={
                        query !== ''
                            ? query
                            : selected
                              ? `${selected.firstName} ${selected.lastName}`
                              : ''
                    }
                    onFocus={() => {
                        if (blurTimeout.current !== null) {
                            window.clearTimeout(blurTimeout.current);
                        }

                        setOpen(true);
                    }}
                    onBlur={() => {
                        blurTimeout.current = window.setTimeout(
                            () => setOpen(false),
                            120,
                        );
                    }}
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setOpen(true);

                        if (selected) {
                            onSelect(null);
                        }
                    }}
                    onKeyDown={(event) => {
                        if (event.key === 'Escape') {
                            setOpen(false);
                            event.currentTarget.blur();
                        }

                        if (
                            event.key === 'Enter' &&
                            open &&
                            visible.length > 0
                        ) {
                            event.preventDefault();
                            choose(visible[0]);
                        }
                    }}
                    role="combobox"
                    aria-expanded={open}
                    aria-label="Rechercher un voyageur"
                />
                {selected && !open && (
                    <button
                        type="button"
                        className="grid size-6 shrink-0 place-items-center rounded-md text-muted-foreground/70 transition-colors hover:bg-muted hover:text-foreground"
                        onClick={() => {
                            onSelect(null);
                            setQuery('');
                        }}
                        title="Retirer ce voyageur"
                        aria-label="Retirer ce voyageur"
                    >
                        <X className="size-3.5" />
                    </button>
                )}
            </div>

            {open && (
                <ul
                    className="absolute top-full left-0 z-20 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-border bg-popover p-1 shadow-lg"
                    role="listbox"
                >
                    {visible.map((candidate) => (
                        <li key={candidate.uuid}>
                            <button
                                type="button"
                                role="option"
                                aria-selected={
                                    candidate.uuid === selected?.uuid
                                }
                                className={cn(
                                    'flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm transition-colors hover:bg-muted',
                                    candidate.uuid === selected?.uuid &&
                                        'bg-gold/10 font-bold',
                                )}
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => choose(candidate)}
                            >
                                <span className="font-bold text-foreground">
                                    {candidate.firstName} {candidate.lastName}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {candidate.phone}
                                </span>
                            </button>
                        </li>
                    ))}
                    {visible.length === 0 && (
                        <li className="px-3 py-2 text-sm text-muted-foreground">
                            {query.trim()
                                ? 'Aucun voyageur ne correspond à cette recherche.'
                                : 'Aucun voyageur disponible.'}
                        </li>
                    )}
                    {hiddenCount > 0 && (
                        <li className="px-3 py-2 text-xs text-muted-foreground">
                            {hiddenCount} autre
                            {hiddenCount > 1 ? 's' : ''} — affinez la recherche.
                        </li>
                    )}
                </ul>
            )}
        </div>
    );
}
