import { useState } from 'react';
import {
    X,
    RotateCcw,
    Ticket,
    User,
    Phone,
    FileText,
    AlertCircle,
    Info,
    CheckCircle2,
    Coins,
    Camera,
    Image as ImageIcon,
    Trash2,
    HelpCircle,
} from 'lucide-react';
import {
    PointRefundReason,
    PointSaleRecord,
    KoraPointProfile,
} from '../mock/kora-point-mock-data';
import { cn } from '@/lib/utils';

type Props = {
    isOpen: boolean;
    point: KoraPointProfile;
    eligibleSales: PointSaleRecord[];
    onClose: () => void;
    onSubmit: (data: {
        ticketNumber: string;
        passengerName: string;
        passengerPhone: string;
        originalAmount: number;
        reason: PointRefundReason;
        reasonLabel: string;
        retainedFee?: number;
        proofDocumentName?: string;
    }) => void;
};

export function KoraPointRefundRequestDialog({
    isOpen,
    point,
    eligibleSales,
    onClose,
    onSubmit,
}: Props) {
    if (!isOpen) return null;

    const [selectedTicketNumber, setSelectedTicketNumber] = useState<string>(
        eligibleSales[0]?.ticket_number || '',
    );
    const [reason, setReason] = useState<PointRefundReason>('PASSENGER_SICKNESS');
    const [proofFile, setProofFile] = useState<{ name: string; size: string } | null>({
        name: 'certificat_medical_voyageur.pdf',
        size: '180 Ko',
    });
    const [error, setError] = useState<string | null>(null);

    const activeSale = eligibleSales.find((s) => s.ticket_number === selectedTicketNumber);
    const originalAmount = activeSale?.amount_paid || 9000;
    const passengerName = activeSale?.passenger_name || 'Voyageur';
    const passengerPhone = activeSale?.passenger_phone || '+229 97 00 00 00';

    // Retained fee calculation (10% if passenger date change, 0% if trip cancelled or medical)
    const retainedFee =
        reason === 'SCHEDULE_CHANGE' ? Math.round(originalAmount * 0.1) : 0;
    const netToRefundPassenger = originalAmount - retainedFee;

    // Commission clawback (-3%)
    const commissionRateBps = point.terms.commission_rate_bps;
    const commissionClawback = -Math.round((netToRefundPassenger * commissionRateBps) / 10000);

    const getReasonLabel = (r: PointRefundReason) => {
        switch (r) {
            case 'TRIP_CANCELLED':
                return 'Départ annulé par la compagnie (Frais 0%)';
            case 'PASSENGER_SICKNESS':
                return 'Incapacité médicale subite prouvée (Frais 0%)';
            case 'SCHEDULE_CHANGE':
                return 'Demande d’annulation par le voyageur (Retenue 10%)';
            case 'DUPLICATE_PURCHASE':
                return 'Double achat accidentel au guichet (Frais 0%)';
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);

        if (!selectedTicketNumber) {
            setError('Veuillez sélectionner un billet valide.');
            return;
        }

        onSubmit({
            ticketNumber: selectedTicketNumber,
            passengerName,
            passengerPhone,
            originalAmount,
            reason,
            reasonLabel: getReasonLabel(reason),
            retainedFee,
            proofDocumentName: proofFile?.name,
        });

        onClose();
    };

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm overflow-y-auto animate-in fade-in duration-200"
            role="dialog"
            aria-modal="true"
            aria-labelledby="refund-request-title"
        >
            <div className="relative my-8 w-full max-w-xl rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900 overflow-hidden">
                {/* Modal Header */}
                <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4 dark:border-slate-800 dark:bg-slate-800/40">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/15 text-amber-700 dark:bg-amber-500/25 dark:text-amber-300">
                            <RotateCcw className="h-5 w-5" />
                        </div>
                        <div>
                            <h2
                                id="refund-request-title"
                                className="text-base font-black text-slate-900 dark:text-white"
                            >
                                Demande de remboursement de billet
                            </h2>
                            <p className="text-xs text-slate-500">
                                Saisie au guichet pour instruction par le pôle Finance KORA
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

                <form onSubmit={handleSubmit} className="p-6 space-y-5">
                    {error && (
                        <div className="rounded-xl border border-rose-300 bg-rose-50 p-4 text-xs font-semibold text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200 flex items-center gap-2">
                            <AlertCircle className="h-4 w-4 shrink-0 text-rose-600" />
                            <span>{error}</span>
                        </div>
                    )}

                    {/* Step 1: Select Ticket */}
                    <div className="space-y-1.5">
                        <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                            <Ticket className="mr-1.5 h-4 w-4 text-slate-500" />
                            Billet concerné
                            <span className="text-red-500 font-bold ml-1" aria-hidden="true">
                                *
                            </span>
                        </label>
                        <select
                            value={selectedTicketNumber}
                            onChange={(e) => setSelectedTicketNumber(e.target.value)}
                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-bold text-slate-900 focus:border-gold focus:outline-none focus:ring-2 focus:ring-gold/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            required
                        >
                            {eligibleSales.map((sale) => (
                                <option key={sale.uuid} value={sale.ticket_number}>
                                    {sale.ticket_number} — {sale.passenger_name} ({sale.route_label} ·{' '}
                                    {sale.amount_paid.toLocaleString()} FCFA)
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Passenger & Trip Overview */}
                    {activeSale && (
                        <div className="rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs dark:border-slate-800 dark:bg-slate-800/40 space-y-1.5">
                            <div className="flex justify-between">
                                <span className="text-slate-500">Passager :</span>
                                <span className="font-bold text-slate-900 dark:text-white">
                                    {activeSale.passenger_name} ({activeSale.passenger_phone})
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Trajet & Départ :</span>
                                <span className="font-semibold text-slate-800 dark:text-slate-200">
                                    {activeSale.route_label} · {activeSale.departure_ref} (Siège{' '}
                                    {activeSale.seat_number})
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Montant payé initial :</span>
                                <span className="font-black text-slate-900 dark:text-white">
                                    {activeSale.amount_paid.toLocaleString()} FCFA
                                </span>
                            </div>
                        </div>
                    )}

                    {/* Step 2: Reason */}
                    <div className="space-y-1.5">
                        <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                            <HelpCircle className="mr-1.5 h-4 w-4 text-slate-500" />
                            Motif de la demande
                            <span className="text-red-500 font-bold ml-1" aria-hidden="true">
                                *
                            </span>
                        </label>
                        <select
                            value={reason}
                            onChange={(e) => setReason(e.target.value as PointRefundReason)}
                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-gold focus:outline-none focus:ring-2 focus:ring-gold/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            required
                        >
                            <option value="PASSENGER_SICKNESS">
                                Incapacité médicale du voyageur (certificat requis · Remboursement 100%)
                            </option>
                            <option value="TRIP_CANCELLED">
                                Départ annulé ou reporté par le transporteur (Remboursement 100%)
                            </option>
                            <option value="SCHEDULE_CHANGE">
                                Annulation demandée par le client avant départ (Retenue contractuelle 10%)
                            </option>
                            <option value="DUPLICATE_PURCHASE">
                                Doublon de vente au guichet (Remboursement 100%)
                            </option>
                        </select>
                    </div>

                    {/* Step 3: Transparent Financial Impact Card */}
                    <div className="rounded-xl border border-amber-200 bg-amber-50/70 p-4 dark:border-amber-800/60 dark:bg-amber-950/20 space-y-3">
                        <h4 className="text-xs font-bold text-amber-900 dark:text-amber-200 uppercase tracking-wider flex items-center gap-1.5">
                            <Coins className="h-4 w-4 text-amber-700 dark:text-amber-400" />
                            Décomposition financière et impact Point
                        </h4>
                        <div className="space-y-2 text-xs">
                            <div className="flex justify-between">
                                <span className="text-slate-600 dark:text-slate-300">Prix du billet :</span>
                                <span className="font-bold text-slate-900 dark:text-white">
                                    {originalAmount.toLocaleString()} FCFA
                                </span>
                            </div>
                            {retainedFee > 0 && (
                                <div className="flex justify-between text-amber-800 dark:text-amber-300">
                                    <span>Frais de retenue (10 %) :</span>
                                    <span className="font-bold">
                                        - {retainedFee.toLocaleString()} FCFA
                                    </span>
                                </div>
                            )}
                            <div className="flex justify-between border-t border-amber-200/80 pt-1.5 dark:border-amber-800/60 font-bold">
                                <span className="text-slate-800 dark:text-slate-200">
                                    Net à restituer au voyageur :
                                </span>
                                <span className="text-sm font-black text-emerald-600 dark:text-emerald-400">
                                    {netToRefundPassenger.toLocaleString()} FCFA
                                </span>
                            </div>
                            <div className="flex justify-between border-t border-amber-200/80 pt-1.5 dark:border-amber-800/60 text-[11px] text-rose-700 dark:text-rose-400">
                                <span>Reprise de commission du Point (-3 %) :</span>
                                <span className="font-black">
                                    {commissionClawback.toLocaleString()} FCFA
                                </span>
                            </div>
                            <p className="text-[10px] text-slate-500 italic pt-1">
                                Note : Après validation par Finance KORA, le voyageur percevra ses espèces au guichet et la dette KORA du Point sera diminuée de la valeur du billet net de commission.
                            </p>
                        </div>
                    </div>

                    {/* Step 4: Justificatif */}
                    <div className="space-y-1.5">
                        <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                            <FileText className="mr-1.5 h-4 w-4 text-slate-500" />
                            Pièce justificative (Certificat médical, photo CNI ou réclamation)
                        </label>
                        {proofFile ? (
                            <div className="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs dark:border-slate-800 dark:bg-slate-800">
                                <span className="font-bold text-slate-800 dark:text-slate-200 truncate">
                                    {proofFile.name} ({proofFile.size})
                                </span>
                                <button
                                    type="button"
                                    onClick={() => setProofFile(null)}
                                    className="text-slate-400 hover:text-rose-600 p-1"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            </div>
                        ) : (
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={() =>
                                        setProofFile({
                                            name: 'justificatif_passager.pdf',
                                            size: '140 Ko',
                                        })
                                    }
                                    className="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    <ImageIcon className="h-3.5 w-3.5 text-slate-500" />
                                    Choisir un fichier
                                </button>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setProofFile({
                                            name: 'photo_justificatif_camera.jpg',
                                            size: '890 Ko',
                                        })
                                    }
                                    className="inline-flex items-center gap-1.5 rounded-xl bg-navy px-4 py-2 text-xs font-bold text-white hover:bg-navy/90 dark:bg-slate-700"
                                >
                                    <Camera className="h-3.5 w-3.5 text-gold" />
                                    Prendre une photo
                                </button>
                            </div>
                        )}
                    </div>

                    {/* Footer */}
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
                            Transmettre la demande
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
