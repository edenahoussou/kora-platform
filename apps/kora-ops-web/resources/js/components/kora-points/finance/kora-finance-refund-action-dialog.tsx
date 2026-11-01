import {
    RotateCcw,
    X,
    FileText,
    AlertCircle,
    CheckCircle2,
    CreditCard,
    Store,
    Building2,
} from 'lucide-react';
import { useState } from 'react';
import type { PointRefundRecord } from '../mock/kora-point-mock-data';

type Props = {
    isOpen: boolean;
    refund: PointRefundRecord | null;
    onClose: () => void;
    onApprove: (
        refundUuid: string,
        params: {
            payoutMode: 'POINT_CASH' | 'KORA_DIRECT';
            notes?: string;
        },
    ) => void;
    onReject: (refundUuid: string, params: { reason: string }) => void;
};

export function KoraFinanceRefundActionDialog({
    isOpen,
    refund,
    onClose,
    onApprove,
    onReject,
}: Props) {
    const [mode, setMode] = useState<'APPROVE' | 'REJECT'>('APPROVE');
    const [payoutMode, setPayoutMode] = useState<'POINT_CASH' | 'KORA_DIRECT'>(
        'POINT_CASH',
    );
    const [notes, setNotes] = useState('');
    const [rejectionReason, setRejectionReason] = useState('');

    if (!isOpen || !refund) {
        return null;
    }

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (mode === 'REJECT') {
            if (!rejectionReason.trim()) {
                return;
            }

            onReject(refund.uuid, { reason: rejectionReason.trim() });
            onClose();
        } else {
            onApprove(refund.uuid, {
                payoutMode,
                notes: notes.trim() || undefined,
            });
            onClose();
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex animate-in items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm fade-in">
            <div className="relative w-full max-w-xl overflow-hidden rounded-2xl bg-white text-slate-900 shadow-2xl dark:bg-slate-900 dark:text-slate-100">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-slate-200 bg-[var(--kora)] px-6 py-4 text-white dark:border-slate-800">
                    <div className="flex items-center gap-3">
                        <div className="grid size-10 place-items-center rounded-xl bg-gold font-black text-kora">
                            <RotateCcw className="size-5" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h3 className="text-base font-black tracking-wide text-white">
                                    Instruction du Remboursement
                                </h3>
                                <span className="font-mono text-xs font-bold text-gold">
                                    {refund.reference}
                                </span>
                            </div>
                            <p className="text-xs text-white/70">
                                Billet concerné : {refund.ticket_number}
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

                {/* Body Form */}
                <form
                    onSubmit={handleSubmit}
                    className="max-h-[80vh] space-y-5 overflow-y-auto p-6"
                >
                    {/* Passenger & Ticket Card */}
                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div>
                                <span className="block text-[0.625rem] font-bold text-slate-500 uppercase">
                                    Passager
                                </span>
                                <span className="text-xs font-black text-slate-900 dark:text-white">
                                    {refund.passenger_name}
                                </span>
                                <span className="block text-[0.625rem] text-slate-500">
                                    {refund.passenger_phone}
                                </span>
                            </div>
                            <div>
                                <span className="block text-[0.625rem] font-bold text-slate-500 uppercase">
                                    Trajet & Départ
                                </span>
                                <span className="text-xs font-bold text-slate-800 dark:text-slate-200">
                                    {refund.route_label}
                                </span>
                                <span className="block font-mono text-[0.625rem] text-slate-500">
                                    {refund.departure_ref}
                                </span>
                            </div>
                            <div>
                                <span className="block text-[0.625rem] font-bold text-slate-500 uppercase">
                                    Motif invoqué
                                </span>
                                <span className="text-xs font-bold text-amber-700 dark:text-amber-400">
                                    {refund.reason_label}
                                </span>
                            </div>
                        </div>

                        {/* Financial Impact Breakdown */}
                        <div className="mt-3 grid grid-cols-3 gap-2 border-t border-slate-200/80 pt-3 dark:border-slate-700">
                            <div>
                                <span className="block text-[0.625rem] font-bold text-slate-500 uppercase">
                                    Prix initial payé
                                </span>
                                <span className="font-mono text-xs font-bold text-slate-800 dark:text-slate-200">
                                    {refund.original_amount.toLocaleString(
                                        'fr-FR',
                                    )}{' '}
                                    FCFA
                                </span>
                            </div>
                            <div>
                                <span className="block text-[0.625rem] font-bold text-slate-500 uppercase">
                                    Montant à restituer
                                </span>
                                <span className="font-mono text-xs font-black text-emerald-600 dark:text-emerald-400">
                                    {refund.refund_amount.toLocaleString(
                                        'fr-FR',
                                    )}{' '}
                                    FCFA
                                </span>
                            </div>
                            <div>
                                <span className="block text-[0.625rem] font-bold text-slate-500 uppercase">
                                    Reprise com. Point
                                </span>
                                <span className="font-mono text-xs font-black text-rose-600 dark:text-rose-400">
                                    {refund.commission_clawback} FCFA
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Mode Toggle */}
                    <div className="flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                        <button
                            type="button"
                            onClick={() => setMode('APPROVE')}
                            className={`flex-1 rounded-lg py-2 text-xs font-black transition ${
                                mode === 'APPROVE'
                                    ? 'bg-emerald-600 text-white shadow-sm'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                            }`}
                        >
                            Approuver le remboursement
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
                            Refuser la demande
                        </button>
                    </div>

                    {mode === 'APPROVE' ? (
                        <>
                            {/* Payout Channel Selection */}
                            <div>
                                <label className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                    <CreditCard className="size-4 shrink-0 text-gold" />
                                    <span>
                                        Canal d'arbitrage de la restitution
                                    </span>
                                    <span className="font-black text-rose-500">
                                        *
                                    </span>
                                </label>

                                <div className="mt-2 grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                                    <label
                                        className={`relative flex cursor-pointer flex-col rounded-xl border p-3 transition ${
                                            payoutMode === 'POINT_CASH'
                                                ? 'border-emerald-500 bg-emerald-50/50 dark:border-emerald-500 dark:bg-emerald-950/20'
                                                : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900'
                                        }`}
                                    >
                                        <div className="flex items-center gap-2">
                                            <input
                                                type="radio"
                                                name="payout_mode"
                                                checked={
                                                    payoutMode === 'POINT_CASH'
                                                }
                                                onChange={() =>
                                                    setPayoutMode('POINT_CASH')
                                                }
                                                className="text-emerald-600 focus:ring-emerald-500"
                                            />
                                            <Store className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                            <span className="text-xs font-black text-slate-900 dark:text-white">
                                                Restitution au guichet du Point
                                            </span>
                                        </div>
                                        <p className="mt-1 pl-6 text-[0.6875rem] text-slate-500 dark:text-slate-400">
                                            L'agent du Point remettra les
                                            espèces au voyageur en agence. Le
                                            fond de caisse sera déduit et la
                                            commission reprise.
                                        </p>
                                    </label>

                                    <label
                                        className={`relative flex cursor-pointer flex-col rounded-xl border p-3 transition ${
                                            payoutMode === 'KORA_DIRECT'
                                                ? 'border-blue-500 bg-blue-50/50 dark:border-blue-500 dark:bg-blue-950/20'
                                                : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900'
                                        }`}
                                    >
                                        <div className="flex items-center gap-2">
                                            <input
                                                type="radio"
                                                name="payout_mode"
                                                checked={
                                                    payoutMode === 'KORA_DIRECT'
                                                }
                                                onChange={() =>
                                                    setPayoutMode('KORA_DIRECT')
                                                }
                                                className="text-blue-600 focus:ring-blue-500"
                                            />
                                            <Building2 className="size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                                            <span className="text-xs font-black text-slate-900 dark:text-white">
                                                Remboursement direct KORA
                                            </span>
                                        </div>
                                        <p className="mt-1 pl-6 text-[0.6875rem] text-slate-500 dark:text-slate-400">
                                            La Trésorerie KORA procède au
                                            remboursement par virement ou Mobile
                                            Money direct. Sans impact sur la
                                            caisse du Point.
                                        </p>
                                    </label>
                                </div>
                            </div>

                            {/* Consignes / Notes */}
                            <div>
                                <label className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                    <FileText className="size-4 shrink-0 text-gold" />
                                    <span>
                                        Consignes ou motif d'accord préalable
                                    </span>
                                </label>
                                <textarea
                                    rows={2}
                                    value={notes}
                                    onChange={(e) => setNotes(e.target.value)}
                                    placeholder="Ex: Accord accordé sous réserve de présentation de la CNI originale et signature de la quittance..."
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
                                        Refus du dossier de remboursement
                                    </h4>
                                </div>
                                <p className="mt-1 text-xs">
                                    La demande sera classée sans suite. Le motif
                                    légal ou contractuel sera notifié au
                                    guichetier pour explication au passager.
                                </p>
                            </div>

                            <div>
                                <label className="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300">
                                    <AlertCircle className="size-4 shrink-0 text-rose-500" />
                                    <span>
                                        Motif obligatoire du refus passager
                                    </span>
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
                                    placeholder="Ex: Voyageur non présenté au départ sans annulation préalable dans les délais impartis (Article 7 des CGV KORA Bus)..."
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

                        {mode === 'APPROVE' ? (
                            <button
                                type="submit"
                                className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-xs font-black tracking-wider text-white uppercase shadow-md transition hover:bg-emerald-700"
                            >
                                <CheckCircle2 className="size-4" />
                                Valider l'accord
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
