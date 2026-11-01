import {
    X,
    Store,
    Building2,
    MapPin,
    User,
    Phone,
    Mail,
    Coins,
    Calendar,
    FileText,
    UploadCloud,
    CheckCircle2,
    AlertCircle,
    ArrowRight,
    ArrowLeft,
    Shield,
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
    const [pointType, setPointType] = useState<'AUTONOMOUS' | 'ORGANIZATION_ATTACHED'>('AUTONOMOUS');
    const [selectedOrgUuid, setSelectedOrgUuid] = useState<string>(organizations[0]?.uuid || '');

    // Step 3: Conditions financières
    const [commissionRateBps, setCommissionRateBps] = useState<number>(300);
    const [creditLimit, setCreditLimit] = useState<number>(500000);
    const [settlementFrequencyDays, setSettlementFrequencyDays] = useState<number>(7);
    const [gracePeriodDays, setGracePeriodDays] = useState<number>(3);

    // Step 4: Gérant & Pièces légales
    const [managerName, setManagerName] = useState('');
    const [phone, setPhone] = useState('+229 ');
    const [email, setEmail] = useState('');
    const [proofFile, setProofFile] = useState<{ name: string; size: string } | null>({
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
            setSettlementFrequencyDays(org.default_terms.settlement_frequency_days);
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
            organizationName: pointType === 'ORGANIZATION_ATTACHED' ? org?.name : undefined,
            organizationUuid: pointType === 'ORGANIZATION_ATTACHED' ? selectedOrgUuid : undefined,
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
            className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm overflow-y-auto animate-in fade-in duration-200"
            role="dialog"
            aria-modal="true"
        >
            <div className="relative my-8 w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900 overflow-hidden">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4 dark:border-slate-800 dark:bg-slate-800/40">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gold/15 text-gold-dark dark:bg-gold/25 dark:text-gold">
                            <Store className="h-5 w-5" />
                        </div>
                        <div>
                            <h2 className="text-base font-black text-slate-900 dark:text-white">
                                Déployer un nouveau Point de vente KORA
                            </h2>
                            <p className="text-xs text-slate-500">
                                Étape {step} sur 4 · Administration Réseau Commercial
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {/* Stepper Progress */}
                <div className="grid grid-cols-4 border-b border-slate-200 text-center text-xs font-bold dark:border-slate-800">
                    <div
                        className={cn(
                            'py-2.5 border-b-2 transition-colors',
                            step >= 1
                                ? 'border-gold text-slate-900 dark:text-white'
                                : 'border-transparent text-slate-400',
                        )}
                    >
                        1. Localisation
                    </div>
                    <div
                        className={cn(
                            'py-2.5 border-b-2 transition-colors',
                            step >= 2
                                ? 'border-gold text-slate-900 dark:text-white'
                                : 'border-transparent text-slate-400',
                        )}
                    >
                        2. Rattachement
                    </div>
                    <div
                        className={cn(
                            'py-2.5 border-b-2 transition-colors',
                            step >= 3
                                ? 'border-gold text-slate-900 dark:text-white'
                                : 'border-transparent text-slate-400',
                        )}
                    >
                        3. Conditions
                    </div>
                    <div
                        className={cn(
                            'py-2.5 border-b-2 transition-colors',
                            step >= 4
                                ? 'border-gold text-slate-900 dark:text-white'
                                : 'border-transparent text-slate-400',
                        )}
                    >
                        4. Gérant & Pièces
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-5">
                    {error && (
                        <div className="rounded-xl border border-rose-300 bg-rose-50 p-4 text-xs font-semibold text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200 flex items-center gap-2">
                            <AlertCircle className="h-4 w-4 shrink-0 text-rose-600" />
                            <span>{error}</span>
                        </div>
                    )}

                    {/* Step 1: Identité & Localisation */}
                    {step === 1 && (
                        <div className="space-y-4">
                            <div className="space-y-1.5">
                                <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                                    <Store className="mr-1.5 h-4 w-4 text-gold-dark dark:text-gold" />
                                    Nom commercial du Point
                                    <span className="text-red-500 font-bold ml-1">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    placeholder="Ex: Kiosque Relais Étoile Rouge"
                                    className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    required
                                />
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-1.5">
                                    <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <MapPin className="mr-1.5 h-4 w-4 text-slate-500" />
                                        Ville d’implantation
                                        <span className="text-red-500 font-bold ml-1">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={city}
                                        onChange={(e) => setCity(e.target.value)}
                                        placeholder="Ex: Cotonou, Parakou, Bohicon..."
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        required
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <MapPin className="mr-1.5 h-4 w-4 text-slate-500" />
                                        Département
                                        <span className="text-red-500 font-bold ml-1">*</span>
                                    </label>
                                    <select
                                        value={department}
                                        onChange={(e) => setDepartment(e.target.value)}
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    >
                                        <option value="Littoral">Littoral (Cotonou)</option>
                                        <option value="Atlantique">Atlantique (Calavi, Ouidah)</option>
                                        <option value="Ouémé">Ouémé (Porto-Novo)</option>
                                        <option value="Borgou">Borgou (Parakou)</option>
                                        <option value="Zou">Zou (Bohicon, Abomey)</option>
                                        <option value="Atacora">Atacora (Natitingou)</option>
                                        <option value="Mono">Mono (Lokossa)</option>
                                    </select>
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                                    <MapPin className="mr-1.5 h-4 w-4 text-slate-500" />
                                    Adresse précise / Repère géographique
                                </label>
                                <input
                                    type="text"
                                    value={address}
                                    onChange={(e) => setAddress(e.target.value)}
                                    placeholder="Ex: Carrefour Vêdoko, face pharmacie de l'Amitié"
                                    className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                />
                            </div>
                        </div>
                    )}

                    {/* Step 2: Type & Rattachement */}
                    {step === 2 && (
                        <div className="space-y-4">
                            <label className="text-xs font-bold text-slate-700 dark:text-slate-300 block">
                                Modèle d’exploitation du Point
                                <span className="text-red-500 font-bold ml-1">*</span>
                            </label>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div
                                    onClick={() => setPointType('AUTONOMOUS')}
                                    className={cn(
                                        'rounded-2xl border-2 p-4 cursor-pointer transition-all space-y-2',
                                        pointType === 'AUTONOMOUS'
                                            ? 'border-gold bg-gold/5 dark:bg-gold/10'
                                            : 'border-slate-200 hover:border-slate-300 dark:border-slate-800',
                                    )}
                                >
                                    <div className="flex items-center gap-2">
                                        <Store className="h-5 w-5 text-gold-dark dark:text-gold" />
                                        <span className="font-bold text-sm text-slate-900 dark:text-white">
                                            Point Autonome
                                        </span>
                                    </div>
                                    <p className="text-xs text-slate-500">
                                        Commerçant indépendant gérant ses propres reversements hebdomadaires et sa caisse.
                                    </p>
                                </div>

                                <div
                                    onClick={() => setPointType('ORGANIZATION_ATTACHED')}
                                    className={cn(
                                        'rounded-2xl border-2 p-4 cursor-pointer transition-all space-y-2',
                                        pointType === 'ORGANIZATION_ATTACHED'
                                            ? 'border-gold bg-gold/5 dark:bg-gold/10'
                                            : 'border-slate-200 hover:border-slate-300 dark:border-slate-800',
                                    )}
                                >
                                    <div className="flex items-center gap-2">
                                        <Building2 className="h-5 w-5 text-navy dark:text-gold" />
                                        <span className="font-bold text-sm text-slate-900 dark:text-white">
                                            Rattaché à une Organisation
                                        </span>
                                    </div>
                                    <p className="text-xs text-slate-500">
                                        Agence faisant partie d'un réseau structuré (La Poste, etc.). Reversements centralisés par le Trésorier.
                                    </p>
                                </div>
                            </div>

                            {pointType === 'ORGANIZATION_ATTACHED' && (
                                <div className="space-y-1.5 pt-2">
                                    <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <Building2 className="mr-1.5 h-4 w-4 text-slate-500" />
                                        Organisation partenaire de rattachement
                                        <span className="text-red-500 font-bold ml-1">*</span>
                                    </label>
                                    <select
                                        value={selectedOrgUuid}
                                        onChange={(e) => handleOrgChange(e.target.value)}
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-bold text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    >
                                        {organizations.map((org) => (
                                            <option key={org.uuid} value={org.uuid}>
                                                {org.name} ({org.points_count} bureaux · Convention cadre active)
                                            </option>
                                        ))}
                                    </select>
                                    <p className="text-[11px] text-blue-600 dark:text-blue-400">
                                        ✓ Les conditions financières seront pré-remplies selon l'accord-cadre de l'organisation.
                                    </p>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Step 3: Conditions financières */}
                    {step === 3 && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-1.5">
                                    <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <Coins className="mr-1.5 h-4 w-4 text-gold-dark dark:text-gold" />
                                        Taux de commission (bps)
                                        <span className="text-red-500 font-bold ml-1">*</span>
                                    </label>
                                    <div className="relative">
                                        <input
                                            type="number"
                                            value={commissionRateBps}
                                            onChange={(e) => setCommissionRateBps(Number(e.target.value))}
                                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-bold text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        />
                                        <span className="absolute right-3 top-2.5 text-xs font-bold text-slate-400">
                                            {(commissionRateBps / 100).toFixed(2)} %
                                        </span>
                                    </div>
                                    <p className="text-[10px] text-slate-400">Standard KORA : 300 bps (3,00 %)</p>
                                </div>

                                <div className="space-y-1.5">
                                    <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <Coins className="mr-1.5 h-4 w-4 text-slate-500" />
                                        Plafond de crédit initial
                                        <span className="text-red-500 font-bold ml-1">*</span>
                                    </label>
                                    <div className="relative">
                                        <input
                                            type="number"
                                            step={50000}
                                            value={creditLimit}
                                            onChange={(e) => setCreditLimit(Number(e.target.value))}
                                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-bold text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        />
                                        <span className="absolute right-3 top-2.5 text-xs font-bold text-slate-400">
                                            FCFA
                                        </span>
                                    </div>
                                    <p className="text-[10px] text-slate-400">Encours maximal autorisé avant blocage des ventes</p>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-1.5">
                                    <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <Calendar className="mr-1.5 h-4 w-4 text-slate-500" />
                                        Fréquence de reversement
                                    </label>
                                    <select
                                        value={settlementFrequencyDays}
                                        onChange={(e) => setSettlementFrequencyDays(Number(e.target.value))}
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    >
                                        <option value={7}>Hebdomadaire (7 jours · Recommandé autonome)</option>
                                        <option value={14}>Bimensuel (14 jours)</option>
                                        <option value={30}>Mensuel (30 jours · Organisations)</option>
                                    </select>
                                </div>

                                <div className="space-y-1.5">
                                    <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <Shield className="mr-1.5 h-4 w-4 text-slate-500" />
                                        Délai de grâce avant suspension
                                    </label>
                                    <select
                                        value={gracePeriodDays}
                                        onChange={(e) => setGracePeriodDays(Number(e.target.value))}
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    >
                                        <option value={3}>3 jours ouvrés</option>
                                        <option value={5}>5 jours ouvrés</option>
                                        <option value={7}>7 jours ouvrés</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Step 4: Gérant & Pièces */}
                    {step === 4 && (
                        <div className="space-y-4">
                            <div className="space-y-1.5">
                                <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                                    <User className="mr-1.5 h-4 w-4 text-slate-500" />
                                    Nom et prénom du gérant / chef de bureau
                                    <span className="text-red-500 font-bold ml-1">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={managerName}
                                    onChange={(e) => setManagerName(e.target.value)}
                                    placeholder="Ex: Bio Kora, Jean Dossou..."
                                    className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    required
                                />
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-1.5">
                                    <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <Phone className="mr-1.5 h-4 w-4 text-slate-500" />
                                        Numéro de téléphone
                                        <span className="text-red-500 font-bold ml-1">*</span>
                                    </label>
                                    <input
                                        type="tel"
                                        value={phone}
                                        onChange={(e) => setPhone(e.target.value)}
                                        placeholder="+229 97 00 00 00"
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-mono font-semibold text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        required
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <Mail className="mr-1.5 h-4 w-4 text-slate-500" />
                                        Email professionnel
                                    </label>
                                    <input
                                        type="email"
                                        value={email}
                                        onChange={(e) => setEmail(e.target.value)}
                                        placeholder="agent@kora.bj"
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    />
                                </div>
                            </div>

                            {/* Documents Upload */}
                            <div className="space-y-1.5">
                                <label className="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300">
                                    <span className="flex items-center">
                                        <UploadCloud className="mr-1.5 h-4 w-4 text-slate-500" />
                                        Dossier légal (RCCM, IFU, Pièce d'identité)
                                    </span>
                                </label>
                                {proofFile ? (
                                    <div className="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50/70 p-3.5 text-xs dark:border-emerald-800/60 dark:bg-emerald-950/20">
                                        <div className="flex items-center gap-2.5">
                                            <FileText className="h-5 w-5 text-emerald-600" />
                                            <div>
                                                <p className="font-bold text-slate-900 dark:text-white">
                                                    {proofFile.name}
                                                </p>
                                                <span className="text-slate-500">{proofFile.size} · Validé</span>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setProofFile(null)}
                                            className="text-xs font-bold text-slate-600 hover:text-rose-600"
                                        >
                                            Remplacer
                                        </button>
                                    </div>
                                ) : (
                                    <div className="rounded-xl border-2 border-dashed border-slate-300 p-4 text-center dark:border-slate-700">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setProofFile({
                                                    name: 'dossier_juridique_point.pdf',
                                                    size: '2.1 Mo',
                                                })
                                            }
                                            className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                        >
                                            Sélectionner le dossier PDF
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Navigation Buttons */}
                    <div className="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
                        {step > 1 ? (
                            <button
                                type="button"
                                onClick={() => setStep((s) => (s - 1) as any)}
                                className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300"
                            >
                                <ArrowLeft className="h-4 w-4" />
                                Précédent
                            </button>
                        ) : (
                            <button
                                type="button"
                                onClick={onClose}
                                className="rounded-xl border border-slate-300 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300"
                            >
                                Annuler
                            </button>
                        )}

                        {step < 4 ? (
                            <button
                                type="button"
                                onClick={handleNext}
                                className="inline-flex items-center gap-1.5 rounded-xl bg-gold px-6 py-2.5 text-xs font-black uppercase tracking-wider text-kora hover:bg-gold-light"
                            >
                                Suivant
                                <ArrowRight className="h-4 w-4" />
                            </button>
                        ) : (
                            <button
                                type="submit"
                                className="inline-flex items-center gap-2 rounded-xl bg-gold px-6 py-2.5 text-xs font-black uppercase tracking-wider text-kora hover:bg-gold-light shadow-sm"
                            >
                                <CheckCircle2 className="h-4 w-4" />
                                Finaliser & Déployer le Point
                            </button>
                        )}
                    </div>
                </form>
            </div>
        </div>
    );
}
