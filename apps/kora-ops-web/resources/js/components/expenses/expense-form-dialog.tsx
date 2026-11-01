import { useForm } from '@inertiajs/react';
import {
    AlignLeft,
    Banknote,
    BookOpen,
    Building2,
    BusFront,
    Calendar,
    Check,
    Coins,
    CreditCard,
    FileText,
    Hammer,
    Hash,
    Info,
    Layers,
    Receipt,
    Route,
    Sparkles,
    Tag,
    Trash2,
    UploadCloud,
    User,
    Warehouse,
    Wrench,
    X,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { cn } from '@/lib/utils';
import { store } from '@/routes/expenses';

type CategoryOption = {
    uuid: string;
    code: string;
    label: string;
};

type LedgerOption = {
    uuid: string;
    label: string;
};

type CostCenterItem = {
    uuid: string;
    label: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    categories: CategoryOption[];
    channels: Record<string, string>;
    ledgers: LedgerOption[];
    costCenters: {
        buses: CostCenterItem[];
        lines: CostCenterItem[];
        departures: CostCenterItem[];
        agencies: CostCenterItem[];
    };
    permissions: {
        canAttachCostCenter: boolean;
        canChooseAgency: boolean;
    };
    onSuccess?: () => void;
};

const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 Mo

function formatMoney(amount: number | string): string {
    const num = typeof amount === 'string' ? Number(amount) : amount;

    if (!num || isNaN(num)) {
        return '—';
    }

    const formatted = new Intl.NumberFormat('fr-FR', {
        maximumFractionDigits: 0,
    }).format(num);

    return `${formatted} FCFA`;
}

function formatBytes(bytes: number): string {
    if (bytes === 0) {
        return '0 o';
    }

    const k = 1024;
    const sizes = ['o', 'Ko', 'Mo', 'Go'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return `${(bytes / Math.pow(k, i)).toFixed(1)} ${sizes[i]}`;
}

/** Modale immersive de saisie d'une dépense réseau (style caisse unifié). */
export function ExpenseFormDialog({
    open,
    onOpenChange,
    categories,
    channels,
    ledgers,
    costCenters,
    permissions,
    onSuccess,
}: Props) {
    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const [isDragging, setIsDragging] = useState(false);
    const [fileError, setFileError] = useState<string | null>(null);

    const form = useForm({
        category_uuid: categories[0]?.uuid ?? '',
        ledger_uuid: '',
        agency_uuid: '',
        center_type: 'none',
        bus_uuid: '',
        line_uuid: '',
        departure_uuid: '',
        maintenance_type: 'corrective',
        garage_label: '',
        parts_amount: '',
        labor_amount: '',
        payment_channel: 'cash',
        amount: '',
        payee_label: '',
        payee_reference: '',
        description: '',
        occurred_at: new Date().toISOString().slice(0, 16),
        evidence: null as File | null,
    });

    const selectedCategory = useMemo(
        () => categories.find((item) => item.uuid === form.data.category_uuid),
        [categories, form.data.category_uuid],
    );

    const isMaintenance = selectedCategory?.code === 'maintenance';

    const centerOptions = {
        bus: costCenters.buses,
        line: costCenters.lines,
        departure: costCenters.departures,
    } as const;

    const selectedCenterLabel = useMemo(() => {
        if (form.data.center_type === 'bus') {
            return (
                costCenters.buses.find((b) => b.uuid === form.data.bus_uuid)
                    ?.label ?? ''
            );
        }

        if (form.data.center_type === 'line') {
            return (
                costCenters.lines.find((l) => l.uuid === form.data.line_uuid)
                    ?.label ?? ''
            );
        }

        if (form.data.center_type === 'departure') {
            return (
                costCenters.departures.find(
                    (d) => d.uuid === form.data.departure_uuid,
                )?.label ?? ''
            );
        }

        return '';
    }, [
        costCenters,
        form.data.center_type,
        form.data.bus_uuid,
        form.data.line_uuid,
        form.data.departure_uuid,
    ]);

    const selectedAgencyLabel = useMemo(
        () =>
            costCenters.agencies.find((a) => a.uuid === form.data.agency_uuid)
                ?.label ?? '',
        [costCenters.agencies, form.data.agency_uuid],
    );

    const handleFileSelect = (file: File | null) => {
        setFileError(null);

        if (!file) {
            form.setData('evidence', null);

            return;
        }

        if (file.size > MAX_FILE_SIZE) {
            setFileError(
                `Le fichier est trop volumineux (${formatBytes(file.size)}). La taille maximale autorisée est de 5 Mo.`,
            );
            form.setData('evidence', null);

            return;
        }

        form.setData('evidence', file);
    };

    const submitExpense = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.transform((data) => {
            const payload: Record<string, unknown> = { ...data };
            delete payload.center_type;
            delete payload.maintenance_type;
            delete payload.garage_label;
            delete payload.parts_amount;
            delete payload.labor_amount;

            payload.bus_uuid = data.center_type === 'bus' ? data.bus_uuid : '';
            payload.line_uuid =
                data.center_type === 'line' ? data.line_uuid : '';
            payload.departure_uuid =
                data.center_type === 'departure' ? data.departure_uuid : '';

            if (isMaintenance) {
                payload.maintenance = {
                    type: data.maintenance_type,
                    garage_label: data.garage_label,
                    parts_amount: data.parts_amount,
                    labor_amount: data.labor_amount,
                };
            }

            return payload;
        });

        form.post(store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setFileError(null);
                onSuccess?.();
                onOpenChange(false);
            },
        });
    };

    if (!open) {
        return null;
    }

    const errors = form.errors as Record<string, string | undefined>;

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onMouseDown={(e) =>
                e.target === e.currentTarget && onOpenChange(false)
            }
        >
            <form
                onSubmit={submitExpense}
                className="cash-modal cash-opening-modal w-[52rem]! max-w-[95vw]!"
                role="dialog"
                aria-modal="true"
                aria-labelledby="expense-modal-title"
            >
                {/* En-tête de la modale */}
                <div className="cash-modal-head">
                    <div className="flex items-start gap-3">
                        <span className="cash-opening-icon">
                            <Receipt className="size-5" />
                        </span>
                        <div>
                            <span className="section-kicker">
                                Finance · Décaissements
                            </span>
                            <h3 id="expense-modal-title">Nouvelle dépense</h3>
                            <p>
                                Déclarez un décaissement réseau, son imputation
                                et sa pièce justificative.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={() => onOpenChange(false)}
                        className="cash-modal-close"
                        aria-label="Fermer"
                        disabled={form.processing}
                    >
                        <X className="size-4" />
                    </button>
                </div>

                <div className="cash-opening-list">
                    {/* Alerte contextuelle explicative */}
                    <div className="flex items-start gap-3 rounded-xl border border-gold/30 bg-gold/10 p-3.5 text-xs">
                        <Info className="mt-0.5 size-4 shrink-0 text-gold" />
                        <div className="space-y-0.5">
                            <strong className="block font-bold text-foreground">
                                Déclaration de décaissement
                            </strong>
                            <p className="text-[11px] leading-relaxed text-muted-foreground">
                                Renseignez la nature de la dépense, le
                                bénéficiaire et le montant. Une pièce
                                justificative (facture, reçu ou bon de caisse)
                                est obligatoire pour permettre la validation et
                                le rapprochement de caisse.
                            </p>
                        </div>
                    </div>

                    {/* Section 1 : Nature & Imputation analytique */}
                    <div className="cash-opening-row">
                        <div className="cash-opening-row-head">
                            <span className="cash-opening-icon">
                                <Layers className="size-4" />
                            </span>
                            <div>
                                <strong>Nature & Imputation</strong>
                                <small>
                                    Catégorie de charge, agence et centre de
                                    coût rattaché
                                </small>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <label className="field">
                                <span className="field-label">
                                    <Tag className="size-3.5" />
                                    Catégorie de dépense{' '}
                                    <b className="font-black text-destructive">
                                        *
                                    </b>
                                </span>
                                <select
                                    className="control"
                                    value={form.data.category_uuid}
                                    onChange={(e) =>
                                        form.setData(
                                            'category_uuid',
                                            e.target.value,
                                        )
                                    }
                                    required
                                >
                                    {categories.map((item) => (
                                        <option
                                            key={item.uuid}
                                            value={item.uuid}
                                        >
                                            {item.label}
                                        </option>
                                    ))}
                                </select>
                                {errors.category_uuid ? (
                                    <small className="field-error-text">
                                        {errors.category_uuid}
                                    </small>
                                ) : (
                                    <small>
                                        Détermine la classification comptable et
                                        les champs requis.
                                    </small>
                                )}
                            </label>

                            {permissions.canChooseAgency ? (
                                <label className="field">
                                    <span className="field-label">
                                        <Building2 className="size-3.5" />
                                        Agence support{' '}
                                        <b className="font-black text-destructive">
                                            *
                                        </b>
                                    </span>
                                    <select
                                        className="control"
                                        value={form.data.agency_uuid}
                                        onChange={(e) =>
                                            form.setData(
                                                'agency_uuid',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    >
                                        <option value="">
                                            Sélectionner l’agence
                                        </option>
                                        {costCenters.agencies.map((agency) => (
                                            <option
                                                key={agency.uuid}
                                                value={agency.uuid}
                                            >
                                                {agency.label}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.agency_uuid ? (
                                        <small className="field-error-text">
                                            {errors.agency_uuid}
                                        </small>
                                    ) : (
                                        <small>
                                            Site opérationnel porteur de la
                                            dépense.
                                        </small>
                                    )}
                                </label>
                            ) : (
                                <div className="flex items-center gap-2 rounded-xl border border-border/60 bg-muted/20 px-3.5 py-3 text-xs text-muted-foreground">
                                    <Building2 className="size-4 text-gold" />
                                    <span>
                                        Rattachée à votre agence d'affectation
                                        locale.
                                    </span>
                                </div>
                            )}
                        </div>

                        {permissions.canAttachCostCenter && (
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <label className="field">
                                    <span className="field-label">
                                        <Layers className="size-3.5" />
                                        Rattacher la dépense à
                                    </span>
                                    <select
                                        className="control"
                                        value={form.data.center_type}
                                        onChange={(e) =>
                                            form.setData(
                                                'center_type',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="none">
                                            Dépense générale (aucun
                                            rattachement)
                                        </option>
                                        <option value="bus">Un bus</option>
                                        <option value="line">Une ligne</option>
                                        <option value="departure">
                                            Un départ
                                        </option>
                                    </select>
                                    <small>
                                        Élément du réseau concerné par cette
                                        dépense.
                                    </small>
                                </label>

                                {form.data.center_type !== 'none' && (
                                    <label className="field">
                                        <span className="field-label">
                                            {form.data.center_type ===
                                                'bus' && (
                                                <BusFront className="size-3.5" />
                                            )}
                                            {form.data.center_type ===
                                                'line' && (
                                                <Route className="size-3.5" />
                                            )}
                                            {form.data.center_type ===
                                                'departure' && (
                                                <Calendar className="size-3.5" />
                                            )}
                                            {form.data.center_type === 'bus'
                                                ? 'Bus concerné'
                                                : form.data.center_type ===
                                                    'line'
                                                  ? 'Ligne concernée'
                                                  : 'Départ concerné'}{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <select
                                            className="control"
                                            value={
                                                form.data.center_type === 'bus'
                                                    ? form.data.bus_uuid
                                                    : form.data.center_type ===
                                                        'line'
                                                      ? form.data.line_uuid
                                                      : form.data.departure_uuid
                                            }
                                            onChange={(e) => {
                                                const key =
                                                    form.data.center_type ===
                                                    'bus'
                                                        ? 'bus_uuid'
                                                        : form.data
                                                                .center_type ===
                                                            'line'
                                                          ? 'line_uuid'
                                                          : 'departure_uuid';
                                                form.setData(
                                                    key,
                                                    e.target.value,
                                                );
                                            }}
                                            required
                                        >
                                            <option value="">
                                                Sélectionner{' '}
                                                {form.data.center_type === 'bus'
                                                    ? 'le bus'
                                                    : form.data.center_type ===
                                                        'line'
                                                      ? 'la ligne'
                                                      : 'le départ'}
                                            </option>
                                            {centerOptions[
                                                form.data
                                                    .center_type as keyof typeof centerOptions
                                            ].map((center) => (
                                                <option
                                                    key={center.uuid}
                                                    value={center.uuid}
                                                >
                                                    {center.label}
                                                </option>
                                            ))}
                                        </select>
                                        {(errors.bus_uuid ||
                                            errors.line_uuid ||
                                            errors.departure_uuid) && (
                                            <small className="field-error-text">
                                                {errors.bus_uuid ??
                                                    errors.line_uuid ??
                                                    errors.departure_uuid}
                                            </small>
                                        )}
                                    </label>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Section 2 : Détails Atelier & Maintenance (Dynamique) */}
                    {isMaintenance && (
                        <div className="cash-opening-row rounded-xl border border-gold/40 bg-gold/5 transition-all">
                            <div className="cash-opening-row-head">
                                <span className="cash-opening-icon">
                                    <Wrench className="size-4" />
                                </span>
                                <div>
                                    <strong className="text-[var(--kora)]">
                                        Détails Atelier & Maintenance
                                    </strong>
                                    <small>
                                        Typologie d'intervention, prestataire et
                                        ventilation des coûts
                                    </small>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <label className="field">
                                    <span className="field-label">
                                        <Wrench className="size-3.5" />
                                        Type de maintenance{' '}
                                        <b className="font-black text-destructive">
                                            *
                                        </b>
                                    </span>
                                    <select
                                        className="control"
                                        value={form.data.maintenance_type}
                                        onChange={(e) =>
                                            form.setData(
                                                'maintenance_type',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    >
                                        <option value="corrective">
                                            Corrective (Panne / Réparation)
                                        </option>
                                        <option value="preventive">
                                            Préventive (Entretien / Révision)
                                        </option>
                                        <option value="diagnostic">
                                            Diagnostic / Expertise
                                        </option>
                                    </select>
                                    <small>
                                        Classification de l'intervention
                                        technique.
                                    </small>
                                </label>

                                <label className="field">
                                    <span className="field-label">
                                        <Warehouse className="size-3.5" />
                                        Garage / Atelier prestataire
                                    </span>
                                    <input
                                        className="control"
                                        value={form.data.garage_label}
                                        onChange={(e) =>
                                            form.setData(
                                                'garage_label',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="ex: Garage Central Cotonou"
                                    />
                                    <small>
                                        Nom de l'atelier externe ou interne.
                                    </small>
                                </label>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <label className="field">
                                    <span className="field-label">
                                        <Coins className="size-3.5" />
                                        Pièces détachées (XOF)
                                    </span>
                                    <input
                                        className="control font-mono"
                                        type="number"
                                        min="0"
                                        value={form.data.parts_amount}
                                        onChange={(e) =>
                                            form.setData(
                                                'parts_amount',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="0"
                                    />
                                    <small>
                                        Montant facturé pour les pièces.
                                    </small>
                                </label>

                                <label className="field">
                                    <span className="field-label">
                                        <Hammer className="size-3.5" />
                                        Main-d’œuvre (XOF)
                                    </span>
                                    <input
                                        className="control font-mono"
                                        type="number"
                                        min="0"
                                        value={form.data.labor_amount}
                                        onChange={(e) =>
                                            form.setData(
                                                'labor_amount',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="0"
                                    />
                                    <small>
                                        Montant de la prestation horaire.
                                    </small>
                                </label>
                            </div>
                        </div>
                    )}

                    {/* Section 3 : Règlement & Bénéficiaire */}
                    <div className="cash-opening-row">
                        <div className="cash-opening-row-head">
                            <span className="cash-opening-icon">
                                <CreditCard className="size-4" />
                            </span>
                            <div>
                                <strong>Règlement & Bénéficiaire</strong>
                                <small>
                                    Canal de décaissement, montant et
                                    destinataire des fonds
                                </small>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <label className="field">
                                <span className="field-label">
                                    <CreditCard className="size-3.5" />
                                    Moyen de paiement{' '}
                                    <b className="font-black text-destructive">
                                        *
                                    </b>
                                </span>
                                <select
                                    className="control"
                                    value={form.data.payment_channel}
                                    onChange={(e) =>
                                        form.setData(
                                            'payment_channel',
                                            e.target.value,
                                        )
                                    }
                                    required
                                >
                                    {Object.entries(channels).map(
                                        ([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                                {errors.payment_channel ? (
                                    <small className="field-error-text">
                                        {errors.payment_channel}
                                    </small>
                                ) : (
                                    <small>
                                        Support utilisé pour le règlement.
                                    </small>
                                )}
                            </label>

                            <label className="field">
                                <span className="field-label">
                                    <Banknote className="size-3.5" />
                                    Montant de la dépense{' '}
                                    <b className="font-black text-destructive">
                                        *
                                    </b>
                                </span>
                                <div className="relative flex items-center">
                                    <input
                                        className="control pr-16 font-mono text-base font-bold tracking-tight"
                                        type="number"
                                        min="1"
                                        step="1"
                                        inputMode="numeric"
                                        value={form.data.amount}
                                        onChange={(e) =>
                                            form.setData(
                                                'amount',
                                                e.target.value,
                                            )
                                        }
                                        required
                                        placeholder="ex: 25000"
                                        aria-invalid={!!errors.amount}
                                    />
                                    <span className="pointer-events-none absolute right-3 inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs font-extrabold text-muted-foreground">
                                        FCFA
                                    </span>
                                </div>
                                {errors.amount ? (
                                    <small className="field-error-text">
                                        {errors.amount}
                                    </small>
                                ) : (
                                    <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                                        <span>
                                            Montant total en Francs CFA.
                                        </span>
                                        {form.data.amount &&
                                            !isNaN(Number(form.data.amount)) &&
                                            Number(form.data.amount) > 0 && (
                                                <span className="font-mono font-bold text-foreground">
                                                    {formatMoney(
                                                        form.data.amount,
                                                    )}
                                                </span>
                                            )}
                                    </div>
                                )}
                            </label>
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <label className="field">
                                <span className="field-label">
                                    <User className="size-3.5" />
                                    Bénéficiaire / Prestataire{' '}
                                    <b className="font-black text-destructive">
                                        *
                                    </b>
                                </span>
                                <input
                                    className="control"
                                    value={form.data.payee_label}
                                    onChange={(e) =>
                                        form.setData(
                                            'payee_label',
                                            e.target.value,
                                        )
                                    }
                                    required
                                    placeholder="ex: Station Total Étoile Rouge"
                                    aria-invalid={!!errors.payee_label}
                                />
                                {errors.payee_label ? (
                                    <small className="field-error-text">
                                        {errors.payee_label}
                                    </small>
                                ) : (
                                    <small>
                                        Nom de la personne ou entreprise ayant
                                        reçu le paiement.
                                    </small>
                                )}
                            </label>

                            <label className="field">
                                <span className="field-label">
                                    <Hash className="size-3.5" />
                                    Réf. facture / Pièce tiers
                                </span>
                                <input
                                    className="control font-mono"
                                    value={form.data.payee_reference}
                                    onChange={(e) =>
                                        form.setData(
                                            'payee_reference',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="ex: FACT-2026-0042"
                                />
                                <small>
                                    Numéro de facture ou référence du reçu
                                    fournisseur.
                                </small>
                            </label>
                        </div>

                        <div className="grid grid-cols-1 gap-4">
                            <label className="field">
                                <span className="field-label">
                                    <BookOpen className="size-3.5" />
                                    Session de caisse
                                </span>
                                <select
                                    className="control"
                                    value={form.data.ledger_uuid}
                                    onChange={(e) =>
                                        form.setData(
                                            'ledger_uuid',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        — Aucune (à rapprocher plus tard) —
                                    </option>
                                    {ledgers.map((ledger) => (
                                        <option
                                            key={ledger.uuid}
                                            value={ledger.uuid}
                                        >
                                            {ledger.label}
                                        </option>
                                    ))}
                                </select>
                                <small>
                                    Caisse sur laquelle imputer la sortie
                                    d'argent, si elle est déjà connue.
                                </small>
                            </label>
                        </div>
                    </div>

                    {/* Section 4 : Justification & Horodatage */}
                    <div className="cash-opening-row">
                        <div className="cash-opening-row-head">
                            <span className="cash-opening-icon">
                                <Calendar className="size-4" />
                            </span>
                            <div>
                                <strong>Justification & Horodatage</strong>
                                <small>
                                    Date d'effet, pièce comptable et motif
                                </small>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <label className="field">
                                <span className="field-label">
                                    <Calendar className="size-3.5" />
                                    Date et heure de l'opération{' '}
                                    <b className="font-black text-destructive">
                                        *
                                    </b>
                                </span>
                                <input
                                    className="control"
                                    type="datetime-local"
                                    value={form.data.occurred_at}
                                    onChange={(e) =>
                                        form.setData(
                                            'occurred_at',
                                            e.target.value,
                                        )
                                    }
                                    required
                                    aria-invalid={!!errors.occurred_at}
                                />
                                {errors.occurred_at ? (
                                    <small className="field-error-text">
                                        {errors.occurred_at}
                                    </small>
                                ) : (
                                    <small>
                                        Moment exact où la dépense a été
                                        effectuée.
                                    </small>
                                )}
                            </label>

                            <div className="field">
                                <span className="field-label">
                                    <UploadCloud className="size-3.5" />
                                    Justificatif (Reçu / Facture){' '}
                                    <b className="font-black text-destructive">
                                        *
                                    </b>
                                </span>

                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp,application/pdf"
                                    className="sr-only"
                                    onChange={(e) => {
                                        const file =
                                            e.target.files?.[0] ?? null;
                                        handleFileSelect(file);
                                    }}
                                />

                                {!form.data.evidence ? (
                                    <div
                                        onDragOver={(e) => {
                                            e.preventDefault();
                                            setIsDragging(true);
                                        }}
                                        onDragLeave={(e) => {
                                            e.preventDefault();
                                            setIsDragging(false);
                                        }}
                                        onDrop={(e) => {
                                            e.preventDefault();
                                            setIsDragging(false);
                                            const file =
                                                e.dataTransfer.files?.[0] ??
                                                null;
                                            handleFileSelect(file);
                                        }}
                                        onClick={() =>
                                            fileInputRef.current?.click()
                                        }
                                        className={cn(
                                            'group flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed p-5 text-center transition-all duration-300',
                                            isDragging
                                                ? 'scale-[1.01] border-gold bg-gold/15 shadow-md'
                                                : 'border-border/80 bg-muted/20 hover:border-gold/60 hover:bg-gold/5',
                                            (errors.evidence || fileError) &&
                                                'border-destructive/60 bg-destructive/5',
                                        )}
                                    >
                                        <div className="grid size-10 place-items-center rounded-xl bg-gold/15 text-[var(--kora)] transition-transform duration-300 group-hover:scale-110">
                                            <UploadCloud className="size-5 text-gold" />
                                        </div>
                                        <div>
                                            <strong className="block text-xs font-bold text-foreground">
                                                Glissez-déposez votre
                                                justificatif ici
                                            </strong>
                                            <p className="mt-0.5 text-[11px] text-muted-foreground">
                                                ou{' '}
                                                <span className="font-bold text-gold underline underline-offset-2">
                                                    parcourir vos fichiers
                                                </span>
                                            </p>
                                        </div>
                                        <span className="text-[10px] font-medium text-muted-foreground">
                                            Formats : JPG, PNG, WEBP ou PDF (max
                                            5 Mo)
                                        </span>
                                    </div>
                                ) : (
                                    <div className="rounded-2xl border border-gold/40 bg-gold/5 p-3.5 transition-all">
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="flex min-w-0 items-center gap-3">
                                                <div className="grid size-9 shrink-0 place-items-center rounded-xl bg-gold/20 text-[var(--kora)]">
                                                    <FileText className="size-4.5 text-gold" />
                                                </div>
                                                <div className="min-w-0">
                                                    <strong className="block truncate text-xs font-bold text-foreground">
                                                        {
                                                            form.data.evidence
                                                                .name
                                                        }
                                                    </strong>
                                                    <span className="text-[11px] text-muted-foreground">
                                                        {form.processing &&
                                                        form.progress
                                                            ? `Envoi en cours… ${Math.round(form.progress.percentage ?? 0)}%`
                                                            : `${formatBytes(form.data.evidence.size)} · Prêt pour envoi`}
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-1.5">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        fileInputRef.current?.click()
                                                    }
                                                    disabled={form.processing}
                                                    className="rounded-lg bg-muted px-2.5 py-1 text-xs font-bold text-foreground transition-colors hover:bg-gold/15 disabled:opacity-50"
                                                >
                                                    Remplacer
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        form.setData(
                                                            'evidence',
                                                            null,
                                                        );

                                                        if (
                                                            fileInputRef.current
                                                        ) {
                                                            fileInputRef.current.value =
                                                                '';
                                                        }
                                                    }}
                                                    disabled={form.processing}
                                                    className="grid size-7 place-items-center rounded-lg bg-destructive/10 text-destructive transition-colors hover:bg-destructive/20 disabled:opacity-50"
                                                    aria-label="Supprimer le fichier"
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                        {form.processing && form.progress && (
                                            <div
                                                className="mt-3"
                                                role="progressbar"
                                                aria-valuemin={0}
                                                aria-valuemax={100}
                                                aria-valuenow={Math.round(
                                                    form.progress.percentage ??
                                                        0,
                                                )}
                                                aria-label="Progression de l'envoi du justificatif"
                                            >
                                                <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className="h-full rounded-full bg-gradient-to-r from-gold to-[var(--kora)] transition-all duration-300 ease-out"
                                                        style={{
                                                            width: `${Math.round(form.progress.percentage ?? 0)}%`,
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {fileError && (
                                    <small className="field-error-text font-semibold text-destructive">
                                        {fileError}
                                    </small>
                                )}
                                {errors.evidence && (
                                    <small className="field-error-text font-semibold text-destructive">
                                        {errors.evidence}
                                    </small>
                                )}
                            </div>
                        </div>

                        <label className="field">
                            <span className="field-label">
                                <AlignLeft className="size-3.5" />
                                Description / Motif détaillé
                            </span>
                            <textarea
                                className="control min-h-[4.5rem] py-2"
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                                placeholder="Précisez le contexte opérationnel ou la justification de ce décaissement…"
                            />
                            <small>
                                Explication complémentaire utile pour le
                                contrôleur financier.
                            </small>
                        </label>
                    </div>

                    {/* Aperçu dynamique : Reçu de décaissement */}
                    <div className="rounded-xl border border-gold/30 bg-gold/5 p-4 transition-all">
                        <div className="flex items-center justify-between border-b border-gold/20 pb-2.5">
                            <div className="flex items-center gap-2">
                                <Sparkles className="size-4 text-gold" />
                                <span className="text-[11px] font-black tracking-wider text-[var(--kora)] uppercase">
                                    Aperçu décaissement
                                </span>
                            </div>
                            <span className="rounded bg-gold/20 px-2 py-0.5 text-[10px] font-extrabold text-[var(--kora)]">
                                En attente de validation
                            </span>
                        </div>
                        <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div>
                                <span className="block text-[10px] font-bold text-muted-foreground uppercase">
                                    Catégorie
                                </span>
                                <span className="truncate text-xs font-bold text-foreground">
                                    {selectedCategory?.label || '—'}
                                </span>
                            </div>
                            <div>
                                <span className="block text-[10px] font-bold text-muted-foreground uppercase">
                                    Bénéficiaire
                                </span>
                                <span className="truncate text-xs font-bold text-foreground">
                                    {form.data.payee_label || '—'}
                                </span>
                            </div>
                            <div>
                                <span className="block text-[10px] font-bold text-muted-foreground uppercase">
                                    Montant
                                </span>
                                <span className="font-mono text-xs font-black text-foreground">
                                    {form.data.amount
                                        ? formatMoney(form.data.amount)
                                        : '—'}
                                </span>
                            </div>
                            <div>
                                <span className="block text-[10px] font-bold text-muted-foreground uppercase">
                                    Affectation
                                </span>
                                <span className="truncate text-xs font-medium text-foreground">
                                    {selectedCenterLabel ||
                                        selectedAgencyLabel ||
                                        'Structure'}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Affichage d'erreur globale */}
                    {(errors.maintenance ||
                        errors.bus_uuid ||
                        errors.line_uuid ||
                        errors.departure_uuid) && (
                        <div className="cash-form-error" role="alert">
                            {errors.maintenance ??
                                errors.bus_uuid ??
                                errors.line_uuid ??
                                errors.departure_uuid}
                        </div>
                    )}
                </div>

                {/* Boutons d'action style Caisse */}
                <div className="cash-modal-actions mt-6">
                    <button
                        type="button"
                        onClick={() => onOpenChange(false)}
                        className="btn btn-ghost"
                        disabled={form.processing}
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="btn btn-primary"
                    >
                        <Check className="size-4" />
                        {form.processing
                            ? 'Enregistrement…'
                            : 'Envoyer pour validation'}
                    </button>
                </div>
            </form>
        </div>
    );
}
