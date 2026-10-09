import { Search, UserRound, X } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
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

type Props = {
    roster: CompanyPortalTraveler[];
    selections: CompanyPortalPassengerSelection[];
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

export function RosterPassengerPicker({ roster, selections, onChange }: Props) {
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
            className="grid gap-4"
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
                    <article
                        key={selection.passengerIndex}
                        className="rounded-2xl border border-border bg-card p-4"
                    >
                        <div className="flex items-center gap-3">
                            <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-gold/10 text-gold">
                                <UserRound className="size-4" />
                            </span>
                            <div>
                                <span className="section-kicker">
                                    Voyageur {selection.passengerIndex + 1}
                                </span>
                                <h3 className="text-sm font-extrabold text-foreground">
                                    {traveler
                                        ? `${traveler.firstName} ${traveler.lastName}`
                                        : 'Sélectionner un voyageur'}
                                </h3>
                            </div>
                        </div>

                        <TravelerSearch
                            roster={roster}
                            excludedUuids={chosenElsewhere}
                            selected={traveler ?? null}
                            onSelect={(selectedTraveler) =>
                                update(selection.passengerIndex, {
                                    travelerUuid: selectedTraveler?.uuid ?? '',
                                    documentType:
                                        selectedTraveler?.documentType ?? '',
                                    documentNumber:
                                        selectedTraveler?.documentNumber ?? '',
                                    nationality:
                                        selectedTraveler?.nationality ?? '',
                                    birthDate:
                                        selectedTraveler?.birthDate ?? '',
                                    yellowFeverVaccinated:
                                        selectedTraveler?.yellowFeverVaccinated ??
                                        null,
                                })
                            }
                        />

                        {traveler && (
                            <>
                                <div className="mt-3 grid gap-2 rounded-xl bg-muted/50 p-3 text-xs sm:grid-cols-2">
                                    <span className="text-muted-foreground">
                                        Identité
                                        <strong className="ml-2 text-foreground">
                                            {traveler.firstName}{' '}
                                            {traveler.lastName}
                                        </strong>
                                    </span>
                                    <span className="text-muted-foreground">
                                        Téléphone
                                        <strong className="ml-2 text-foreground">
                                            {traveler.phone}
                                        </strong>
                                    </span>
                                </div>
                                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                    {traveler.documentType ? (
                                        <ReadOnlyDetail
                                            label="Pièce d’identité"
                                            value={
                                                traveler.documentTypeLabel ??
                                                traveler.documentType
                                            }
                                        />
                                    ) : (
                                        <label className="field">
                                            <span>Pièce d’identité</span>
                                            <select
                                                className="control"
                                                value={selection.documentType}
                                                onChange={(event) =>
                                                    update(
                                                        selection.passengerIndex,
                                                        {
                                                            documentType:
                                                                event.target
                                                                    .value,
                                                        },
                                                    )
                                                }
                                            >
                                                <option value="">
                                                    À compléter
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
                                        </label>
                                    )}
                                    {traveler.documentNumber ? (
                                        <ReadOnlyDetail
                                            label="Numéro de pièce"
                                            value={traveler.documentNumber}
                                        />
                                    ) : (
                                        <EditableDetail
                                            label="Numéro de pièce"
                                            value={selection.documentNumber}
                                            onChange={(documentNumber) =>
                                                update(
                                                    selection.passengerIndex,
                                                    { documentNumber },
                                                )
                                            }
                                        />
                                    )}
                                    {!traveler.nationality && (
                                        <EditableDetail
                                            label="Nationalité (ISO)"
                                            value={selection.nationality}
                                            maxLength={2}
                                            onChange={(nationality) =>
                                                update(
                                                    selection.passengerIndex,
                                                    {
                                                        nationality:
                                                            nationality.toUpperCase(),
                                                    },
                                                )
                                            }
                                        />
                                    )}
                                    {!traveler.birthDate && (
                                        <EditableDetail
                                            label="Date de naissance"
                                            type="date"
                                            value={selection.birthDate}
                                            onChange={(birthDate) =>
                                                update(
                                                    selection.passengerIndex,
                                                    { birthDate },
                                                )
                                            }
                                        />
                                    )}
                                    {traveler.yellowFeverVaccinated ===
                                        null && (
                                        <label className="field">
                                            <span>Vaccin fièvre jaune</span>
                                            <select
                                                className="control"
                                                value={
                                                    selection.yellowFeverVaccinated ===
                                                    null
                                                        ? ''
                                                        : selection.yellowFeverVaccinated
                                                          ? 'yes'
                                                          : 'no'
                                                }
                                                onChange={(event) =>
                                                    update(
                                                        selection.passengerIndex,
                                                        {
                                                            yellowFeverVaccinated:
                                                                event.target
                                                                    .value ===
                                                                ''
                                                                    ? null
                                                                    : event
                                                                          .target
                                                                          .value ===
                                                                      'yes',
                                                        },
                                                    )
                                                }
                                            >
                                                <option value="">
                                                    À préciser
                                                </option>
                                                <option value="yes">Oui</option>
                                                <option value="no">Non</option>
                                            </select>
                                        </label>
                                    )}
                                </div>
                            </>
                        )}
                    </article>
                );
            })}
        </section>
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
        <div className="field mt-4">
            <span>Voyageur</span>
            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    type="text"
                    className="control pl-9"
                    placeholder="Rechercher par nom ou téléphone…"
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
                        className="absolute top-1/2 right-2 grid size-6 -translate-y-1/2 place-items-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                        onClick={() => {
                            onSelect(null);
                            setQuery('');
                        }}
                        aria-label="Retirer ce voyageur"
                    >
                        <X className="size-3.5" />
                    </button>
                )}
                {open && (
                    <ul
                        className="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-border bg-popover p-1 shadow-lg"
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
                                        'flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm hover:bg-muted',
                                        candidate.uuid === selected?.uuid &&
                                            'bg-gold/10',
                                    )}
                                    onMouseDown={(event) =>
                                        event.preventDefault()
                                    }
                                    onClick={() => choose(candidate)}
                                >
                                    <span className="font-bold text-foreground">
                                        {candidate.firstName}{' '}
                                        {candidate.lastName}
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
                                {hiddenCount > 1 ? 's' : ''} — affinez la
                                recherche.
                            </li>
                        )}
                    </ul>
                )}
            </div>
        </div>
    );
}

function ReadOnlyDetail({ label, value }: { label: string; value: string }) {
    return (
        <label className="field">
            <span>{label}</span>
            <input className="control" value={value} readOnly />
        </label>
    );
}

function EditableDetail({
    label,
    value,
    onChange,
    type = 'text',
    maxLength,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    type?: 'text' | 'date';
    maxLength?: number;
}) {
    return (
        <label className="field">
            <span>{label}</span>
            <input
                className="control"
                type={type}
                value={value}
                maxLength={maxLength}
                onChange={(event) => onChange(event.target.value)}
            />
        </label>
    );
}
