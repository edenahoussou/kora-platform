import { UserRound } from 'lucide-react';

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
                                        : 'Choisir dans le roster'}
                                </h3>
                            </div>
                        </div>

                        <label className="field mt-4">
                            <span>Membre voyageur</span>
                            <select
                                className="control"
                                value={selection.travelerUuid}
                                onChange={(event) => {
                                    const selectedTraveler = roster.find(
                                        (candidate) =>
                                            candidate.uuid ===
                                            event.target.value,
                                    );
                                    update(selection.passengerIndex, {
                                        travelerUuid: event.target.value,
                                        documentType:
                                            selectedTraveler?.documentType ??
                                            '',
                                        documentNumber:
                                            selectedTraveler?.documentNumber ??
                                            '',
                                        nationality:
                                            selectedTraveler?.nationality ?? '',
                                        birthDate:
                                            selectedTraveler?.birthDate ?? '',
                                        yellowFeverVaccinated:
                                            selectedTraveler?.yellowFeverVaccinated ??
                                            null,
                                    });
                                }}
                            >
                                <option value="">Sélectionner un membre</option>
                                {roster.map((candidate) => (
                                    <option
                                        key={candidate.uuid}
                                        value={candidate.uuid}
                                        disabled={chosenElsewhere.has(
                                            candidate.uuid,
                                        )}
                                    >
                                        {candidate.firstName}{' '}
                                        {candidate.lastName} · {candidate.phone}
                                    </option>
                                ))}
                            </select>
                        </label>

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
