import { useState } from 'react';
import {
    X,
    Coins,
    Building2,
    Calendar,
    Landmark,
    FileText,
    CheckCircle2,
    Clock,
    AlertTriangle,
    Eye,
    Download,
    Ticket,
    ChevronDown,
    ChevronUp,
    ShieldCheck,
    Hash,
    User,
    ArrowUpRight,
} from 'lucide-react';
import { PointSettlementRecord } from '../mock/kora-point-mock-data';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';

type Props = {
    settlement: PointSettlementRecord | null;
    isOpen: boolean;
    onClose: () => void;
    onResubmit?: (settlement: PointSettlementRecord) => void;
};

export function KoraPointSettlementDetailDrawer({
    settlement,
    isOpen,
    onClose,
    onResubmit,
}: Props) {
    if (!isOpen || !settlement) return null;

    const [isTicketsExpanded, setIsTicketsExpanded] = useState(false);
    const [isProofModalOpen, setIsProofModalOpen] = useState(false);

    const getStatusBadge = (status: PointSettlementRecord['status']) => {
        switch (status) {
            case 'VALIDATED':
                return (
                    <KoraStatusBadge
                        variant="solid"
                        colorScheme="emerald"
                        icon={<CheckCircle2 className="h-3 w-3" />}
                    >
                        Validé & Rapproché
                    </KoraStatusBadge>
                );
            case 'DECLARED':
                return (
                    <KoraStatusBadge
                        variant="solid"
                        colorScheme="amber"
                        icon={<Clock className="h-3 w-3" />}
                    >
                        En attente vérification KORA
                    </KoraStatusBadge>
                );
            case 'REJECTED':
                return (
                    <KoraStatusBadge
                        variant="solid"
                        colorScheme="destructive"
                        icon={<AlertTriangle className="h-3 w-3" />}
                    >
                        Rejeté (Action requise)
                    </KoraStatusBadge>
                );
            case 'PARTIALLY_VALIDATED':
                return (
                    <KoraStatusBadge
                        variant="solid"
                        colorScheme="blue"
                        icon={<Clock className="h-3 w-3" />}
                    >
                        Validation partielle
                    </KoraStatusBadge>
                );
            default:
                return (
                    <KoraStatusBadge variant="solid" colorScheme="navy">
                        Brouillon
                    </KoraStatusBadge>
                );
        }
    };

    return (
        <div
            className="fixed inset-0 z-50 overflow-hidden bg-slate-950/60 backdrop-blur-sm animate-in fade-in duration-200"
            role="dialog"
            aria-modal="true"
        >
            <div className="absolute inset-y-0 right-0 max-w-full flex pl-10">
                <div className="w-screen max-w-xl bg-white shadow-2xl dark:bg-slate-900 border-l border-slate-200 dark:border-slate-800 flex flex-col h-full">
                    {/* Drawer Header */}
                    <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4 dark:border-slate-800 dark:bg-slate-800/40 shrink-0">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-navy text-gold">
                                <Coins className="h-5 w-5" />
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h2 className="text-base font-black text-slate-900 dark:text-white font-mono">
                                        {settlement.reference}
                                    </h2>
                                    {getStatusBadge(settlement.status)}
                                </div>
                                <p className="text-xs text-slate-500">
                                    Dossier de versement émis par {settlement.created_by_name}
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

                    {/* Drawer Body Scrollable */}
                    <div className="flex-1 overflow-y-auto p-6 space-y-6">
                        {/* Alert if Rejected */}
                        {settlement.status === 'REJECTED' && settlement.rejection_reason && (
                            <div className="rounded-xl border border-rose-300 bg-rose-50 p-4 text-xs dark:border-rose-900/60 dark:bg-rose-950/40">
                                <div className="flex items-start gap-2">
                                    <AlertTriangle className="h-4 w-4 shrink-0 text-rose-600 mt-0.5" />
                                    <div className="space-y-1">
                                        <p className="font-bold text-rose-900 dark:text-rose-200">
                                            Motif du rejet par la trésorerie KORA
                                        </p>
                                        <p className="text-rose-800 dark:text-rose-300">
                                            {settlement.rejection_reason}
                                        </p>
                                        {onResubmit && (
                                            <button
                                                type="button"
                                                onClick={() => onResubmit(settlement)}
                                                className="mt-2 inline-flex items-center gap-1 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-rose-700"
                                            >
                                                Soumettre à nouveau avec une nouvelle pièce
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Financial Comparison: Declared vs Validated */}
                        <div className="rounded-2xl border border-slate-200 bg-slate-50/70 p-5 dark:border-slate-800 dark:bg-slate-800/30">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                Contrôle des montants
                            </h3>
                            <div className="mt-3 grid grid-cols-2 gap-4">
                                <div className="rounded-xl bg-white p-3.5 border border-slate-200 dark:bg-slate-900 dark:border-slate-800">
                                    <span className="text-[11px] font-semibold text-slate-400 block">
                                        Montant déclaré par le Point
                                    </span>
                                    <span className="mt-1 text-lg font-black text-slate-900 dark:text-white block">
                                        {settlement.amount_declared.toLocaleString()} FCFA
                                    </span>
                                    <span className="text-[11px] text-slate-500">
                                        Sur un attendu de {settlement.amount_expected.toLocaleString()} FCFA
                                    </span>
                                </div>

                                <div className="rounded-xl bg-white p-3.5 border border-slate-200 dark:bg-slate-900 dark:border-slate-800">
                                    <span className="text-[11px] font-semibold text-slate-400 block">
                                        Montant validé par KORA
                                    </span>
                                    <span
                                        className={cn(
                                            'mt-1 text-lg font-black block',
                                            settlement.amount_validated
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : 'text-amber-500',
                                        )}
                                    >
                                        {settlement.amount_validated
                                            ? `${settlement.amount_validated.toLocaleString()} FCFA`
                                            : 'En attente de pointage'}
                                    </span>
                                    <span className="text-[11px] text-slate-500">
                                        {settlement.validated_at
                                            ? `Confirmé le ${settlement.validated_at}`
                                            : 'Rapprochement bancaire sous 24h'}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Transaction Details */}
                        <div className="space-y-3">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                Caractéristiques de l'opération
                            </h3>
                            <div className="rounded-xl border border-slate-200 divide-y divide-slate-100 text-xs dark:border-slate-800 dark:divide-slate-800">
                                <div className="flex justify-between p-3">
                                    <span className="text-slate-500 flex items-center gap-1.5">
                                        <Landmark className="h-3.5 w-3.5" /> Mode de règlement
                                    </span>
                                    <span className="font-bold text-slate-900 dark:text-white">
                                        {settlement.payment_method === 'BANK_DEPOSIT' && 'Dépôt bancaire espèces'}
                                        {settlement.payment_method === 'BANK_TRANSFER' && 'Virement bancaire'}
                                        {settlement.payment_method === 'CHECK' && 'Chèque bancaire'}
                                        {settlement.payment_method === 'MOBILE_MONEY' && 'Mobile Money B2B'}
                                    </span>
                                </div>
                                <div className="flex justify-between p-3">
                                    <span className="text-slate-500 flex items-center gap-1.5">
                                        <Hash className="h-3.5 w-3.5" /> N° d'opération / Bordereau
                                    </span>
                                    <span className="font-mono font-bold text-slate-900 dark:text-white">
                                        {settlement.payment_reference}
                                    </span>
                                </div>
                                <div className="flex justify-between p-3">
                                    <span className="text-slate-500 flex items-center gap-1.5">
                                        <Calendar className="h-3.5 w-3.5" /> Période des ventes couvertes
                                    </span>
                                    <span className="font-bold text-slate-900 dark:text-white">
                                        Du {settlement.period_start} au {settlement.period_end}
                                    </span>
                                </div>
                                <div className="flex justify-between p-3">
                                    <span className="text-slate-500 flex items-center gap-1.5">
                                        <User className="h-3.5 w-3.5" /> Déclaré par
                                    </span>
                                    <span className="font-bold text-slate-900 dark:text-white">
                                        {settlement.created_by_name} ({settlement.created_at})
                                    </span>
                                </div>
                                {settlement.notes && (
                                    <div className="p-3">
                                        <span className="text-slate-500 block mb-1">Observations :</span>
                                        <p className="text-slate-700 italic dark:text-slate-300">
                                            "{settlement.notes}"
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Piece justificative preview */}
                        <div className="space-y-3">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                Preuve de paiement téléversée
                            </h3>
                            <div className="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/40">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                                        <FileText className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p className="text-xs font-bold text-slate-900 dark:text-white truncate max-w-xs">
                                            {settlement.proof_file_name || 'bordereau_bancaire.pdf'}
                                        </p>
                                        <span className="text-[11px] text-slate-500">
                                            {settlement.proof_file_size || '380 Ko'} · Reçu certifié
                                        </span>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setIsProofModalOpen(true)}
                                    className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                >
                                    <Eye className="h-3.5 w-3.5 text-slate-500" />
                                    Visualiser
                                </button>
                            </div>
                        </div>

                        {/* Covered tickets accordion */}
                        <div className="space-y-2">
                            <button
                                type="button"
                                onClick={() => setIsTicketsExpanded(!isTicketsExpanded)}
                                className="w-full flex items-center justify-between rounded-xl border border-slate-200 p-3.5 text-xs font-bold text-slate-900 hover:bg-slate-50 dark:border-slate-800 dark:text-white dark:hover:bg-slate-800/40"
                            >
                                <span className="flex items-center gap-2">
                                    <Ticket className="h-4 w-4 text-gold-dark dark:text-gold" />
                                    Billets couverts par ce versement ({settlement.sales_count} ventes)
                                </span>
                                {isTicketsExpanded ? (
                                    <ChevronUp className="h-4 w-4 text-slate-400" />
                                ) : (
                                    <ChevronDown className="h-4 w-4 text-slate-400" />
                                )}
                            </button>

                            {isTicketsExpanded && (
                                <div className="rounded-xl border border-slate-200 bg-slate-50/50 p-3 space-y-2 text-xs dark:border-slate-800 dark:bg-slate-800/20 max-h-48 overflow-y-auto">
                                    {settlement.tickets_covered.map((tkNumber) => (
                                        <div
                                            key={tkNumber}
                                            className="flex items-center justify-between py-1.5 px-2 bg-white rounded-lg border border-slate-100 dark:bg-slate-900 dark:border-slate-800"
                                        >
                                            <span className="font-mono font-bold text-slate-800 dark:text-slate-200">
                                                {tkNumber}
                                            </span>
                                            <span className="text-[11px] text-slate-500">
                                                Cotonou → Parakou (9 000 FCFA)
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Audit Timeline */}
                        <div className="space-y-3">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                Journal des actions & Rapprochement
                            </h3>
                            <div className="relative pl-6 space-y-4 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200 dark:before:bg-slate-800">
                                {settlement.timeline.map((step, idx) => (
                                    <div key={idx} className="relative">
                                        <div className="absolute -left-6 top-0.5 h-3 w-3 rounded-full border-2 border-white bg-gold dark:border-slate-900" />
                                        <div>
                                            <div className="flex items-center justify-between">
                                                <p className="text-xs font-bold text-slate-900 dark:text-white">
                                                    {step.step}
                                                </p>
                                                <span className="text-[10px] text-slate-400">
                                                    {step.date_label}
                                                </span>
                                            </div>
                                            <p className="text-[11px] text-slate-500 mt-0.5">
                                                Par {step.actor}
                                            </p>
                                            {step.comment && (
                                                <p className="mt-1 text-xs text-slate-600 bg-slate-50 p-2 rounded-lg border border-slate-100 dark:bg-slate-800/60 dark:text-slate-300 dark:border-slate-800">
                                                    {step.comment}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Drawer Footer */}
                    <div className="border-t border-slate-100 p-4 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/40 flex justify-end shrink-0">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            Fermer
                        </button>
                    </div>
                </div>
            </div>

            {/* Proof Modal Viewer Preview */}
            {isProofModalOpen && (
                <div
                    className="fixed inset-0 z-60 flex items-center justify-center bg-black/80 p-4 backdrop-blur-md"
                    onClick={() => setIsProofModalOpen(false)}
                >
                    <div
                        className="relative max-w-lg w-full bg-white rounded-2xl p-6 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-center justify-between border-b pb-3 mb-4">
                            <h4 className="text-sm font-bold text-slate-900 dark:text-white">
                                {settlement.proof_file_name || 'Bordereau bancaire'}
                            </h4>
                            <button
                                type="button"
                                onClick={() => setIsProofModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        {/* Simulated bank receipt preview */}
                        <div className="rounded-xl border border-dashed border-slate-300 p-6 bg-slate-50 dark:bg-slate-800/40 text-center space-y-4 font-mono text-xs text-slate-700 dark:text-slate-300">
                            <div className="border-b pb-2 font-bold uppercase tracking-wider text-navy dark:text-gold">
                                REÇU DE VERSEMENT ESPÈCES BANCAIRE
                            </div>
                            <div className="space-y-1 text-left">
                                <p>Banque : ECOBANK BÉNIN · Agence Saint-Michel</p>
                                <p>Compte bénéficiaire : KORA EXPLOITATION SAS</p>
                                <p>RIB : BJ063 01001 00293847101 22</p>
                                <p>Déposant : {settlement.created_by_name} ({settlement.point_name})</p>
                                <p>Montant : {settlement.amount_declared.toLocaleString()} FCFA</p>
                                <p>Référence guichet : {settlement.payment_reference}</p>
                                <p>Date & Heure : {settlement.created_at}</p>
                            </div>
                            <div className="pt-2 border-t text-[10px] text-emerald-600 dark:text-emerald-400 font-bold">
                                [TAMPON CAISSE BANCAIRE CERTIFIÉ]
                            </div>
                        </div>
                        <div className="mt-4 flex justify-end">
                            <button
                                type="button"
                                onClick={() => setIsProofModalOpen(false)}
                                className="rounded-xl bg-gold px-4 py-2 text-xs font-black uppercase text-kora"
                            >
                                Fermer la vue
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
