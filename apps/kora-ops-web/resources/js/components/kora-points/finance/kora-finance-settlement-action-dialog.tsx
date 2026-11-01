import {
    Calendar,
    Banknote,
    FileCheck,
    MessageSquare,
    AlertCircle,
    CheckCircle2,
    X,
    FileText,
    Receipt,
    AlertTriangle,
    Eye,
} from 'lucide-react';
import { useState } from 'react';
import type { PointSettlementRecord } from '../mock/kora-point-mock-data';

type Props = {
    isOpen: boolean;
    settlement: PointSettlementRecord | null;
    onClose: () => void;
    onReconcile: (
        settlementUuid: string,
        params: {
            bankAmount: number;
            valueDate: string;
            bankReference?: string;
            notes?: string;
        },
    ) => void;
    onReject: (settlementUuid: string, params: { reason: string }) => void;
};

export function KoraFinanceSettlementActionDialog({
    isOpen,
    settlement,
    onClose,
    onReconcile,
    onReject,
}: Props) {
    const [valueDate, setValueDate] = useState(() =>
        new Date().toISOString().slice(0, 10),
    );
    const [bankAmount, setBankAmount] = useState<number>(
        settlement?.amount_declared ?? 0,
    );
    const [bankReference, setBankReference] = useState(
        settlement?.payment_reference
            ? `REF-${settlement.payment_reference}`
            : '',
    );
    const [notes, setNotes] = useState('');
    const [mode, setMode] = useState<'VALIDATE' | 'REJECT'>('VALIDATE');
    const [rejectionReason, setRejectionReason] = useState('');
    const [showProofPreview, setShowProofPreview] = useState(false);

    if (!isOpen || !settlement) {
        return null;
    }

    const delta = bankAmount - settlement.amount_declared;

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (mode === 'REJECT') {
            if (!rejectionReason.trim()) {
                return;
            }

            onReject(settlement.uuid, { reason: rejectionReason.trim() });
            onClose();
        } else {
            onReconcile(settlement.uuid, {
                bankAmount,
                valueDate,
                bankReference: bankReference.trim() || undefined,
                notes: notes.trim() || undefined,
            });
            onClose();
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex animate-in items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm fade-in">
            <div className="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-white text-slate-900 shadow-2xl dark:bg-slate-900 dark:text-slate-100">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-slate-200 bg-[var(--kora)] px-6 py-4 text-white dark:border-slate-800">
                    <div className="flex items-center gap-3">
                        <div className="grid size-10 place-items-center rounded-xl bg-gold font-black text-kora">
                            <Receipt className="size-5" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h3 className="text-base font-black tracking-wide text-white">
                                    Instruction Finance & Rapprochement
                                </h3>
                                <span className="font-mono text-xs font-bold text-gold">
                                    {settlement.reference}
                                </span>
                            </div>
                            <p className="text-xs text-white/70">
                                Émetteur : {settlement.point_name} (
                                {settlement.created_by_name})
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-1.5 text-white/70 transition hover:bg-white/10 hover:text-white"
                        aria-label="Fermer"
                    >
                        <X className="size-5" />
                    </button>
                </div>

                {/* Form */}
                <form
                    onSubmit={handleFormSubmit}
                    className="max-h-[80vh] space-y-5 overflow-y-auto p-6"
                >
                    {/* Settlement Overview Banner */}
                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div>
                                <span className="block text-[0.625rem] font-bold text-slate-500 uppercase">
                                    Montant déclaré
                                </span>
                                <span className="font-mono text-sm font-black text-kora dark:text-gold">
                                    {settlement.amount_declared.toLocaleString(
                                        'fr-FR',
                                    )}{' '}
                                    FCFA
                                </span>
                            </div>
                            <div>
                                <span className="block text-[0.625rem] font-bold text-slate-500 uppercase">
                                    Canal de versement
                                </span>
                                <span className="text-xs font-bold text-slate-800 dark:text-slate-200">
                                    {settlement.payment_method ===
                                    'BANK_TRANSFER'
                                        ? 'Virement bancaire'
                                        : settlement.payment_method ===
                                            'BANK_DEPOSIT'
                                          ? 'Versement espèces'
                                          : 'Mobile Money B2B'}
                                </span>
                            </div>
                            <div>
                                <span className="block text-[0.625rem] font-bold text-slate-500 uppercase">
                                    Référence partenaire
                                </span>
                                <span className="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">
                                    {settlement.payment_reference || 'N/A'}
                                </span>
                            </div>
                            <div>
                                <span className="block text-[0.625rem] font-bold text-slate-500 uppercase">
                                    Billets couverts
                                </span>
                                <span className="text-xs font-extrabold text-slate-700 dark:text-slate-300">
                                    {settlement.sales_count} billets
                                </span>
                            </div>
                        </div>

                        {/* Proof Document Pill */}
                        {settlement.proof_file_name && (
                            <div className="mt-3 flex items-center justify-between border-t border-slate-200/80 pt-2.5 dark:border-slate-700">
                                <div className="flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-300">
                                    <FileText className="size-4 shrink-0 text-gold" />
                                    <span className="truncate">
                                        {settlement.proof_file_name}
                                    </span>
                                    <span className="text-[0.625rem] text-slate-400">
                                        (
                                        {settlement.proof_file_size ||
                                            'Pièce justificative'}
                                        )
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setShowProofPreview(!showProofPreview)
                                    }
                                    className="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    <Eye className="size-3.5" />
                                    {showProofPreview
                                        ? 'Masquer aperçu'
                                        : 'Voir le bordereau'}
                                </button>
                            </div>
                        )}

                        {showProofPreview && (
                            <div className="mt-3 rounded-lg border border-slate-300 bg-white p-3 text-center dark:border-slate-700 dark:bg-slate-900">
                                <div className="py-6 text-xs text-slate-500">
                                    <Receipt className="mx-auto size-12 text-slate-400" />
                                    <p className="mt-2 font-bold text-slate-700 dark:text-slate-300">
                                        [Aperçu démo] Bordereau de versement
                                        bancaire émargé
                                    </p>
                                    <p className="text-[0.6875rem] text-slate-400">
                                        Tampon Banque Écobarre / BOA certifié
                                        conforme — Réf{' '}
                                        {settlement.payment_reference}
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Mode Toggle Tabs */}
                    <div className="flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                        <button
                            type="button"
                            onClick={() => setMode('VALIDATE')}
                            className={`flex-1 rounded-lg py-2 text-xs font-black transition ${
                                mode === 'VALIDATE'
                                    ? 'bg-emerald-600 text-white shadow-sm'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                            }`}
                        >
                            Valider le rapprochement bancaire
                        </button>
                        <button
                            type="button"
                            onClick={() => setMode('REJECT')}
                            className={`flex-1 rounded-lg py-2 text-xs font-black transition ${
                                mode === 'REJECT'
                                    ? 'bg-rose-600 text-white shadow-sm'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                            }`}
                        >
                            Rejeter le bordereau
                        </button>
                    </div>

                    {mode === 'VALIDATE' ? (
                        <>
                            {/* Date de valeur */}
                            <div>
                                <label className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                    <Calendar className="size-4 shrink-0 text-gold" />
                                    <span>Date de valeur bancaire</span>
                                    <span className="font-black text-rose-500">
                                        *
                                    </span>
                                </label>
                                <input
                                    type="date"
                                    required
                                    value={valueDate}
                                    onChange={(e) =>
                                        setValueDate(e.target.value)
                                    }
                                    className="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-xs font-medium text-slate-900 shadow-sm focus:border-gold focus:ring-1 focus:ring-gold focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                />
                                <p className="mt-1 text-[0.625rem] text-slate-500">
                                    Date exacte à laquelle les fonds ont été
                                    crédités sur le compte bancaire KORA.
                                </p>
                            </div>

                            {/* Montant réel constaté en banque */}
                            <div>
                                <label className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                    <Banknote className="size-4 shrink-0 text-gold" />
                                    <span>
                                        Montant réel constaté en banque (FCFA)
                                    </span>
                                    <span className="font-black text-rose-500">
                                        *
                                    </span>
                                </label>
                                <input
                                    type="number"
                                    required
                                    min={0}
                                    step={100}
                                    value={bankAmount}
                                    onChange={(e) =>
                                        setBankAmount(
                                            Number(e.target.value) || 0,
                                        )
                                    }
                                    className="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-900 shadow-sm focus:border-gold focus:ring-1 focus:ring-gold focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                />
                            </div>

                            {/* Delta / Écart Alert */}
                            {delta === 0 ? (
                                <div className="flex items-center gap-2 rounded-xl bg-emerald-500/15 p-3 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300">
                                    <CheckCircle2 className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                    <span className="text-xs font-bold">
                                        Montant 100% conforme au franc près
                                        (aucun écart comptable).
                                    </span>
                                </div>
                            ) : delta < 0 ? (
                                <div className="flex items-start gap-2.5 rounded-xl bg-amber-500/15 p-3 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">
                                    <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                    <div className="text-xs">
                                        <strong className="block font-black">
                                            Écart négatif de{' '}
                                            {Math.abs(delta).toLocaleString(
                                                'fr-FR',
                                            )}{' '}
                                            FCFA constaté
                                        </strong>
                                        <span>
                                            Le montant validé (
                                            {bankAmount.toLocaleString('fr-FR')}{' '}
                                            FCFA) est inférieur au montant
                                            déclaré. Le solde restant sera
                                            maintenu sur l'encours du point.
                                        </span>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex items-start gap-2.5 rounded-xl bg-blue-500/15 p-3 text-blue-800 dark:bg-blue-500/20 dark:text-blue-300">
                                    <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                                    <div className="text-xs">
                                        <strong className="block font-black">
                                            Sur-versement de{' '}
                                            {delta.toLocaleString('fr-FR')} FCFA
                                        </strong>
                                        <span>
                                            Montant constaté supérieur au
                                            montant déclaré. L'excédent sera
                                            crédité sur les prochaines ventes du
                                            partenaire.
                                        </span>
                                    </div>
                                </div>
                            )}

                            {/* Référence relevé bancaire */}
                            <div>
                                <label className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                    <FileCheck className="size-4 shrink-0 text-gold" />
                                    <span>
                                        Référence écriture relevé bancaire
                                    </span>
                                </label>
                                <input
                                    type="text"
                                    value={bankReference}
                                    onChange={(e) =>
                                        setBankReference(e.target.value)
                                    }
                                    placeholder="Ex: EXT-BOA-2026-08-01-8841"
                                    className="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-xs font-medium text-slate-900 shadow-sm focus:border-gold focus:ring-1 focus:ring-gold focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                />
                            </div>

                            {/* Observations / Note */}
                            <div>
                                <label className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                    <MessageSquare className="size-4 shrink-0 text-gold" />
                                    <span>
                                        Observation comptable / Note interne
                                    </span>
                                </label>
                                <textarea
                                    rows={2}
                                    value={notes}
                                    onChange={(e) => setNotes(e.target.value)}
                                    placeholder="Mention pour le journal de trésorerie..."
                                    className="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-xs font-medium text-slate-900 shadow-sm focus:border-gold focus:ring-1 focus:ring-gold focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                />
                            </div>
                        </>
                    ) : (
                        /* REJECT MODE */
                        <div className="space-y-4">
                            <div className="rounded-xl border border-rose-500/20 bg-rose-500/10 p-4 text-rose-800 dark:text-rose-300">
                                <div className="flex items-center gap-2">
                                    <AlertCircle className="size-5 shrink-0 text-rose-600 dark:text-rose-400" />
                                    <h4 className="text-xs font-black">
                                        Rejet du bordereau de reversement
                                    </h4>
                                </div>
                                <p className="mt-1 text-xs">
                                    Le bordereau sera renvoyé au partenaire avec
                                    l'explication saisie ci-dessous. Le Point ou
                                    son trésorier sera notifié pour corriger la
                                    pièce ou réémettre la déclaration.
                                </p>
                            </div>

                            <div>
                                <label className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                    <AlertCircle className="size-4 shrink-0 text-rose-500" />
                                    <span>Motif obligatoire du rejet</span>
                                    <span className="font-black text-rose-500">
                                        *
                                    </span>
                                </label>
                                <textarea
                                    rows={3}
                                    required
                                    value={rejectionReason}
                                    onChange={(e) =>
                                        setRejectionReason(e.target.value)
                                    }
                                    placeholder="Ex: Bordereau bancaire illisible, montant non concordant avec le relevé du jour, tampon bancaire manquant..."
                                    className="mt-1.5 w-full rounded-xl border border-rose-300 bg-white px-3.5 py-2.5 text-xs font-medium text-slate-900 shadow-sm focus:border-rose-500 focus:ring-1 focus:ring-rose-500 focus:outline-none dark:border-rose-900 dark:bg-slate-950 dark:text-slate-100"
                                />
                            </div>
                        </div>
                    )}

                    {/* Actions */}
                    <div className="flex items-center justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        >
                            Annuler
                        </button>

                        {mode === 'VALIDATE' ? (
                            <button
                                type="submit"
                                className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-xs font-black tracking-wider text-white uppercase shadow-md transition hover:bg-emerald-700"
                            >
                                <CheckCircle2 className="size-4" />
                                Valider le rapprochement
                            </button>
                        ) : (
                            <button
                                type="submit"
                                disabled={!rejectionReason.trim()}
                                className="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-5 py-2 text-xs font-black tracking-wider text-white uppercase shadow-md transition hover:bg-rose-700 disabled:opacity-50"
                            >
                                <AlertCircle className="size-4" />
                                Confirmer le rejet
                            </button>
                        )}
                    </div>
                </form>
            </div>
        </div>
    );
}
