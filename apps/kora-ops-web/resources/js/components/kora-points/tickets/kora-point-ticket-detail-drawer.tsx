import {
    X,
    Ticket,
    MapPin,
    User,
    Printer,
    Download,
    RotateCcw,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import type { PointSaleRecord } from '../mock/kora-point-mock-data';

type Props = {
    sale: PointSaleRecord | null;
    onClose: () => void;
    onRequestRefund?: (saleUuid: string) => void;
};

export function KoraPointTicketDetailDrawer({
    sale,
    onClose,
    onRequestRefund,
}: Props) {
    const [actionMsg, setActionMsg] = useState<string | null>(null);

    if (!sale) {
return null;
}

    const handlePrint = () => {
        setActionMsg('Billet envoyé à l’imprimante thermique (simulation).');
        setTimeout(() => setActionMsg(null), 3000);
    };

    const handleDownload = () => {
        setActionMsg('Reçu PDF téléchargé avec succès (simulation).');
        setTimeout(() => setActionMsg(null), 3000);
    };

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label="Détail du billet"
            className="fixed inset-0 z-50 flex justify-end bg-black/50 backdrop-blur-sm animate-in fade-in"
        >
            <div className="flex h-full w-full max-w-md flex-col bg-white shadow-2xl dark:bg-slate-900 animate-in slide-in-from-right duration-300">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-slate-100 p-5 dark:border-slate-800">
                    <div className="flex items-center gap-2.5">
                        <div className="grid size-9 place-items-center rounded-xl bg-gold/20 text-kora dark:text-gold">
                            <Ticket className="size-5" />
                        </div>
                        <div>
                            <span className="text-[0.625rem] font-black uppercase tracking-wider text-slate-400">
                                Billet KORA POINT
                            </span>
                            <h3 className="font-mono text-sm font-black text-slate-900 dark:text-white">
                                {sale.ticket_number}
                            </h3>
                        </div>
                    </div>

                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
                        aria-label="Fermer le panneau"
                    >
                        <X className="size-5" />
                    </button>
                </div>

                {/* Content */}
                <div className="flex-1 space-y-5 overflow-y-auto p-5 text-xs">
                    {/* Status & Code */}
                    <div className="flex items-center justify-between rounded-xl bg-slate-50 p-3.5 dark:bg-slate-800/60">
                        <div>
                            <span className="block text-[0.625rem] font-bold text-slate-400 uppercase">
                                Statut de reversement
                            </span>
                            <div className="mt-1">
                                {sale.settlement_status === 'open' && (
                                    <KoraStatusBadge
                                        label="À reverser (Encours ouvert)"
                                        tone="amber"
                                        variant="solid"
                                    />
                                )}
                                {sale.settlement_status === 'in_settlement' && (
                                    <KoraStatusBadge
                                        label="Engagé dans bordereau"
                                        tone="blue"
                                        variant="solid"
                                    />
                                )}
                                {sale.settlement_status === 'settled' && (
                                    <KoraStatusBadge
                                        label="Réglé et soldé"
                                        tone="emerald"
                                        variant="solid"
                                    />
                                )}
                            </div>
                        </div>

                        <div className="text-right">
                            <span className="block text-[0.625rem] font-bold text-slate-400 uppercase">
                                Code court vérif
                            </span>
                            <strong className="mt-1 inline-block rounded bg-gold/25 px-2 py-0.5 font-mono text-sm font-black text-kora dark:text-gold">
                                {sale.short_code}
                            </strong>
                        </div>
                    </div>

                    {/* Passenger & Journey */}
                    <div className="space-y-3 rounded-xl border border-slate-200/80 p-4 dark:border-slate-800">
                        <div className="font-bold text-slate-900 dark:text-white border-b border-slate-100 pb-2 dark:border-slate-800 flex items-center gap-1.5">
                            <User className="size-4 text-slate-400" />
                            <span>Voyageur</span>
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <div>
                                <span className="text-[0.6875rem] text-slate-400">Nom & Prénom :</span>
                                <div className="font-bold text-slate-800 dark:text-slate-200">
                                    {sale.passenger_name}
                                </div>
                            </div>
                            <div>
                                <span className="text-[0.6875rem] text-slate-400">Téléphone :</span>
                                <div className="font-mono font-bold text-slate-800 dark:text-slate-200">
                                    {sale.passenger_phone}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Trip details */}
                    <div className="space-y-3 rounded-xl border border-slate-200/80 p-4 dark:border-slate-800">
                        <div className="font-bold text-slate-900 dark:text-white border-b border-slate-100 pb-2 dark:border-slate-800 flex items-center gap-1.5">
                            <MapPin className="size-4 text-slate-400" />
                            <span>Trajet & Départ</span>
                        </div>
                        <div className="space-y-2">
                            <div className="flex justify-between">
                                <span className="text-slate-400">Itinéraire :</span>
                                <strong className="text-slate-900 dark:text-white">
                                    {sale.route_label}
                                </strong>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-400">Départ / Service :</span>
                                <span className="font-bold text-slate-800 dark:text-slate-200">
                                    {sale.departure_ref} · {sale.service_class}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-400">Heure / Siège :</span>
                                <span className="font-bold text-slate-800 dark:text-slate-200">
                                    {sale.departure_time} · Siège {sale.seat_number}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-400">Date d'émission :</span>
                                <span className="text-slate-600 dark:text-slate-400">
                                    {sale.date_label}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Financial Breakdown */}
                    <div className="space-y-3 rounded-xl border border-slate-200/80 p-4 dark:border-slate-800">
                        <div className="font-bold text-slate-900 dark:text-white border-b border-slate-100 pb-2 dark:border-slate-800 flex items-center gap-1.5">
                            <TrendingUp className="size-4 text-slate-400" />
                            <span>Ventilation financière</span>
                        </div>
                        <div className="space-y-2">
                            <div className="flex justify-between">
                                <span className="text-slate-400">Montant payé par client :</span>
                                <strong className="font-mono text-sm text-slate-900 dark:text-white">
                                    {sale.amount_paid.toLocaleString('fr-FR')} FCFA
                                </strong>
                            </div>
                            <div className="flex justify-between text-emerald-600 dark:text-emerald-400 font-bold">
                                <span>Commission Point acquise (3 %) :</span>
                                <span className="font-mono">
                                    +{sale.commission_amount.toLocaleString('fr-FR')} FCFA
                                </span>
                            </div>
                            <div className="flex justify-between text-slate-700 dark:text-slate-300">
                                <span>Dette nette à reverser à KORA :</span>
                                <span className="font-mono font-bold">
                                    {(sale.amount_paid - sale.commission_amount).toLocaleString('fr-FR')} FCFA
                                </span>
                            </div>
                            {sale.settlement_ref && (
                                <div className="mt-1 rounded bg-slate-100 px-2 py-1 text-[0.6875rem] text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    Rattaché au bordereau : <strong>{sale.settlement_ref}</strong>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Action notification feedback */}
                    {actionMsg && (
                        <div className="rounded-xl bg-blue-50 p-2.5 text-center text-xs font-semibold text-blue-700 dark:bg-blue-950/50 dark:text-blue-300 animate-in fade-in">
                            {actionMsg}
                        </div>
                    )}
                </div>

                {/* Footer Actions */}
                <div className="border-t border-slate-100 p-4 dark:border-slate-800 space-y-2">
                    <div className="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            onClick={handlePrint}
                            className="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white py-2 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            <Printer className="size-3.5" />
                            <span>Imprimer</span>
                        </button>
                        <button
                            type="button"
                            onClick={handleDownload}
                            className="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white py-2 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            <Download className="size-3.5" />
                            <span>Reçu PDF</span>
                        </button>
                    </div>

                    {sale.refundable && onRequestRefund && (
                        <button
                            type="button"
                            onClick={() => onRequestRefund(sale.uuid)}
                            className="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-slate-100 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                        >
                            <RotateCcw className="size-3.5 text-slate-500" />
                            <span>Demander un remboursement (Démo)</span>
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
