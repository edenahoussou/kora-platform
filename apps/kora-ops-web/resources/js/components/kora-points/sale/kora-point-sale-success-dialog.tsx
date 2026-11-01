import { CheckCircle2, Printer, Download, PlusCircle, X } from 'lucide-react';
import { useState } from 'react';
import type { PointSaleRecord } from '../mock/kora-point-mock-data';

type Props = {
    saleRecord: PointSaleRecord | null;
    onClose: () => void;
    onNewSale: () => void;
};

export function KoraPointSaleSuccessDialog({
    saleRecord,
    onClose,
    onNewSale,
}: Props) {
    const [actionMessage, setActionMessage] = useState<string | null>(null);

    if (!saleRecord) {
        return null;
    }

    const handlePrint = () => {
        setActionMessage(
            'Impression thermique simulée : reçu envoyé à l’imprimante du guichet.',
        );
        setTimeout(() => setActionMessage(null), 3500);
    };

    const handleDownload = () => {
        setActionMessage(
            'Téléchargement PDF simulé : fichier KORA-BILLET-DEMO.pdf généré.',
        );
        setTimeout(() => setActionMessage(null), 3500);
    };

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label="Confirmation de vente de billet"
            className="fixed inset-0 z-50 flex animate-in items-center justify-center bg-black/60 p-4 backdrop-blur-md fade-in"
        >
            <div className="relative w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-7 dark:border-slate-800 dark:bg-slate-900">
                {/* Close Button */}
                <button
                    type="button"
                    onClick={onClose}
                    className="absolute top-5 right-5 rounded-full p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
                    aria-label="Fermer"
                >
                    <X className="size-5" />
                </button>

                {/* Header status */}
                <div className="flex items-center gap-3">
                    <div className="grid size-12 place-items-center rounded-2xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                        <CheckCircle2 className="size-7" />
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="rounded-full bg-amber-500/15 px-2 py-0.5 text-[0.625rem] font-black text-amber-800 uppercase dark:text-amber-300">
                                Exemple de démonstration
                            </span>
                            <span className="text-[0.6875rem] font-bold text-slate-400">
                                {saleRecord.date_label}
                            </span>
                        </div>
                        <h2 className="text-lg font-black text-slate-900 sm:text-xl dark:text-white">
                            Billet émis avec succès
                        </h2>
                    </div>
                </div>

                {/* Simulated Thermal Ticket Preview */}
                <div className="my-5 rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 p-4 font-mono text-xs dark:border-slate-700 dark:bg-slate-950">
                    <div className="border-b border-slate-200 pb-2 text-center dark:border-slate-800">
                        <strong className="block text-sm font-black text-slate-900 dark:text-white">
                            KORA EXPRESS — BILLET PASSAGER
                        </strong>
                        <span className="text-[0.6875rem] text-slate-500">
                            Point de vente : {saleRecord.seller_name}
                        </span>
                    </div>

                    <div className="my-3 space-y-1.5 text-[0.75rem]">
                        <div className="flex justify-between">
                            <span className="text-slate-500">Billet N° :</span>
                            <strong className="text-slate-900 dark:text-white">
                                {saleRecord.ticket_number}
                            </strong>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-slate-500">
                                Code court vérif :
                            </span>
                            <strong className="rounded bg-gold/20 px-1.5 py-0.5 font-black text-kora dark:text-gold">
                                {saleRecord.short_code}
                            </strong>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-slate-500">Trajet :</span>
                            <strong className="text-slate-900 dark:text-white">
                                {saleRecord.route_label}
                            </strong>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-slate-500">
                                Départ / Siège :
                            </span>
                            <span className="font-bold text-slate-900 dark:text-white">
                                {saleRecord.departure_ref} (
                                {saleRecord.departure_time}) · Siège{' '}
                                {saleRecord.seat_number}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-slate-500">Passager :</span>
                            <strong className="text-slate-900 dark:text-white">
                                {saleRecord.passenger_name}
                            </strong>
                        </div>
                        <div className="flex justify-between border-t border-slate-200 pt-1.5 dark:border-slate-800">
                            <span className="font-bold text-slate-700 dark:text-slate-300">
                                Montant payé ({saleRecord.payment_method}) :
                            </span>
                            <strong className="text-sm font-black text-slate-900 dark:text-white">
                                {saleRecord.amount_paid.toLocaleString('fr-FR')}{' '}
                                FCFA
                            </strong>
                        </div>
                        <div className="flex justify-between font-bold text-emerald-600 dark:text-emerald-400">
                            <span>Commission retenue Point (3 %) :</span>
                            <span>
                                +
                                {saleRecord.commission_amount.toLocaleString(
                                    'fr-FR',
                                )}{' '}
                                FCFA
                            </span>
                        </div>
                    </div>

                    <div className="border-t border-slate-200 pt-2 text-center text-[0.625rem] text-slate-400 dark:border-slate-800">
                        Billet valable uniquement sur présentation d’une pièce
                        d’identité valide.
                    </div>
                </div>

                {/* Action Feedback Message */}
                {actionMessage && (
                    <div className="mb-4 animate-in rounded-xl bg-blue-50 p-2.5 text-center text-xs font-semibold text-blue-700 fade-in dark:bg-blue-950/50 dark:text-blue-300">
                        {actionMessage}
                    </div>
                )}

                {/* Modal Action Buttons */}
                <div className="flex flex-wrap items-center justify-between gap-2.5 pt-1">
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={handlePrint}
                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            <Printer className="size-3.5 text-slate-500" />
                            <span>Imprimer</span>
                        </button>

                        <button
                            type="button"
                            onClick={handleDownload}
                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            <Download className="size-3.5 text-slate-500" />
                            <span>Reçu PDF</span>
                        </button>
                    </div>

                    <button
                        type="button"
                        onClick={onNewSale}
                        className="inline-flex items-center gap-1.5 rounded-xl bg-gold px-4 py-2 text-xs font-black tracking-wider text-kora uppercase shadow-md transition hover:bg-gold/90"
                    >
                        <PlusCircle className="size-3.5" />
                        <span>Nouvelle vente</span>
                    </button>
                </div>
            </div>
        </div>
    );
}
