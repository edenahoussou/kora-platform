import {
    X,
    Coins,
    Landmark,
    FileText,
    UploadCloud,
    Camera,
    Image as ImageIcon,
    CheckCircle2,
    AlertCircle,
    Hash,
    Trash2,
} from 'lucide-react';
import { useState, useRef } from 'react';
import type { FinancialHealth, PointSettlementMethod } from '../mock/kora-point-mock-data';

type Props = {
    isOpen: boolean;
    health: FinancialHealth;
    pointName: string;
    onClose: () => void;
    onSubmit: (data: {
        amountDeclared: number;
        paymentMethod: PointSettlementMethod;
        paymentReference: string;
        proofFileName?: string;
        notes?: string;
    }) => void;
};

export function KoraPointSettlementDialog({
    isOpen,
    health,
    pointName,
    onClose,
    onSubmit,
}: Props) {
    const [amountDeclared, setAmountDeclared] = useState<number>(health.current_exposure || 100000);
    const [paymentMethod, setPaymentMethod] = useState<PointSettlementMethod>('BANK_DEPOSIT');
    const [paymentReference, setPaymentReference] = useState('');
    const [notes, setNotes] = useState('');
    const [proofFile, setProofFile] = useState<{ name: string; size: string; previewUrl?: string } | null>({
        name: 'bordereau_versement_ecobank_saint_michel.pdf',
        size: '345 Ko',
    });
    const [, setUploadProgress] = useState<number>(100);
    const [error, setError] = useState<string | null>(null);

    const fileInputRef = useRef<HTMLInputElement>(null);
    const cameraInputRef = useRef<HTMLInputElement>(null);

    if (!isOpen) {
        return null;
    }

    const remainingExposure = Math.max(0, health.current_exposure - amountDeclared);

    const handleFileSelected = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            const file = e.target.files[0];
            const sizeInKb = Math.round(file.size / 1024);
            setUploadProgress(40);
            setTimeout(() => {
                setUploadProgress(100);
                setProofFile({
                    name: file.name,
                    size: `${sizeInKb} Ko`,
                    previewUrl: file.type.startsWith('image/')
                        ? URL.createObjectURL(file)
                        : undefined,
                });
            }, 300);
        }
    };

    const handleQuickAmount = (ratio: number) => {
        const calculated = Math.round((health.current_exposure * ratio) / 1000) * 1000;
        setAmountDeclared(calculated);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);

        if (!amountDeclared || amountDeclared <= 0) {
            setError('Veuillez indiquer un montant supérieur à 0 FCFA.');

            return;
        }

        if (!paymentReference.trim()) {
            setError('Veuillez renseigner le numéro d’opération ou la référence bancaire.');

            return;
        }

        if (!proofFile) {
            setError('Le téléversement du justificatif de paiement ou bordereau est obligatoire.');

            return;
        }

        onSubmit({
            amountDeclared,
            paymentMethod,
            paymentReference: paymentReference.trim(),
            proofFileName: proofFile.name,
            notes: notes.trim() || undefined,
        });

        onClose();
    };

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm overflow-y-auto animate-in fade-in duration-200"
            role="dialog"
            aria-modal="true"
            aria-labelledby="settlement-dialog-title"
        >
            <div className="relative my-8 w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900 overflow-hidden">
                {/* Modal Header */}
                <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4 dark:border-slate-800 dark:bg-slate-800/40">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gold/15 text-gold-dark dark:bg-gold/25 dark:text-gold">
                            <Coins className="h-5 w-5" />
                        </div>
                        <div>
                            <h2
                                id="settlement-dialog-title"
                                className="text-base font-black text-slate-900 dark:text-white"
                            >
                                Déclarer un versement de reversement
                            </h2>
                            <p className="text-xs text-slate-500">
                                {pointName} · Apurement de la dette envers KORA Exploitation
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        aria-label="Fermer la boîte de dialogue"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-6">
                    {/* Error Banner */}
                    {error && (
                        <div className="rounded-xl border border-rose-300 bg-rose-50 p-4 text-xs font-semibold text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200 flex items-center gap-2">
                            <AlertCircle className="h-4 w-4 shrink-0 text-rose-600" />
                            <span>{error}</span>
                        </div>
                    )}

                    {/* Financial Context Banner */}
                    <div className="rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-800/20">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">
                                    Encours total échu
                                </span>
                                <span className="mt-1 text-lg font-black text-slate-900 dark:text-white">
                                    {health.current_exposure.toLocaleString()} FCFA
                                </span>
                            </div>
                            <div>
                                <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">
                                    Versement déclaré
                                </span>
                                <span className="mt-1 text-lg font-black text-emerald-600 dark:text-emerald-400">
                                    {amountDeclared.toLocaleString()} FCFA
                                </span>
                            </div>
                            <div>
                                <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">
                                    Reste dû prévisionnel
                                </span>
                                <span className="mt-1 text-lg font-black text-amber-600 dark:text-amber-400">
                                    {remainingExposure.toLocaleString()} FCFA
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Section 1: Montant */}
                    <div className="space-y-2">
                        <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                            <Coins className="mr-1.5 h-4 w-4 text-gold-dark dark:text-gold" />
                            Montant versé
                            <span className="text-red-500 font-bold ml-1" aria-hidden="true">
                                *
                            </span>
                        </label>
                        <div className="relative">
                            <input
                                type="number"
                                min={1000}
                                max={10000000}
                                step={1000}
                                value={amountDeclared || ''}
                                onChange={(e) => setAmountDeclared(Number(e.target.value))}
                                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-base font-black text-slate-900 focus:border-gold focus:outline-none focus:ring-2 focus:ring-gold/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                placeholder="Montant en FCFA"
                                required
                            />
                            <div className="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-xs font-black text-slate-400">
                                FCFA
                            </div>
                        </div>

                        {/* Quick Selection Buttons */}
                        <div className="flex flex-wrap gap-2 pt-1">
                            <span className="text-[11px] text-slate-400 self-center">Raccourcis :</span>
                            <button
                                type="button"
                                onClick={() => handleQuickAmount(1)}
                                className="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300"
                            >
                                Totalité (100 %)
                            </button>
                            <button
                                type="button"
                                onClick={() => handleQuickAmount(0.5)}
                                className="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300"
                            >
                                50 %
                            </button>
                            <button
                                type="button"
                                onClick={() => handleQuickAmount(0.25)}
                                className="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300"
                            >
                                25 %
                            </button>
                        </div>
                    </div>

                    {/* Section 2: Mode & Référence */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {/* Mode de paiement */}
                        <div className="space-y-2">
                            <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                                <Landmark className="mr-1.5 h-4 w-4 text-slate-500" />
                                Mode de versement
                                <span className="text-red-500 font-bold ml-1" aria-hidden="true">
                                    *
                                </span>
                            </label>
                            <select
                                value={paymentMethod}
                                onChange={(e) => setPaymentMethod(e.target.value as PointSettlementMethod)}
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-gold focus:outline-none focus:ring-2 focus:ring-gold/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                required
                            >
                                <option value="BANK_DEPOSIT">Dépôt espèces en agence bancaire</option>
                                <option value="BANK_TRANSFER">Virement bancaire (BOA / Ecobank / BGFIBank)</option>
                                <option value="MOBILE_MONEY">Mobile Money B2B (MTN MoMo / Moov Money)</option>
                            </select>
                        </div>

                        {/* Référence opération */}
                        <div className="space-y-2">
                            <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                                <Hash className="mr-1.5 h-4 w-4 text-slate-500" />
                                N° d’opération / Bordereau
                                <span className="text-red-500 font-bold ml-1" aria-hidden="true">
                                    *
                                </span>
                            </label>
                            <input
                                type="text"
                                value={paymentReference}
                                onChange={(e) => setPaymentReference(e.target.value)}
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 placeholder:text-slate-400 focus:border-gold focus:outline-none focus:ring-2 focus:ring-gold/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                placeholder="Ex: DEP-ECO-2026-9921"
                                required
                            />
                        </div>
                    </div>

                    {/* Section 3: Preuve de versement (Desktop & Mobile) */}
                    <div className="space-y-2">
                        <label className="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300">
                            <span className="flex items-center">
                                <UploadCloud className="mr-1.5 h-4 w-4 text-slate-500" />
                                Preuve de versement (Reçu ou bordereau bancaire)
                                <span className="text-red-500 font-bold ml-1" aria-hidden="true">
                                    *
                                </span>
                            </span>
                            <span className="text-[11px] text-slate-400">PDF, JPG, PNG (Max 5 Mo)</span>
                        </label>

                        {/* Invisible file inputs */}
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".pdf,image/*"
                            className="hidden"
                            onChange={handleFileSelected}
                        />
                        <input
                            ref={cameraInputRef}
                            type="file"
                            accept="image/*"
                            capture="environment"
                            className="hidden"
                            onChange={handleFileSelected}
                        />

                        {proofFile ? (
                            /* File preview block */
                            <div className="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-800/60 dark:bg-emerald-950/20">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-600 text-white">
                                        <FileText className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p className="text-xs font-bold text-slate-900 dark:text-white truncate max-w-xs sm:max-w-md">
                                            {proofFile.name}
                                        </p>
                                        <div className="flex items-center gap-2 mt-0.5">
                                            <span className="text-[11px] text-slate-500">{proofFile.size}</span>
                                            <span className="inline-flex items-center text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                                <CheckCircle2 className="h-3 w-3 mr-0.5" /> Fichier prêt
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => fileInputRef.current?.click()}
                                        className="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                    >
                                        Remplacer
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setProofFile(null)}
                                        className="rounded-lg p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40"
                                        title="Supprimer la pièce"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        ) : (
                            /* Dropzone / Mobile choice */
                            <div className="rounded-xl border-2 border-dashed border-slate-300 p-6 text-center hover:border-gold dark:border-slate-700 dark:hover:border-gold">
                                <UploadCloud className="mx-auto h-8 w-8 text-slate-400" />
                                <p className="mt-2 text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Glissez-déposez votre reçu bancaire ici
                                </p>
                                <p className="text-[11px] text-slate-400">
                                    ou utilisez les boutons ci-dessous
                                </p>
                                <div className="mt-4 flex flex-wrap justify-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => fileInputRef.current?.click()}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                    >
                                        <ImageIcon className="h-3.5 w-3.5 text-slate-500" />
                                        Choisir un fichier
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => cameraInputRef.current?.click()}
                                        className="inline-flex items-center gap-1.5 rounded-lg bg-navy px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-navy/90 dark:bg-slate-700"
                                    >
                                        <Camera className="h-3.5 w-3.5 text-gold" />
                                        Prendre une photo
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Section 4: Observations */}
                    <div className="space-y-1">
                        <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                            <FileText className="mr-1.5 h-4 w-4 text-slate-500" />
                            Observations pour la trésorerie KORA
                            <span className="text-[10px] text-slate-400 ml-1.5 font-normal">
                                (Facultatif)
                            </span>
                        </label>
                        <textarea
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            rows={2}
                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-normal text-slate-900 placeholder:text-slate-400 focus:border-gold focus:outline-none focus:ring-2 focus:ring-gold/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            placeholder="Précisions éventuelles sur l'agence de dépôt ou le titulaire du compte émetteur..."
                        />
                    </div>

                    {/* Footer Actions */}
                    <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-xl border border-slate-300 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        >
                            Annuler
                        </button>
                        <button
                            type="submit"
                            className="inline-flex items-center gap-2 rounded-xl bg-gold px-6 py-2.5 text-xs font-black uppercase tracking-wider text-kora shadow-sm hover:bg-gold-light focus:outline-none focus:ring-2 focus:ring-gold"
                        >
                            <CheckCircle2 className="h-4 w-4" />
                            Soumettre la déclaration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
