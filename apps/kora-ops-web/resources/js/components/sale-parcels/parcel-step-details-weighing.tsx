import {
    AlertTriangle,
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    ArrowUpRight,
    Building2,
    Bus,
    CheckCircle2,
    FileText,
    MessageSquare,
    Package,
    PackageCheck,
    Pencil,
    Phone,
    Plus,
    Ruler,
    Scale,
    Search,
    ShieldAlert,
    ShieldCheck,
    User,
    UserCheck,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import PhoneInput, { isValidPhoneNumber } from 'react-phone-number-input';
import phoneLabels from 'react-phone-number-input/locale/fr.json';
import { ParcelButton } from '@/components/parcels/parcel-button';
import { cn } from '@/lib/utils';
import {
    getItinerariesForHub,
    KNOWN_CLIENTS,
    PARCEL_NATURE_OPTIONS,
} from './mock-data';
import type {
    KnownParcelClient,
    ParcelIntakeDraft,
    ParcelItinerary,
    ParcelNatureId,
} from './types';

interface Props {
    draft: ParcelIntakeDraft;
    onChange: (updates: Partial<ParcelIntakeDraft>) => void;
    onNext: () => void;
    onBack: () => void;
    weightInputRef?: React.RefObject<HTMLInputElement | null>;
}

const QUICK_WEIGHTS = [0.5, 1, 2, 5, 10, 15];

const DIMENSION_PRESETS = [
    { label: 'Pochette A4', value: '30 × 21 × 3 cm' },
    { label: 'Carton M', value: '35 × 25 × 15 cm' },
    { label: 'Carton L', value: '50 × 40 × 30 cm' },
    { label: 'Sac / Malle', value: '70 × 45 × 35 cm' },
];

type SenderPanelMode = 'search' | 'new_form' | 'summary';

export function ParcelStepDetailsWeighing({
    draft,
    onChange,
    onNext,
    onBack,
    weightInputRef,
}: Props) {
    const [attemptedSubmit, setAttemptedSubmit] = useState(false);

    // Unified sender panel state: 'search' | 'new_form' | 'summary'
    const initialSenderMode: SenderPanelMode =
        draft.knownClientId ||
        (draft.senderName.trim().length >= 2 &&
            draft.senderPhone.trim() !== '' &&
            isValidPhoneNumber(draft.senderPhone))
            ? 'summary'
            : 'search';

    const [senderPanelMode, setSenderPanelMode] =
        useState<SenderPanelMode>(initialSenderMode);
    const [clientQuery, setClientQuery] = useState('');
    const [clientDropdownOpen, setClientDropdownOpen] = useState(false);
    const [highlightedClientIdx, setHighlightedClientIdx] = useState(0);
    const clientSearchRef = useRef<HTMLDivElement>(null);

    const selectedKnownClient = useMemo(
        () => KNOWN_CLIENTS.find((c) => c.id === draft.knownClientId) ?? null,
        [draft.knownClientId],
    );

    const filteredKnownClients = useMemo(() => {
        const q = clientQuery.trim().toLowerCase();

        if (!q) {
            return KNOWN_CLIENTS;
        }

        return KNOWN_CLIENTS.filter(
            (c) =>
                c.name.toLowerCase().includes(q) ||
                c.phone.toLowerCase().includes(q) ||
                c.accountType.toLowerCase().includes(q) ||
                (c.companyName?.toLowerCase().includes(q) ?? false),
        );
    }, [clientQuery]);

    useEffect(() => {
        const onClickOutside = (e: MouseEvent) => {
            if (
                clientSearchRef.current &&
                !clientSearchRef.current.contains(e.target as Node)
            ) {
                setClientDropdownOpen(false);
            }
        };

        document.addEventListener('mousedown', onClickOutside);

        return () => document.removeEventListener('mousedown', onClickOutside);
    }, []);

    const handleSelectKnownClient = (client: KnownParcelClient) => {
        onChange({
            knownClientId: client.id,
            senderName: client.name,
            senderPhone: client.phone,
            ...(client.companyAccountId
                ? { companyAccountId: client.companyAccountId }
                : {}),
        });
        setClientQuery('');
        setClientDropdownOpen(false);
        setSenderPanelMode('summary');
    };

    const handleStartNewSender = (prefillName = '') => {
        onChange({
            knownClientId: undefined,
            senderName: prefillName,
            senderPhone: '',
        });
        setClientQuery('');
        setClientDropdownOpen(false);
        setSenderPanelMode('new_form');
    };

    const handleBackToClientSearch = () => {
        onChange({
            knownClientId: undefined,
            senderName: '',
            senderPhone: '',
        });
        setClientQuery('');
        setSenderPanelMode('search');
    };

    const minCargo = draft.selectedItinerary?.minAvailableCargoKg ?? null;
    const isCargoExceeded =
        minCargo !== null &&
        !draft.deferToNextAvailableCargo &&
        draft.weightKg > minCargo;

    const compatibleAlternatives = useMemo(() => {
        if (!draft.destinationHub || !isCargoExceeded) {
            return [];
        }

        return getItinerariesForHub(draft.destinationHub).filter(
            (i) =>
                i.uuid !== draft.selectedItinerary?.uuid &&
                i.minAvailableCargoKg >= draft.weightKg,
        );
    }, [
        draft.destinationHub,
        draft.selectedItinerary?.uuid,
        draft.weightKg,
        isCargoExceeded,
    ]);

    const senderPhoneValid =
        draft.senderPhone.trim() !== '' &&
        isValidPhoneNumber(draft.senderPhone);
    const recipientPhoneValid =
        draft.recipientPhone.trim() !== '' &&
        isValidPhoneNumber(draft.recipientPhone);

    const senderComplete =
        senderPhoneValid && draft.senderName.trim().length >= 2;
    const recipientComplete =
        recipientPhoneValid && draft.recipientName.trim().length >= 2;

    const validationErrors = useMemo(() => {
        const errs: string[] = [];

        if (!senderPhoneValid) {
            errs.push(
                "Téléphone de l'expéditeur invalide ou incomplet (format international requis).",
            );
        }

        if (draft.senderName.trim().length < 2) {
            errs.push("Nom et prénoms de l'expéditeur requis.");
        }

        if (!recipientPhoneValid) {
            errs.push(
                'Téléphone du destinataire invalide ou incomplet (format international requis).',
            );
        }

        if (draft.recipientName.trim().length < 2) {
            errs.push('Nom et prénoms du destinataire requis.');
        }

        if (!(draft.weightKg > 0)) {
            errs.push('Le poids du colis doit être supérieur à 0 kg.');
        }

        if (
            draft.nature === 'express_valeur' &&
            !(draft.declaredValueFcfa > 0)
        ) {
            errs.push(
                'Indiquez la valeur déclarée du contenu pour un envoi Colis Assuré / Valeur.',
            );
        }

        if (!draft.prohibitedAccepted) {
            errs.push('Confirmez la déclaration de contenu autorisé.');
        }

        if (isCargoExceeded) {
            errs.push(
                `Le poids (${draft.weightKg} kg) dépasse la soute restante du car sélectionné (${minCargo} kg). Choisissez un autre car ou mettez en dépôt libre.`,
            );
        }

        return errs;
    }, [
        draft.declaredValueFcfa,
        draft.nature,
        draft.prohibitedAccepted,
        draft.recipientName,
        draft.senderName,
        draft.weightKg,
        isCargoExceeded,
        minCargo,
        recipientPhoneValid,
        senderPhoneValid,
    ]);

    const handleContinue = () => {
        if (validationErrors.length > 0) {
            setAttemptedSubmit(true);

            return;
        }

        onNext();
    };

    const handleSwitchItinerary = (itinerary: ParcelItinerary) => {
        onChange({
            selectedItinerary: itinerary,
            deferToNextAvailableCargo: false,
        });
    };

    const handleClientKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (!clientDropdownOpen && (e.key === 'ArrowDown' || e.key === 'Enter')) {
            setClientDropdownOpen(true);

            return;
        }

        if (!clientDropdownOpen) {
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setHighlightedClientIdx((prev) =>
                Math.min(prev + 1, Math.max(0, filteredKnownClients.length - 1)),
            );
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setHighlightedClientIdx((prev) => Math.max(prev - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const picked = filteredKnownClients[highlightedClientIdx];

            if (picked) {
                handleSelectKnownClient(picked);
            }
        } else if (e.key === 'Escape') {
            setClientDropdownOpen(false);
        }
    };

    return (
        <div className="space-y-5 animate-in fade-in slide-in-from-bottom-2 duration-200">
            {/* Légende champs obligatoires */}
            <div className="flex flex-wrap items-center justify-between gap-2 px-1 text-[11px] text-muted-foreground">
                <span>
                    Identifiez le déposant, le destinataire puis pesez le colis.
                </span>
                <span>
                    Les champs marqués <span className="required-asterisk">*</span>{' '}
                    sont obligatoires.
                </span>
            </div>

            {/* Section 1: Coordonnées empilées et visuellement distinctes */}
            <section className="space-y-3">
                {/* BLOC 1 : EXPÉDITEUR (DÉPOSANT · DÉPART) — Identité Bleu Nuit / Or */}
                <article
                    className={cn(
                        'overflow-visible rounded-[1.125rem] border border-border border-l-4 border-l-kora bg-card shadow-xs transition-all',
                        senderComplete && 'ring-1 ring-kora/15',
                        attemptedSubmit &&
                            !senderComplete &&
                            'border-destructive/60 border-l-destructive',
                    )}
                >
                    {/* Header distinctif Expéditeur */}
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-tr-[1.125rem] border-b border-border/70 bg-kora/6 px-5 py-3.5 dark:bg-kora/30">
                        <div className="flex items-center gap-3">
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-kora text-gold shadow-xs">
                                <ArrowUpRight className="size-4" />
                            </span>
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="rounded-md bg-kora px-2 py-0.5 text-[9px] font-black tracking-wider text-gold uppercase">
                                        1 · Expéditeur (Déposant)
                                    </span>
                                    <span className="required-asterisk">*</span>
                                    <span className="text-[11px] font-bold text-muted-foreground">
                                        Départ : {draft.originHub.city}
                                    </span>
                                </div>
                                <p className="mt-0.5 text-xs font-semibold text-foreground">
                                    Qui dépose le colis au guichet ?
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            {senderComplete && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-kora px-2.5 py-1 text-[10px] font-extrabold text-gold">
                                    <CheckCircle2 className="size-3.5" />
                                    Déposant prêt
                                </span>
                            )}
                            {senderPanelMode === 'search' ? (
                                <ParcelButton
                                    variant="navy"
                                    size="sm"
                                    onClick={() => handleStartNewSender('')}
                                >
                                    <Plus className="size-3.5 text-gold" />
                                    <span>Nouveau déposant</span>
                                </ParcelButton>
                            ) : (
                                <button
                                    type="button"
                                    onClick={handleBackToClientSearch}
                                    className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-border bg-card px-2.5 py-1.5 text-[11px] font-bold text-foreground transition-colors hover:border-gold"
                                >
                                    <Search className="size-3.5 text-digital" />
                                    <span>Changer / Chercher un compte</span>
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Corps unifié Expéditeur (jamais de doublon !) */}
                    <div className="p-5">
                        {/* MODE A : Recherche unifiée client connu + accès nouveau */}
                        {senderPanelMode === 'search' && (
                            <div
                                ref={clientSearchRef}
                                className="relative space-y-3"
                            >
                                <div className="field">
                                    <span className="field-label">
                                        <Search className="size-3.5" />
                                        Rechercher un client connu (nom,
                                        téléphone, code entreprise){' '}
                                        <span className="required-asterisk">
                                            *
                                        </span>
                                    </span>
                                    <div className="relative flex items-center">
                                        <Search className="pointer-events-none absolute left-3 size-4 text-muted-foreground" />
                                        <input
                                            type="text"
                                            value={clientQuery}
                                            onFocus={() =>
                                                setClientDropdownOpen(true)
                                            }
                                            onKeyDown={handleClientKeyDown}
                                            onChange={(e) => {
                                                setClientQuery(e.target.value);
                                                setHighlightedClientIdx(0);
                                                setClientDropdownOpen(true);
                                            }}
                                            placeholder="Tapez un nom, un numéro ou ENT-0042… ou cliquez « Nouveau déposant »"
                                            className="control w-full pl-9! pr-9!"
                                        />
                                        {clientQuery && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setClientQuery('')
                                                }
                                                className="absolute right-2.5 flex size-6 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                                                aria-label="Effacer la recherche client"
                                            >
                                                <X className="size-3.5" />
                                            </button>
                                        )}
                                    </div>
                                </div>

                                {/* Habitués rapides */}
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-[10px] font-bold text-muted-foreground">
                                        Clients fréquents :
                                    </span>
                                    {KNOWN_CLIENTS.slice(0, 4).map((client) => (
                                        <button
                                            key={client.id}
                                            type="button"
                                            onClick={() =>
                                                handleSelectKnownClient(client)
                                            }
                                            className="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-border bg-muted/40 px-3 py-1 text-[11px] font-bold text-foreground transition-colors hover:border-gold hover:bg-gold/10"
                                        >
                                            <UserCheck className="size-3 text-gold" />
                                            <span>{client.name}</span>
                                        </button>
                                    ))}
                                </div>

                                {/* Dropdown résultats */}
                                {clientDropdownOpen && (
                                    <div className="absolute right-0 left-0 z-40 mt-1 max-h-64 overflow-y-auto rounded-2xl border border-border bg-card p-1.5 shadow-xl">
                                        {filteredKnownClients.length === 0 ? (
                                            <div className="flex flex-wrap items-center justify-between gap-2 px-3 py-3 text-xs">
                                                <span className="text-muted-foreground">
                                                    Aucun compte trouvé pour «{' '}
                                                    {clientQuery} ».
                                                </span>
                                                <ParcelButton
                                                    variant="primary"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleStartNewSender(
                                                            clientQuery.trim(),
                                                        )
                                                    }
                                                >
                                                    <Plus className="size-3.5" />
                                                    <span>
                                                        Créer «{' '}
                                                        {clientQuery.trim()} »
                                                    </span>
                                                </ParcelButton>
                                            </div>
                                        ) : (
                                            filteredKnownClients.map(
                                                (client, idx) => {
                                                    const isHighlighted =
                                                        idx ===
                                                        highlightedClientIdx;

                                                    return (
                                                        <button
                                                            key={client.id}
                                                            type="button"
                                                            onMouseEnter={() =>
                                                                setHighlightedClientIdx(
                                                                    idx,
                                                                )
                                                            }
                                                            onClick={() =>
                                                                handleSelectKnownClient(
                                                                    client,
                                                                )
                                                            }
                                                            className={cn(
                                                                'flex w-full cursor-pointer items-center justify-between gap-3 rounded-xl px-3 py-2 text-left transition-colors',
                                                                isHighlighted
                                                                    ? 'bg-kora/8 text-foreground dark:bg-gold/15'
                                                                    : 'hover:bg-muted/60',
                                                            )}
                                                        >
                                                            <div className="flex min-w-0 items-center gap-2.5">
                                                                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-kora text-[11px] font-black text-gold">
                                                                    {client.accountType ===
                                                                    'Compte Entreprise' ? (
                                                                        <Building2 className="size-4" />
                                                                    ) : (
                                                                        client.name
                                                                            .slice(
                                                                                0,
                                                                                2,
                                                                            )
                                                                            .toUpperCase()
                                                                    )}
                                                                </span>
                                                                <div className="min-w-0">
                                                                    <p className="truncate text-xs font-extrabold text-foreground">
                                                                        {
                                                                            client.name
                                                                        }
                                                                    </p>
                                                                    <p className="text-[11px] font-medium text-muted-foreground">
                                                                        {
                                                                            client.phone
                                                                        }{' '}
                                                                        ·{' '}
                                                                        {
                                                                            client.shipmentsCount
                                                                        }{' '}
                                                                        colis
                                                                    </p>
                                                                </div>
                                                            </div>

                                                            <span className="shrink-0 rounded-full border border-border bg-muted/60 px-2 py-0.5 text-[9px] font-bold text-foreground">
                                                                {client.companyName ??
                                                                    client.accountType}
                                                            </span>
                                                        </button>
                                                    );
                                                },
                                            )
                                        )}
                                    </div>
                                )}
                            </div>
                        )}

                        {/* MODE B : Saisie d'un nouveau déposant (barre de recherche cachée) */}
                        {senderPanelMode === 'new_form' && (
                            <div className="space-y-3">
                                <div className="form-grid premium-form p-4!">
                                    <label className="field">
                                        <span className="field-label">
                                            <Phone className="size-3.5" />
                                            Téléphone du déposant{' '}
                                            <span className="required-asterisk">
                                                *
                                            </span>
                                        </span>
                                        <PhoneInput
                                            id="sender-phone"
                                            className={cn(
                                                'control phone-field-control',
                                                attemptedSubmit &&
                                                    !senderPhoneValid &&
                                                    'phone-field-control-invalid',
                                            )}
                                            defaultCountry="BJ"
                                            international
                                            labels={phoneLabels}
                                            value={
                                                draft.senderPhone || undefined
                                            }
                                            onChange={(value) =>
                                                onChange({
                                                    senderPhone: value ?? '',
                                                })
                                            }
                                            numberInputProps={{
                                                placeholder:
                                                    '+229 01 97 00 00 00',
                                                autoComplete: 'tel',
                                                'aria-invalid':
                                                    attemptedSubmit &&
                                                    !senderPhoneValid,
                                            }}
                                        />
                                        {attemptedSubmit &&
                                            !senderPhoneValid && (
                                                <small className="field-error-text">
                                                    Saisissez un numéro valide
                                                    avec indicatif pays.
                                                </small>
                                            )}
                                    </label>

                                    <label className="field">
                                        <span className="field-label">
                                            <User className="size-3.5" />
                                            Nom &amp; Prénoms (ou Raison
                                            sociale){' '}
                                            <span className="required-asterisk">
                                                *
                                            </span>
                                        </span>
                                        <input
                                            id="sender-name"
                                            type="text"
                                            value={draft.senderName}
                                            placeholder="Ex: Jean-Baptiste Hounsa"
                                            aria-invalid={
                                                attemptedSubmit &&
                                                draft.senderName.trim().length <
                                                    2
                                            }
                                            onChange={(e) =>
                                                onChange({
                                                    senderName: e.target.value,
                                                })
                                            }
                                            className="control"
                                        />
                                        {attemptedSubmit &&
                                            draft.senderName.trim().length <
                                                2 && (
                                                <small className="field-error-text">
                                                    Nom complet requis (min. 2
                                                    caractères).
                                                </small>
                                            )}
                                    </label>
                                </div>

                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <span className="text-[11px] text-muted-foreground">
                                        Ce nouveau déposant sera rattaché à
                                        l&apos;envoi en cours.
                                    </span>
                                    <ParcelButton
                                        variant="primary"
                                        size="sm"
                                        disabled={!senderComplete}
                                        onClick={() =>
                                            setSenderPanelMode('summary')
                                        }
                                    >
                                        <CheckCircle2 className="size-3.5" />
                                        <span>Confirmer le déposant</span>
                                    </ParcelButton>
                                </div>
                            </div>
                        )}

                        {/* MODE C : Fiche compacte unifiée (Client connu OU Nouveau client confirmé) */}
                        {senderPanelMode === 'summary' && (
                            <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gold/45 bg-gold/8 px-4 py-3.5">
                                <div className="flex items-center gap-3">
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-kora text-sm font-black text-gold">
                                        {selectedKnownClient?.accountType ===
                                        'Compte Entreprise' ? (
                                            <Building2 className="size-4" />
                                        ) : (
                                            (draft.senderName || 'ND')
                                                .slice(0, 2)
                                                .toUpperCase()
                                        )}
                                    </span>
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <strong className="text-sm font-extrabold text-foreground">
                                                {draft.senderName}
                                            </strong>
                                            <span className="rounded-full bg-kora px-2 py-0.5 text-[9px] font-extrabold text-gold">
                                                {selectedKnownClient
                                                    ? selectedKnownClient.accountType
                                                    : 'Nouveau déposant'}
                                            </span>
                                            {selectedKnownClient && (
                                                <span className="text-[11px] font-medium text-muted-foreground">
                                                    ·{' '}
                                                    {
                                                        selectedKnownClient.shipmentsCount
                                                    }{' '}
                                                    envois
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-0.5 font-mono text-xs font-bold text-foreground/80">
                                            {draft.senderPhone}
                                        </p>
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center gap-2">
                                    {selectedKnownClient
                                        ?.defaultRecipientName &&
                                        selectedKnownClient.defaultRecipientPhone && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    onChange({
                                                        recipientName:
                                                            selectedKnownClient.defaultRecipientName,
                                                        recipientPhone:
                                                            selectedKnownClient.defaultRecipientPhone,
                                                    })
                                                }
                                                className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-3 py-1.5 text-[11px] font-extrabold text-emerald-800 transition-colors hover:bg-emerald-600 hover:text-white dark:text-emerald-300"
                                            >
                                                <Users className="size-3.5" />
                                                <span>
                                                    Destinataire habituel (
                                                    {
                                                        selectedKnownClient.defaultRecipientName.split(
                                                            ' ',
                                                        )[0]
                                                    }
                                                    )
                                                </span>
                                            </button>
                                        )}

                                    <button
                                        type="button"
                                        onClick={() =>
                                            setSenderPanelMode('new_form')
                                        }
                                        className="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border bg-card px-2.5 py-1.5 text-[11px] font-bold text-foreground transition-colors hover:border-gold"
                                    >
                                        <Pencil className="size-3" />
                                        <span>Modifier</span>
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </article>

                {/* Connecteur directionnel clair */}
                <div className="flex items-center justify-center gap-3 py-0.5 text-[11px] font-extrabold text-muted-foreground">
                    <span className="h-px flex-1 bg-border" />
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/60 px-3 py-1 text-foreground">
                        <ArrowDown className="size-3.5 text-emerald-600" />
                        <span>
                            Expédié vers {draft.destinationHub?.city ?? 'la destination'}
                        </span>
                    </span>
                    <span className="h-px flex-1 bg-border" />
                </div>

                {/* BLOC 2 : DESTINATAIRE (RETRAIT · ARRIVÉE) — Identité Émeraude distincte */}
                <article
                    className={cn(
                        'rounded-[1.125rem] border border-border border-l-4 border-l-emerald-600 bg-card shadow-xs transition-all',
                        recipientComplete && 'ring-1 ring-emerald-500/20',
                        attemptedSubmit &&
                            !recipientComplete &&
                            'border-destructive/60 border-l-destructive',
                    )}
                >
                    {/* Header distinctif Destinataire */}
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-tr-[1.125rem] border-b border-border/70 bg-emerald-500/8 px-5 py-3.5">
                        <div className="flex items-center gap-3">
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-xs">
                                <PackageCheck className="size-4" />
                            </span>
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="rounded-md bg-emerald-600 px-2 py-0.5 text-[9px] font-black tracking-wider text-white uppercase">
                                        2 · Destinataire (Retrait)
                                    </span>
                                    <span className="required-asterisk">*</span>
                                    <span className="text-[11px] font-bold text-emerald-700 dark:text-emerald-300">
                                        Arrivée :{' '}
                                        {draft.destinationHub?.city ?? '—'} (
                                        {draft.destinationHub?.name ?? '—'})
                                    </span>
                                </div>
                                <p className="mt-0.5 text-xs font-semibold text-foreground">
                                    Qui récupérera le colis et recevra le code
                                    PIN ?
                                </p>
                            </div>
                        </div>

                        {recipientComplete && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2.5 py-1 text-[10px] font-extrabold text-emerald-700 dark:text-emerald-300">
                                <CheckCircle2 className="size-3.5" />
                                Destinataire prêt
                            </span>
                        )}
                    </div>

                    <div className="space-y-4 p-5">
                        <div className="form-grid premium-form p-4!">
                            <label className="field">
                                <span className="field-label">
                                    <Phone className="size-3.5" />
                                    Téléphone du destinataire{' '}
                                    <span className="required-asterisk">*</span>
                                </span>
                                <PhoneInput
                                    id="recipient-phone"
                                    className={cn(
                                        'control phone-field-control',
                                        attemptedSubmit &&
                                            !recipientPhoneValid &&
                                            'phone-field-control-invalid',
                                    )}
                                    defaultCountry="BJ"
                                    international
                                    labels={phoneLabels}
                                    value={draft.recipientPhone || undefined}
                                    onChange={(value) =>
                                        onChange({
                                            recipientPhone: value ?? '',
                                        })
                                    }
                                    numberInputProps={{
                                        placeholder: '+229 01 96 00 00 00',
                                        autoComplete: 'tel',
                                        'aria-invalid':
                                            attemptedSubmit &&
                                            !recipientPhoneValid,
                                    }}
                                />
                                <small className="text-[10px] font-medium text-muted-foreground">
                                    Le code secret de retrait (PIN) sera envoyé
                                    sur ce numéro à l&apos;arrivée.
                                </small>
                                {attemptedSubmit && !recipientPhoneValid && (
                                    <small className="field-error-text">
                                        Saisissez un numéro valide avec
                                        indicatif pays.
                                    </small>
                                )}
                            </label>

                            <label className="field">
                                <span className="field-label">
                                    <Users className="size-3.5" />
                                    Nom &amp; Prénoms du destinataire{' '}
                                    <span className="required-asterisk">*</span>
                                </span>
                                <input
                                    id="recipient-name"
                                    type="text"
                                    value={draft.recipientName}
                                    placeholder="Ex: Mariam Bio Tchané"
                                    aria-invalid={
                                        attemptedSubmit &&
                                        draft.recipientName.trim().length < 2
                                    }
                                    onChange={(e) =>
                                        onChange({
                                            recipientName: e.target.value,
                                        })
                                    }
                                    className="control"
                                />
                                {attemptedSubmit &&
                                    draft.recipientName.trim().length < 2 && (
                                        <small className="field-error-text">
                                            Nom du destinataire requis.
                                        </small>
                                    )}
                            </label>
                        </div>

                        {/* Préférences de notification */}
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-4 py-2.5">
                            <span className="flex items-center gap-2 text-[11px] font-extrabold text-foreground">
                                <MessageSquare className="size-3.5 text-emerald-600" />
                                Alertes destinataire :
                            </span>
                            <div className="flex flex-wrap gap-4 text-[11px]">
                                <label className="flex cursor-pointer items-center gap-2 font-semibold text-foreground">
                                    <input
                                        type="checkbox"
                                        checked={draft.notifySms}
                                        onChange={(e) =>
                                            onChange({
                                                notifySms: e.target.checked,
                                            })
                                        }
                                        className="size-4 cursor-pointer rounded border-border accent-emerald-600"
                                    />
                                    <span>
                                        SMS automatique du code PIN à
                                        l&apos;arrivée
                                    </span>
                                </label>

                                <label className="flex cursor-pointer items-center gap-2 font-semibold text-foreground">
                                    <input
                                        type="checkbox"
                                        checked={draft.notifyWhatsApp}
                                        onChange={(e) =>
                                            onChange({
                                                notifyWhatsApp:
                                                    e.target.checked,
                                            })
                                        }
                                        className="size-4 cursor-pointer rounded border-border accent-emerald-600"
                                    />
                                    <span>
                                        Lien de suivi WhatsApp (départ &amp;
                                        escale)
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            {/* Section 2: Qualification, Nature & Balance */}
            <section className="rounded-[1.125rem] border border-border bg-card p-5 shadow-xs space-y-5">
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border/70 pb-3.5">
                    <div>
                        <span className="section-kicker">
                            Qualification &amp; Contrôle Soute
                        </span>
                        <h2 className="text-base font-extrabold tracking-tight text-foreground">
                            Nature du colis &amp; pesée au comptoir
                        </h2>
                    </div>

                    <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                        <span>Focus balance :</span>
                        <kbd className="rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px] font-bold text-foreground">
                            Alt+W
                        </kbd>
                    </div>
                </div>

                {/* Selectable Nature Cards — full card cursor-pointer */}
                <div className="space-y-2.5">
                    <span className="field-label text-[10px] font-extrabold text-muted-foreground">
                        <Package className="size-3.5" />
                        Nature du colis{' '}
                        <span className="required-asterisk">*</span>
                    </span>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {PARCEL_NATURE_OPTIONS.map((opt) => {
                        const active = draft.nature === opt.id;

                        return (
                            <button
                                key={opt.id}
                                type="button"
                                onClick={() =>
                                    onChange({
                                        nature: opt.id as ParcelNatureId,
                                    })
                                }
                                className={cn(
                                    'group flex cursor-pointer flex-col justify-between rounded-2xl border p-3.5 text-left transition-all',
                                    active
                                        ? 'border-gold bg-gold/10 shadow-sm ring-2 ring-gold/75'
                                        : 'border-border bg-background hover:-translate-y-0.5 hover:border-gold/60 hover:shadow-xs',
                                )}
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <span
                                        className={cn(
                                            'flex size-9 items-center justify-center rounded-xl transition-colors',
                                            active
                                                ? 'bg-kora text-gold'
                                                : 'bg-muted text-foreground group-hover:bg-kora/10',
                                        )}
                                    >
                                        {opt.id === 'standard' && (
                                            <Package className="size-4" />
                                        )}
                                        {opt.id === 'documents' && (
                                            <FileText className="size-4" />
                                        )}
                                        {opt.id === 'fragile' && (
                                            <ShieldAlert className="size-4" />
                                        )}
                                        {opt.id === 'express_valeur' && (
                                            <ShieldCheck className="size-4" />
                                        )}
                                    </span>

                                    <div className="text-right">
                                        <span className="block font-mono text-[11px] font-black text-foreground">
                                            {opt.basePrice.toLocaleString(
                                                'fr-FR',
                                            )}{' '}
                                            F
                                        </span>
                                        <span className="text-[9px] font-bold text-muted-foreground">
                                            +{opt.pricePerKg} F/kg
                                        </span>
                                    </div>
                                </div>

                                <div className="mt-3">
                                    <div className="flex items-center justify-between gap-1">
                                        <p className="text-xs font-extrabold text-foreground">
                                            {opt.label}
                                        </p>
                                        {active && (
                                            <CheckCircle2 className="size-3.5 shrink-0 text-gold" />
                                        )}
                                    </div>
                                    <p className="mt-1 line-clamp-2 text-[10px] leading-snug text-muted-foreground">
                                        {opt.description}
                                    </p>
                                </div>
                            </button>
                        );
                    })}
                    </div>
                </div>

                {/* Weighing Box: LED Display + Properly Styled .field .control inputs */}
                <div className="grid grid-cols-1 gap-4 rounded-2xl border border-border bg-muted/25 p-4 md:grid-cols-12 md:items-start">
                    <div className="md:col-span-4 flex flex-col items-center justify-center rounded-xl border border-slate-800 bg-slate-950 px-4 py-5 text-center shadow-inner">
                        <div className="flex w-full items-center justify-between font-mono text-[9px] text-slate-400">
                            <span>BALANCE GUICHET</span>
                            <span className="text-emerald-400">● STABLE</span>
                        </div>
                        <div className="my-2 flex items-baseline gap-1.5">
                            <span className="font-mono text-4xl font-black tracking-tight text-emerald-400">
                                {Number.isFinite(draft.weightKg)
                                    ? draft.weightKg.toFixed(2)
                                    : '0.00'}
                            </span>
                            <span className="text-sm font-bold text-emerald-500">
                                KG
                            </span>
                        </div>
                        <span className="text-[10px] text-slate-400">
                            Calcul tarifaire en direct →
                        </span>
                    </div>

                    <div className="md:col-span-8 space-y-3.5">
                        <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                            <label className="field">
                                <span className="field-label">
                                    <Scale className="size-3.5" />
                                    Poids pesé (kg){' '}
                                    <span className="required-asterisk">*</span>
                                </span>
                                <input
                                    ref={weightInputRef}
                                    id="parcel-weight-input"
                                    type="number"
                                    min="0.1"
                                    max="250"
                                    step="0.1"
                                    value={draft.weightKg || ''}
                                    aria-invalid={
                                        attemptedSubmit &&
                                        !(draft.weightKg > 0)
                                    }
                                    onChange={(e) =>
                                        onChange({
                                            weightKg: Math.max(
                                                0,
                                                Number(e.target.value),
                                            ),
                                        })
                                    }
                                    className="control font-mono! text-base! font-black!"
                                />
                                <div className="mt-1 flex flex-wrap gap-1.5">
                                    {QUICK_WEIGHTS.map((w) => (
                                        <button
                                            key={w}
                                            type="button"
                                            onClick={() =>
                                                onChange({ weightKg: w })
                                            }
                                            className={cn(
                                                'cursor-pointer rounded-md border px-2 py-0.5 font-mono text-[10px] font-bold transition-colors',
                                                draft.weightKg === w
                                                    ? 'border-gold bg-gold text-kora'
                                                    : 'border-border bg-card text-foreground hover:border-gold',
                                            )}
                                        >
                                            {w} kg
                                        </button>
                                    ))}
                                </div>
                            </label>

                            <label className="field">
                                <span className="field-label">
                                    <ShieldCheck className="size-3.5" />
                                    Valeur assurée / déclarée (FCFA)
                                    {draft.nature === 'express_valeur' && (
                                        <span className="required-asterisk">
                                            *
                                        </span>
                                    )}
                                </span>
                                <input
                                    id="parcel-declared-value"
                                    type="number"
                                    min="0"
                                    step="5000"
                                    placeholder="Ex: 50000 (0 si non déclaré)"
                                    value={draft.declaredValueFcfa || ''}
                                    aria-invalid={
                                        attemptedSubmit &&
                                        draft.nature === 'express_valeur' &&
                                        !(draft.declaredValueFcfa > 0)
                                    }
                                    onChange={(e) =>
                                        onChange({
                                            declaredValueFcfa: Math.max(
                                                0,
                                                Number(e.target.value),
                                            ),
                                        })
                                    }
                                    className="control font-mono!"
                                />
                                <small className="text-[10px] leading-snug font-medium text-muted-foreground">
                                    Valeur marchande estimée du contenu. Sert de
                                    plafond d&apos;indemnisation en cas de perte
                                    ou d&apos;avarie.
                                </small>
                            </label>
                        </div>
                    </div>
                </div>

                {/* Real-time Cargo Overflow Warning */}
                {isCargoExceeded && minCargo !== null && (
                    <div
                        role="alert"
                        className="rounded-2xl border-2 border-destructive/50 bg-destructive/10 p-4 space-y-3"
                    >
                        <div className="flex items-start gap-3">
                            <AlertTriangle className="mt-0.5 size-5 shrink-0 text-destructive" />
                            <div>
                                <h3 className="text-xs font-extrabold text-destructive uppercase">
                                    Soute insuffisante sur le car sélectionné (
                                    {draft.selectedItinerary?.legs[0]
                                        .departureTime}
                                    )
                                </h3>
                                <p className="mt-0.5 text-xs text-foreground">
                                    Ce colis pèse{' '}
                                    <strong>{draft.weightKg} kg</strong> alors
                                    que la soute disponible sur cet itinéraire
                                    est limitée à <strong>{minCargo} kg</strong>
                                    .
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2 pl-8">
                            {compatibleAlternatives.map((alt) => (
                                <ParcelButton
                                    key={alt.uuid}
                                    variant="navy"
                                    size="sm"
                                    onClick={() => handleSwitchItinerary(alt)}
                                >
                                    <Bus className="size-3.5 text-gold" />
                                    <span>
                                        Basculer sur {alt.dayLabel}{' '}
                                        {alt.legs[0].departureTime} (
                                        {alt.minAvailableCargoKg} kg dispo)
                                    </span>
                                </ParcelButton>
                            ))}

                            <ParcelButton
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    onChange({
                                        deferToNextAvailableCargo: true,
                                        selectedItinerary: null,
                                    })
                                }
                            >
                                Enregistrer en dépôt libre (prochain car)
                            </ParcelButton>
                        </div>
                    </div>
                )}

                {/* Dimensions & Packaging Notes inside .form-grid .premium-form */}
                <div className="form-grid premium-form p-4!">
                    <label className="field">
                        <span className="field-label">
                            <Ruler className="size-3.5" />
                            Dimensions / Encombrement
                        </span>
                        <input
                            id="dimensions-input"
                            type="text"
                            value={draft.dimensions}
                            placeholder="Ex: 35 × 25 × 15 cm"
                            onChange={(e) =>
                                onChange({ dimensions: e.target.value })
                            }
                            className="control"
                        />
                        <small className="text-[10px] leading-snug font-medium text-muted-foreground">
                            Ordre de saisie :{' '}
                            <strong className="text-foreground">
                                Longueur × Largeur × Hauteur
                            </strong>{' '}
                            en cm (ex : 35 × 25 × 15 cm) · Facultatif.
                        </small>
                        <div className="mt-1 flex flex-wrap gap-1.5">
                            {DIMENSION_PRESETS.map((p) => (
                                <button
                                    key={p.label}
                                    type="button"
                                    onClick={() =>
                                        onChange({ dimensions: p.value })
                                    }
                                    className={cn(
                                        'cursor-pointer rounded-md border px-2 py-0.5 text-[10px] font-bold transition-colors',
                                        draft.dimensions === p.value
                                            ? 'border-gold bg-gold/15 text-foreground'
                                            : 'border-border bg-card text-muted-foreground hover:border-gold hover:text-foreground',
                                    )}
                                >
                                    {p.label}
                                </button>
                            ))}
                        </div>
                    </label>

                    <label className="field">
                        <span className="field-label">
                            <FileText className="size-3.5" />
                            Observations d&apos;emballage &amp; état à réception
                        </span>
                        <textarea
                            id="notes-input"
                            rows={2}
                            value={draft.notes}
                            onChange={(e) =>
                                onChange({ notes: e.target.value })
                            }
                            placeholder="Ex: Carton scellé intact, mention fragile apposée..."
                            className="control min-h-[68px]! py-2.5!"
                        />
                        <small className="text-[10px] leading-snug font-medium text-muted-foreground">
                            Facultatif · Mentionnez l&apos;état visuel de
                            l&apos;emballage, un scellé ou une consigne de
                            manutention.
                        </small>
                    </label>
                </div>

                <label className="flex cursor-pointer items-start gap-2.5 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 text-xs">
                    <input
                        type="checkbox"
                        checked={draft.prohibitedAccepted}
                        onChange={(e) =>
                            onChange({ prohibitedAccepted: e.target.checked })
                        }
                        className="mt-0.5 size-4 cursor-pointer rounded border-border accent-kora"
                    />
                    <span>
                        <strong className="text-foreground">
                            Contrôle conformité &amp; sûreté{' '}
                            <span className="required-asterisk">*</span>
                        </strong>
                        <span className="block text-[11px] text-muted-foreground">
                            L&apos;expéditeur déclare que le colis ne contient
                            aucun produit interdit, inflammable ou dangereux.
                        </span>
                    </span>
                </label>
            </section>

            {/* Inline explicit validation feedback */}
            {attemptedSubmit && validationErrors.length > 0 && (
                <div
                    role="alert"
                    className="rounded-xl border border-destructive/40 bg-destructive/10 p-3.5 text-xs text-destructive"
                >
                    <p className="font-extrabold">
                        Impossible de passer au paiement :
                    </p>
                    <ul className="mt-1 list-disc space-y-0.5 pl-4">
                        {validationErrors.map((err, i) => (
                            <li key={i}>{err}</li>
                        ))}
                    </ul>
                </div>
            )}

            {/* Step Footer */}
            <div className="flex items-center justify-between pt-1">
                <ParcelButton variant="ghost" onClick={onBack}>
                    <ArrowLeft className="size-3.5" />
                    <span>Retour aux cars</span>
                </ParcelButton>

                <ParcelButton variant="primary" onClick={handleContinue}>
                    <span>Passer à l&apos;encaissement</span>
                    <kbd className="hidden rounded bg-kora/15 px-1.5 py-0.5 font-mono text-[9px] sm:inline-block">
                        Ctrl+↵
                    </kbd>
                    <ArrowRight className="size-4" />
                </ParcelButton>
            </div>
        </div>
    );
}
