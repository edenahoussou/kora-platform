import {
    X,
    Coins,
    CheckCircle2,
    Printer,
    Download,
    FileCheck,
    UserCheck,
    AlertCircle,
    Info,
    Receipt,
    Shield,
} from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import type { PointRefundRecord } from '../mock/kora-point-mock-data';

type Props = {
    isOpen: boolean;
    refund: PointRefundRecord | null;
    pointName: string;
    operatorName: string;
    onClose: () => void;
    onConfirmRestitution: (refundUuid: string, signatoryName: string, receiptNote?: string) => void;
};

export function KoraPointRefundRestitutionDialog({
    isOpen,
    refund,
    pointName,
    operatorName,
    onClose,
    onConfirmRestitution,
}: Props) {
    if (!isOpen || !refund) {
return null;
}

    const [signatoryName, setSignatoryName] = useState(refund.passenger_name);
    const [idCardNumber, setIdCardNumber] = useState('CNI-BJ-2021-99821');
    const [isConfirmedCashGiven, setIsConfirmedCashGiven] = useState(false);
    const [isSuccessView, setIsSuccessView] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleConfirm = (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);

        if (!signatoryName.trim()) {
            setError('Le nom du signataire / bénéficiaire est requis.');

            return;
        }

        if (!isConfirmedCashGiven) {
            setError('Veuillez cocher la confirmation de remise effective des espèces.');

            return;
        }

        onConfirmRestitution(
            refund.uuid,
            signatoryName.trim(),
            `Émargement CNI ${idCardNumber}`,
        );

        setIsSuccessView(true);
    };

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm overflow-y-auto animate-in fade-in duration-200"
            role="dialog"
            aria-modal="true"
        >
            <div className="relative my-8 w-full max-w-lg rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900 overflow-hidden">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4 dark:border-slate-800 dark:bg-slate-800/40">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600/15 text-emerald-700 dark:bg-emerald-600/25 dark:text-emerald-300">
                            <Coins className="h-5 w-5" />
                        </div>
                        <div>
                            <h2 className="text-base font-black text-slate-900 dark:text-white">
                                {isSuccessView
                                    ? 'Quittance de restitution émise'
                                    : 'Restitution d’espèces au voyageur'}
                            </h2>
                            <p className="text-xs text-slate-500 font-mono">
                                Dossier {refund.reference} · Billet {refund.ticket_number}
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

                {!isSuccessView ? (
                    <form onSubmit={handleConfirm} className="p-6 space-y-5">
                        {error && (
                            <div className="rounded-xl border border-rose-300 bg-rose-50 p-4 text-xs font-semibold text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200 flex items-center gap-2">
                                <AlertCircle className="h-4 w-4 shrink-0 text-rose-600" />
                                <span>{error}</span>
                            </div>
                        )}

                        {/* Amount Box */}
                        <div className="rounded-2xl border-2 border-emerald-500/30 bg-emerald-50/60 p-5 text-center dark:bg-emerald-950/20">
                            <span className="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                                Montant net en espèces à remettre au passager
                            </span>
                            <div className="mt-2 text-3xl font-black text-emerald-700 dark:text-emerald-300">
                                {refund.refund_amount.toLocaleString()} FCFA
                            </div>
                            <span className="mt-1 text-xs text-slate-500 block">
                                Accord préalable accordé par {refund.approved_by || 'Finance KORA'}
                            </span>
                        </div>

                        {/* Passenger ID check fields */}
                        <div className="space-y-3">
                            <div className="space-y-1">
                                <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                                    <UserCheck className="mr-1.5 h-4 w-4 text-slate-500" />
                                    Nom du voyageur / bénéficiaire présent au guichet
                                    <span className="text-red-500 font-bold ml-1" aria-hidden="true">
                                        *
                                    </span>
                                </label>
                                <input
                                    type="text"
                                    value={signatoryName}
                                    onChange={(e) => setSignatoryName(e.target.value)}
                                    className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    required
                                />
                            </div>

                            <div className="space-y-1">
                                <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                                    <Shield className="mr-1.5 h-4 w-4 text-slate-500" />
                                    N° de pièce d’identité vérifiée (CNI / Passeport / CIP)
                                    <span className="text-red-500 font-bold ml-1" aria-hidden="true">
                                        *
                                    </span>
                                </label>
                                <input
                                    type="text"
                                    value={idCardNumber}
                                    onChange={(e) => setIdCardNumber(e.target.value)}
                                    className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    required
                                />
                            </div>
                        </div>

                        {/* Checkbox of physical cash release */}
                        <label className="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 cursor-pointer hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-800/40">
                            <input
                                type="checkbox"
                                checked={isConfirmedCashGiven}
                                onChange={(e) => setIsConfirmedCashGiven(e.target.checked)}
                                className="mt-0.5 h-4 w-4 rounded border-slate-300 text-gold focus:ring-gold"
                                required
                            />
                            <div className="text-xs">
                                <span className="font-bold text-slate-900 dark:text-white block">
                                    Attestation de remise physique des fonds
                                </span>
                                <span className="text-slate-500">
                                    J'atteste avoir versé la somme de{' '}
                                    <strong className="text-slate-900 dark:text-white">
                                        {refund.refund_amount.toLocaleString()} FCFA
                                    </strong>{' '}
                                    en espèces au voyageur contre signature de la quittance.
                                </span>
                            </div>
                        </label>

                        {/* Actions */}
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
                                Valider la restitution
                            </button>
                        </div>
                    </form>
                ) : (
                    /* Success / Receipt Print Screen */
                    <div className="p-6 space-y-6">
                        <div className="rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-xs text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200 flex items-center gap-2">
                            <CheckCircle2 className="h-5 w-5 text-emerald-600 shrink-0" />
                            <div>
                                <p className="font-bold">Restitution confirmée avec succès !</p>
                                <p className="text-emerald-800 dark:text-emerald-300">
                                    La caisse physique a été débitée et le dossier est classé soldé.
                                </p>
                            </div>
                        </div>

                        {/* Thermal Receipt Simulation */}
                        <div className="rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 p-6 font-mono text-xs dark:border-slate-700 dark:bg-slate-800/40 space-y-3">
                            <div className="text-center border-b pb-2">
                                <p className="font-bold text-sm tracking-wider uppercase text-navy dark:text-gold">
                                    KORA BUS · POINT DE VOYAGE
                                </p>
                                <p className="text-[11px] text-slate-500 uppercase">{pointName}</p>
                                <p className="text-[10px] text-amber-600 font-bold mt-1">
                                    [RÉCÉPISSÉ OFFICIEL DE RESTITUTION ESPÈCES]
                                </p>
                            </div>

                            <div className="space-y-1 text-slate-700 dark:text-slate-300">
                                <div className="flex justify-between">
                                    <span>N° Dossier :</span>
                                    <span className="font-bold">{refund.reference}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span>Billet annulé :</span>
                                    <span>{refund.ticket_number}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span>Bénéficiaire :</span>
                                    <span className="font-bold">{signatoryName}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span>Pièce identité :</span>
                                    <span>{idCardNumber}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span>Motif :</span>
                                    <span>{refund.reason_label}</span>
                                </div>
                                <div className="flex justify-between border-t pt-1 font-bold">
                                    <span>Montant restitué :</span>
                                    <span className="text-sm">
                                        {refund.refund_amount.toLocaleString()} FCFA
                                    </span>
                                </div>
                                <div className="flex justify-between text-[11px] text-slate-500">
                                    <span>Guichetier :</span>
                                    <span>{operatorName}</span>
                                </div>
                            </div>

                            <div className="border-t pt-3 text-center space-y-1">
                                <p className="text-[10px] text-slate-400">Émargement passager :</p>
                                <div className="font-serif italic text-slate-800 dark:text-slate-200 text-sm">
                                    « {signatoryName} — Pour acquit »
                                </div>
                                <p className="text-[9px] text-slate-400 uppercase">
                                    Exemple de démonstration KORA VOYAGE · Non fiscal
                                </p>
                            </div>
                        </div>

                        {/* Actions */}
                        <div className="flex flex-wrap items-center justify-between gap-2 pt-2">
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={() =>
                                        alert('Impression ticket thermique lancée (simulation démo).')
                                    }
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                >
                                    <Printer className="h-4 w-4 text-slate-500" />
                                    Imprimer
                                </button>
                                <button
                                    type="button"
                                    onClick={() =>
                                        alert('Téléchargement PDF quittance lancé (simulation démo).')
                                    }
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                >
                                    <Download className="h-4 w-4 text-slate-500" />
                                    Télécharger PDF
                                </button>
                            </div>
                            <button
                                type="button"
                                onClick={onClose}
                                className="rounded-xl bg-gold px-5 py-2 text-xs font-black uppercase text-kora hover:bg-gold-light"
                            >
                                Terminer
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
