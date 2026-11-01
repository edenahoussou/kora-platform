import {
    Wallet,
    X,
    Check,
    AlertCircle,
    Banknote,
    MessageSquare,
    Calculator,
} from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import type { PointCashSession } from '../mock/kora-point-mock-data';

type Props = {
    isOpen: boolean;
    mode: 'OPEN' | 'CLOSE';
    session: PointCashSession;
    onClose: () => void;
    onConfirmOpen: (amount: number) => void;
    onConfirmClose: (actualAmount: number, note?: string) => void;
};

export function KoraPointCashSessionDialog({
    isOpen,
    mode,
    session,
    onClose,
    onConfirmOpen,
    onConfirmClose,
}: Props) {
    const [openingAmount, setOpeningAmount] = useState('50000');
    const [countedCash, setCountedCash] = useState(
        String(session.expected_closing_cash || '168500'),
    );
    const [varianceComment, setVarianceComment] = useState('');
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    if (!isOpen) {
return null;
}

    const expectedCash = session.expected_closing_cash;
    const actualCashNum = Number(countedCash || 0);
    const variance = actualCashNum - expectedCash;

    const handleOpenSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const num = Number(openingAmount);

        if (isNaN(num) || num < 0) {
            setErrorMessage('Le montant du fond de caisse doit être un nombre valide.');

            return;
        }

        onConfirmOpen(num);
        onClose();
    };

    const handleCloseSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (variance !== 0 && !varianceComment.trim()) {
            setErrorMessage(
                'Un écart de caisse est constaté. Un motif explicatif est obligatoire pour clôturer.',
            );

            return;
        }

        onConfirmClose(actualCashNum, varianceComment);
        onClose();
    };

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label={mode === 'OPEN' ? 'Ouverture de caisse' : 'Clôture de caisse'}
            className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-md animate-in fade-in"
        >
            <div className="relative w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900 sm:p-7">
                {/* Close Button */}
                <button
                    type="button"
                    onClick={onClose}
                    className="absolute top-5 right-5 rounded-full p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
                    aria-label="Fermer"
                >
                    <X className="size-5" />
                </button>

                {/* Header */}
                <div className="flex items-center gap-3 border-b border-slate-100 pb-4 dark:border-slate-800">
                    <div className="grid size-11 place-items-center rounded-2xl bg-gold/20 text-kora dark:text-gold">
                        <Wallet className="size-6" />
                    </div>
                    <div>
                        <span className="text-[0.625rem] font-black uppercase tracking-wider text-slate-400">
                            Caisse physique du Point
                        </span>
                        <h2 className="text-lg font-black text-slate-900 dark:text-white">
                            {mode === 'OPEN'
                                ? 'Ouverture de session de caisse'
                                : 'Clôture journalière de caisse'}
                        </h2>
                    </div>
                </div>

                {errorMessage && (
                    <div className="mt-4 flex items-center gap-2 rounded-xl bg-rose-50 p-3 text-xs font-semibold text-rose-700 dark:bg-rose-950/50 dark:text-rose-300">
                        <AlertCircle className="size-4 shrink-0" />
                        <span>{errorMessage}</span>
                    </div>
                )}

                {/* MODE OPEN */}
                {mode === 'OPEN' ? (
                    <form onSubmit={handleOpenSubmit} className="mt-5 space-y-4">
                        <div>
                            <label className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                <Banknote className="size-4 text-slate-400" />
                                <span>Fond de caisse initial (Espèces)</span>
                                <span className="text-rose-500" title="Champ obligatoire">
                                    *
                                </span>
                            </label>
                            <input
                                type="number"
                                required
                                value={openingAmount}
                                onChange={(e) => {
                                    setOpeningAmount(e.target.value);
                                    setErrorMessage(null);
                                }}
                                className="mt-1.5 h-11 w-full rounded-xl border border-slate-200 bg-white px-3.5 font-mono text-sm font-bold text-slate-900 shadow-sm focus:border-gold focus:outline-none focus:ring-1 focus:ring-gold dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            />
                            <p className="mt-1 text-[0.6875rem] text-slate-500">
                                Montant physique compté dans le tiroir avant la première vente du jour.
                            </p>
                        </div>

                        <div className="rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                            Opérateur déclarant : <strong>{session.operator_name}</strong>
                        </div>

                        <div className="flex items-center justify-end gap-2.5 pt-3">
                            <button
                                type="button"
                                onClick={onClose}
                                className="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                            >
                                Annuler
                            </button>
                            <button
                                type="submit"
                                className="inline-flex items-center gap-2 rounded-xl bg-gold px-5 py-2 text-xs font-black uppercase tracking-wider text-kora shadow-md hover:bg-gold/90"
                            >
                                <Check className="size-4" />
                                <span>Ouvrir la caisse</span>
                            </button>
                        </div>
                    </form>
                ) : (
                    /* MODE CLOSE */
                    <form onSubmit={handleCloseSubmit} className="mt-5 space-y-4">
                        {/* Summary of Expected amounts */}
                        <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-xs dark:border-slate-800 dark:bg-slate-950 space-y-2">
                            <div className="flex justify-between">
                                <span className="text-slate-500">Fond initial :</span>
                                <span className="font-mono font-bold">
                                    {session.opening_amount.toLocaleString('fr-FR')} FCFA
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Recettes espèces du jour :</span>
                                <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                    +{session.cash_collected.toLocaleString('fr-FR')} FCFA
                                </span>
                            </div>
                            <div className="flex justify-between border-t border-slate-200 pt-1.5 font-bold text-slate-900 dark:border-slate-800 dark:text-white">
                                <span>Espèces théoriques attendues :</span>
                                <span className="font-mono text-sm">
                                    {expectedCash.toLocaleString('fr-FR')} FCFA
                                </span>
                            </div>
                        </div>

                        {/* Counted Cash Input */}
                        <div>
                            <label className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                <Calculator className="size-4 text-slate-400" />
                                <span>Espèces physiques effectivement comptées</span>
                                <span className="text-rose-500" title="Champ obligatoire">
                                    *
                                </span>
                            </label>
                            <input
                                type="number"
                                required
                                value={countedCash}
                                onChange={(e) => {
                                    setCountedCash(e.target.value);
                                    setErrorMessage(null);
                                }}
                                className="mt-1.5 h-11 w-full rounded-xl border border-slate-200 bg-white px-3.5 font-mono text-sm font-bold text-slate-900 shadow-sm focus:border-gold focus:outline-none focus:ring-1 focus:ring-gold dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            />
                        </div>

                        {/* Calculated Variance */}
                        <div
                            className={cn(
                                'rounded-xl p-3 text-xs font-bold flex items-center justify-between',
                                variance === 0
                                    ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
                                    : variance > 0
                                    ? 'bg-blue-50 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300'
                                    : 'bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300',
                            )}
                        >
                            <span>
                                {variance === 0
                                    ? 'Caisse conforme (aucun écart)'
                                    : variance > 0
                                    ? 'Excédent de caisse constaté'
                                    : 'Déficit de caisse constaté'}
                            </span>
                            <span className="font-mono text-sm">
                                {variance > 0 ? `+${variance}` : variance} FCFA
                            </span>
                        </div>

                        {/* Variance comment if mismatch */}
                        {variance !== 0 && (
                            <div>
                                <label className="flex items-center gap-1.5 text-xs font-bold text-rose-700 dark:text-rose-400">
                                    <MessageSquare className="size-3.5" />
                                    <span>Motif obligatoire de l'écart constaté</span>
                                    <span className="text-rose-500">*</span>
                                </label>
                                <textarea
                                    required
                                    rows={2}
                                    placeholder="Expliquez la cause de la différence constatée..."
                                    value={varianceComment}
                                    onChange={(e) => setVarianceComment(e.target.value)}
                                    className="mt-1.5 w-full rounded-xl border border-rose-300 bg-white p-2.5 text-xs text-slate-900 shadow-sm focus:border-rose-500 focus:outline-none dark:border-rose-800 dark:bg-slate-800 dark:text-white"
                                />
                            </div>
                        )}

                        <div className="flex items-center justify-end gap-2.5 pt-3">
                            <button
                                type="button"
                                onClick={onClose}
                                className="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                            >
                                Annuler
                            </button>
                            <button
                                type="submit"
                                className="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2 text-xs font-black uppercase tracking-wider text-white shadow-md hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100"
                            >
                                <Check className="size-4" />
                                <span>Valider la clôture</span>
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </div>
    );
}
