import {
    X,
    Store,
    Building2,
    MapPin,
    User,
    Coins,
    FileText,
    UploadCloud,
    CheckCircle2,
    AlertCircle,
    ArrowRight,
    ArrowLeft,
} from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import type { AdminOrganization } from '../mock/kora-point-mock-data';

type Props = {
    isOpen: boolean;
    organizations: AdminOrganization[];
    onClose: () => void;
    onSubmit: (data: {
        name: string;
        type: 'AUTONOMOUS' | 'ORGANIZATION_ATTACHED';
        organizationName?: string;
        organizationUuid?: string;
        city: string;
        department: string;
        managerName: string;
        phone: string;
        creditLimit: number;
        commissionRateBps: number;
        settlementFrequencyDays: number;
        gracePeriodDays: number;
    }) => void;
};

export function KoraAdminPointWizardDialog({
    isOpen,
    organizations,
    onClose,
    onSubmit,
}: Props) {
    const [step, setStep] = useState<1 | 2 | 3 | 4>(1);

    // Step 1: Identité & Localisation
    const [name, setName] = useState('');
    const [city, setCity] = useState('Cotonou');
    const [department, setDepartment] = useState('Littoral');
    const [address, setAddress] = useState('');

    // Step 2: Type & Organisation
    const [pointType, setPointType] = useState<
        'AUTONOMOUS' | 'ORGANIZATION_ATTACHED'
    >('AUTONOMOUS');
    const [selectedOrgUuid, setSelectedOrgUuid] = useState<string>(
        organizations[0]?.uuid || '',
    );

    // Step 3: Conditions financières
    const [commissionRateBps, setCommissionRateBps] = useState<number>(300);
    const [creditLimit, setCreditLimit] = useState<number>(500000);
    const [settlementFrequencyDays, setSettlementFrequencyDays] =
        useState<number>(7);
    const [gracePeriodDays, setGracePeriodDays] = useState<number>(3);

    // Step 4: Gérant & Pièces légales
    const [managerName, setManagerName] = useState('');
    const [phone, setPhone] = useState('+229 ');
    const [email, setEmail] = useState('');
    const [proofFile, setProofFile] = useState<{
        name: string;
        size: string;
    } | null>({
        name: 'rccm_ifu_piece_identite_gerant.pdf',
        size: '1.4 Mo',
    });

    const [error, setError] = useState<string | null>(null);

    if (!isOpen) {
        return null;
    }

    // Handle organization selection change (auto-prefill inherited terms)
    const handleOrgChange = (orgUuid: string) => {
        setSelectedOrgUuid(orgUuid);
        const org = organizations.find((o) => o.uuid === orgUuid);

        if (org) {
            setCommissionRateBps(org.default_terms.commission_rate_bps);
            setCreditLimit(org.default_terms.credit_limit / 5); // Default per-subpoint share
            setSettlementFrequencyDays(
                org.default_terms.settlement_frequency_days,
            );
            setGracePeriodDays(org.default_terms.grace_period_days);
        }
    };

    const handleNext = () => {
        setError(null);

        if (step === 1) {
            if (!name.trim()) {
                setError('Veuillez renseigner le nom commercial du Point.');

                return;
            }

            if (!city.trim()) {
                setError('Veuillez préciser la ville d’implantation.');

                return;
            }

            setStep(2);
        } else if (step === 2) {
            if (pointType === 'ORGANIZATION_ATTACHED' && selectedOrgUuid) {
                handleOrgChange(selectedOrgUuid);
            }

            setStep(3);
        } else if (step === 3) {
            if (creditLimit <= 0) {
                setError('Le plafond de crédit doit être supérieur à 0 FCFA.');

                return;
            }

            setStep(4);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);

        if (!managerName.trim()) {
            setError('Le nom du gérant ou responsable de bureau est requis.');

            return;
        }

        if (!phone.trim() || phone.length < 8) {
            setError('Un numéro de téléphone valide est requis.');

            return;
        }

        const org = organizations.find((o) => o.uuid === selectedOrgUuid);

        onSubmit({
            name: name.trim(),
            type: pointType,
            organizationName:
                pointType === 'ORGANIZATION_ATTACHED' ? org?.name : undefined,
            organizationUuid:
                pointType === 'ORGANIZATION_ATTACHED'
                    ? selectedOrgUuid
                    : undefined,
            city: city.trim(),
            department,
            managerName: managerName.trim(),
            phone: phone.trim(),
            creditLimit,
            commissionRateBps,
            settlementFrequencyDays,
            gracePeriodDays,
        });

        onClose();
    };

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onMouseDown={(e) => e.target === e.currentTarget && onClose()}
        >
            <div
                className="cash-modal cash-opening-modal w-[44rem]! max-w-[95vw]!"
                role="dialog"
                aria-modal="true"
                aria-labelledby="kora-wizard-modal-title"
            >
                {/* Header */}
                <div className="cash-modal-head">
                    <div className="flex items-start gap-3">
                        <span className="cash-opening-icon">
                            <Store className="size-5" />
                        </span>
                        <div>
                            <span className="section-kicker">
                                Réseau commercial · Nouveau point
                            </span>
                            <h3 id="kora-wizard-modal-title">
                                Déployer un nouveau Point de vente KORA
                            </h3>
                            <p>
                                Étape {step} sur 4 · Administration du réseau
                                commercial.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="cash-modal-close"
                        aria-label="Fermer"
                    >
                        <X className="size-4" />
                    </button>
                </div>

                {/* Stepper Progress */}
                <div className="mx-6 mt-2 flex gap-1 rounded-xl bg-muted/50 p-1">
                    {[
                        'Localisation',
                        'Rattachement',
                        'Conditions',
                        'Gérant & Pièces',
                    ].map((label, index) => {
                        const stepNumber = index + 1;

                        return (
                            <button
                                key={label}
                                type="button"
                                onClick={() => {
                                    if (stepNumber <= step) {
                                        setStep(stepNumber as 1 | 2 | 3 | 4);
                                    }
                                }}
                                className={cn(
                                    'flex-1 rounded-lg px-3 py-2 text-xs font-bold transition',
                                    step === stepNumber
                                        ? 'bg-card text-foreground shadow-sm'
                                        : stepNumber < step
                                          ? 'text-foreground'
                                          : 'text-muted-foreground',
                                )}
                            >
                                {stepNumber}. {label}
                            </button>
                        );
                    })}
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="cash-opening-list mt-2"
                >
                    {error && (
                        <div className="mx-6 flex items-center gap-2 rounded-xl border border-destructive/30 bg-destructive/10 p-3.5 text-xs font-semibold text-destructive">
                            <AlertCircle className="h-4 w-4 shrink-0" />
                            <span>{error}</span>
                        </div>
                    )}

                    {/* Step 1: Identité & Localisation */}
                    {step === 1 && (
                        <div className="cash-opening-row">
                            <div className="cash-opening-row-head">
                                <span className="cash-opening-icon">
                                    <MapPin className="size-4" />
                                </span>
                                <div>
                                    <strong>Identité & localisation</strong>
                                    <small>
                                        Dénomination commerciale et site
                                        d’implantation du point
                                    </small>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4">
                                <label className="field">
                                    <span>
                                        Nom commercial du Point{' '}
                                        <b className="font-black text-destructive">
                                            *
                                        </b>
                                    </span>
                                    <input
                                        type="text"
                                        value={name}
                                        onChange={(e) =>
                                            setName(e.target.value)
                                        }
                                        placeholder="Ex: Kiosque Relais Étoile Rouge"
                                        required
                                    />
                                </label>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <label className="field">
                                        <span>
                                            Ville d’implantation{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <input
                                            type="text"
                                            value={city}
                                            onChange={(e) =>
                                                setCity(e.target.value)
                                            }
                                            placeholder="Ex: Cotonou, Parakou, Bohicon..."
                                            required
                                        />
                                    </label>

                                    <label className="field">
                                        <span>
                                            Département{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <select
                                            value={department}
                                            onChange={(e) =>
                                                setDepartment(e.target.value)
                                            }
                                        >
                                            <option value="Littoral">
                                                Littoral (Cotonou)
                                            </option>
                                            <option value="Atlantique">
                                                Atlantique (Calavi, Ouidah)
                                            </option>
                                            <option value="Ouémé">
                                                Ouémé (Porto-Novo)
                                            </option>
                                            <option value="Borgou">
                                                Borgou (Parakou)
                                            </option>
                                            <option value="Zou">
                                                Zou (Bohicon, Abomey)
                                            </option>
                                            <option value="Atacora">
                                                Atacora (Natitingou)
                                            </option>
                                            <option value="Mono">
                                                Mono (Lokossa)
                                            </option>
                                        </select>
                                    </label>
                                </div>

                                <label className="field">
                                    <span>
                                        Adresse précise / Repère géographique
                                    </span>
                                    <input
                                        type="text"
                                        value={address}
                                        onChange={(e) =>
                                            setAddress(e.target.value)
                                        }
                                        placeholder="Ex: Carrefour Vêdoko, face pharmacie de l'Amitié"
                                    />
                                </label>
                            </div>
                        </div>
                    )}

                    {/* Step 2: Type & Rattachement */}
                    {step === 2 && (
                        <div className="cash-opening-row">
                            <div className="cash-opening-row-head">
                                <span className="cash-opening-icon">
                                    <Building2 className="size-4" />
                                </span>
                                <div>
                                    <strong>Modèle d’exploitation</strong>
                                    <small>
                                        Point autonome ou rattaché à une
                                        organisation partenaire
                                    </small>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div
                                    onClick={() => setPointType('AUTONOMOUS')}
                                    className={cn(
                                        'cursor-pointer space-y-2 rounded-xl border-2 p-4 transition-all',
                                        pointType === 'AUTONOMOUS'
                                            ? 'border-gold bg-gold/5'
                                            : 'border-border hover:border-muted-foreground/40',
                                    )}
                                >
                                    <div className="flex items-center gap-2">
                                        <Store className="h-5 w-5 text-gold" />
                                        <span className="text-sm font-bold text-foreground">
                                            Point Autonome
                                        </span>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Commerçant indépendant gérant ses
                                        propres reversements hebdomadaires et sa
                                        caisse.
                                    </p>
                                </div>

                                <div
                                    onClick={() =>
                                        setPointType('ORGANIZATION_ATTACHED')
                                    }
                                    className={cn(
                                        'cursor-pointer space-y-2 rounded-xl border-2 p-4 transition-all',
                                        pointType === 'ORGANIZATION_ATTACHED'
                                            ? 'border-gold bg-gold/5'
                                            : 'border-border hover:border-muted-foreground/40',
                                    )}
                                >
                                    <div className="flex items-center gap-2">
                                        <Building2 className="h-5 w-5 text-gold" />
                                        <span className="text-sm font-bold text-foreground">
                                            Rattaché à une Organisation
                                        </span>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Agence faisant partie d'un réseau
                                        structuré (La Poste, etc.). Reversements
                                        centralisés par le Trésorier.
                                    </p>
                                </div>
                            </div>

                            {pointType === 'ORGANIZATION_ATTACHED' && (
                                <label className="field pt-2">
                                    <span>
                                        Organisation partenaire de rattachement{' '}
                                        <b className="font-black text-destructive">
                                            *
                                        </b>
                                    </span>
                                    <select
                                        value={selectedOrgUuid}
                                        onChange={(e) =>
                                            handleOrgChange(e.target.value)
                                        }
                                    >
                                        {organizations.map((org) => (
                                            <option
                                                key={org.uuid}
                                                value={org.uuid}
                                            >
                                                {org.name} ({org.points_count}{' '}
                                                bureaux · Convention cadre
                                                active)
                                            </option>
                                        ))}
                                    </select>
                                    <small className="text-emerald-600">
                                        Les conditions financières seront
                                        pré-remplies selon l'accord-cadre de
                                        l'organisation.
                                    </small>
                                </label>
                            )}
                        </div>
                    )}

                    {/* Step 3: Conditions financières */}
                    {step === 3 && (
                        <div className="cash-opening-row">
                            <div className="cash-opening-row-head">
                                <span className="cash-opening-icon">
                                    <Coins className="size-4" />
                                </span>
                                <div>
                                    <strong>Conditions financières</strong>
                                    <small>
                                        Commission, plafond d'encours et
                                        calendrier de reversement
                                    </small>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <label className="field">
                                    <span>
                                        Taux de commission (bps){' '}
                                        <b className="font-black text-destructive">
                                            *
                                        </b>
                                    </span>
                                    <div className="relative">
                                        <input
                                            type="number"
                                            value={commissionRateBps}
                                            onChange={(e) =>
                                                setCommissionRateBps(
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                        <span className="absolute top-2.5 right-3 text-xs font-bold text-muted-foreground">
                                            {(commissionRateBps / 100).toFixed(
                                                2,
                                            )}{' '}
                                            %
                                        </span>
                                    </div>
                                    <small className="text-muted-foreground">
                                        Standard KORA : 300 bps (3,00 %)
                                    </small>
                                </label>

                                <label className="field">
                                    <span>
                                        Plafond de crédit initial{' '}
                                        <b className="font-black text-destructive">
                                            *
                                        </b>
                                    </span>
                                    <div className="relative">
                                        <input
                                            type="number"
                                            step={50000}
                                            value={creditLimit}
                                            onChange={(e) =>
                                                setCreditLimit(
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                        <span className="absolute top-2.5 right-3 text-xs font-bold text-muted-foreground">
                                            FCFA
                                        </span>
                                    </div>
                                    <small className="text-muted-foreground">
                                        Encours maximal autorisé avant blocage
                                        des ventes
                                    </small>
                                </label>

                                <label className="field">
                                    <span>Fréquence de reversement</span>
                                    <select
                                        value={settlementFrequencyDays}
                                        onChange={(e) =>
                                            setSettlementFrequencyDays(
                                                Number(e.target.value),
                                            )
                                        }
                                    >
                                        <option value={7}>
                                            Hebdomadaire (7 jours · Recommandé
                                            autonome)
                                        </option>
                                        <option value={14}>
                                            Bimensuel (14 jours)
                                        </option>
                                        <option value={30}>
                                            Mensuel (30 jours · Organisations)
                                        </option>
                                    </select>
                                </label>

                                <label className="field">
                                    <span>Délai de grâce avant suspension</span>
                                    <select
                                        value={gracePeriodDays}
                                        onChange={(e) =>
                                            setGracePeriodDays(
                                                Number(e.target.value),
                                            )
                                        }
                                    >
                                        <option value={3}>
                                            3 jours ouvrés
                                        </option>
                                        <option value={5}>
                                            5 jours ouvrés
                                        </option>
                                        <option value={7}>
                                            7 jours ouvrés
                                        </option>
                                    </select>
                                </label>
                            </div>
                        </div>
                    )}

                    {/* Step 4: Gérant & Pièces */}
                    {step === 4 && (
                        <div className="cash-opening-row">
                            <div className="cash-opening-row-head">
                                <span className="cash-opening-icon">
                                    <User className="size-4" />
                                </span>
                                <div>
                                    <strong>Gérant & pièces légales</strong>
                                    <small>
                                        Responsable du point et dossier
                                        juridique justificatif
                                    </small>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4">
                                <label className="field">
                                    <span>
                                        Nom et prénom du gérant / chef de bureau{' '}
                                        <b className="font-black text-destructive">
                                            *
                                        </b>
                                    </span>
                                    <input
                                        type="text"
                                        value={managerName}
                                        onChange={(e) =>
                                            setManagerName(e.target.value)
                                        }
                                        placeholder="Ex: Bio Kora, Jean Dossou..."
                                        required
                                    />
                                </label>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <label className="field">
                                        <span>
                                            Numéro de téléphone{' '}
                                            <b className="font-black text-destructive">
                                                *
                                            </b>
                                        </span>
                                        <input
                                            type="tel"
                                            value={phone}
                                            onChange={(e) =>
                                                setPhone(e.target.value)
                                            }
                                            placeholder="+229 97 00 00 00"
                                            required
                                        />
                                    </label>

                                    <label className="field">
                                        <span>Email professionnel</span>
                                        <input
                                            type="email"
                                            value={email}
                                            onChange={(e) =>
                                                setEmail(e.target.value)
                                            }
                                            placeholder="agent@kora.bj"
                                        />
                                    </label>
                                </div>

                                {/* Documents Upload */}
                                <label className="field">
                                    <span>
                                        Dossier légal (RCCM, IFU, Pièce
                                        d'identité)
                                    </span>
                                    {proofFile ? (
                                        <div className="flex items-center justify-between rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3.5 text-xs">
                                            <div className="flex items-center gap-2.5">
                                                <FileText className="h-5 w-5 text-emerald-600" />
                                                <div>
                                                    <p className="font-bold text-foreground">
                                                        {proofFile.name}
                                                    </p>
                                                    <span className="text-muted-foreground">
                                                        {proofFile.size} ·
                                                        Validé
                                                    </span>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setProofFile(null)
                                                }
                                                className="text-xs font-bold text-muted-foreground hover:text-destructive"
                                            >
                                                Remplacer
                                            </button>
                                        </div>
                                    ) : (
                                        <div className="rounded-xl border-2 border-dashed border-border p-4 text-center">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setProofFile({
                                                        name: 'dossier_juridique_point.pdf',
                                                        size: '2.1 Mo',
                                                    })
                                                }
                                                className="btn btn-ghost btn-sm"
                                            >
                                                <UploadCloud className="size-4" />
                                                Sélectionner le dossier PDF
                                            </button>
                                        </div>
                                    )}
                                </label>
                            </div>
                        </div>
                    )}

                    {/* Navigation Buttons */}
                    <div className="cash-modal-actions mt-6">
                        {step > 1 ? (
                            <button
                                type="button"
                                onClick={() => setStep((s) => (s - 1) as any)}
                                className="btn btn-ghost"
                            >
                                <ArrowLeft className="size-4" />
                                Précédent
                            </button>
                        ) : (
                            <button
                                type="button"
                                onClick={onClose}
                                className="btn btn-ghost"
                            >
                                Annuler
                            </button>
                        )}

                        {step < 4 ? (
                            <button
                                type="button"
                                onClick={handleNext}
                                className="btn btn-primary"
                            >
                                Suivant
                                <ArrowRight className="size-4" />
                            </button>
                        ) : (
                            <button type="submit" className="btn btn-primary">
                                <CheckCircle2 className="size-4" />
                                Finaliser & Déployer le Point
                            </button>
                        )}
                    </div>
                </form>
            </div>
        </div>
    );
}
