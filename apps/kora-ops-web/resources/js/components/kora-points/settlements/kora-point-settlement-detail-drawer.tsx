import {
    X,
    Coins,
    Calendar,
    Landmark,
    FileText,
    CheckCircle2,
    Clock,
    AlertTriangle,
    Eye,
    Ticket,
    ChevronDown,
    ChevronUp,
    Hash,
    User,
} from 'lucide-react';
import { useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import type { PointSettlementRecord } from '../mock/kora-point-mock-data';

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
    const [isTicketsExpanded, setIsTicketsExpanded] = useState(false);
    const [isProofModalOpen, setIsProofModalOpen] = useState(false);

    if (!isOpen || !settlement) {
        return null;
    }

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
            className="fixed inset-0 z-50 animate-in overflow-hidden bg-slate-950/60 backdrop-blur-sm duration-200 fade-in"
            role="dialog"
            aria-modal="true"
        >
            <div className="absolute inset-y-0 right-0 flex max-w-full pl-10">
                <div className="flex h-full w-screen max-w-xl flex-col border-l border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                    {/* Drawer Header */}
                    <div className="flex shrink-0 items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4 dark:border-slate-800 dark:bg-slate-800/40">
                        <div className="flex items-center gap-3">
                            <div className="bg-navy flex h-10 w-10 items-center justify-center rounded-xl text-gold">
                                <Coins className="h-5 w-5" />
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h2 className="font-mono text-base font-black text-slate-900 dark:text-white">
                                        {settlement.reference}
                                    </h2>
                                    {getStatusBadge(settlement.status)}
                                </div>
                                <p className="text-xs text-slate-500">
                                    Dossier de versement émis par{' '}
                                    {settlement.created_by_name}
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
                    <div className="flex-1 space-y-6 overflow-y-auto p-6">
                        {/* Alert if Rejected */}
                        {settlement.status === 'REJECTED' &&
                            settlement.rejection_reason && (
                                <div className="rounded-xl border border-rose-300 bg-rose-50 p-4 text-xs dark:border-rose-900/60 dark:bg-rose-950/40">
                                    <div className="flex items-start gap-2">
                                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-rose-600" />
                                        <div className="space-y-1">
                                            <p className="font-bold text-rose-900 dark:text-rose-200">
                                                Motif du rejet par la trésorerie
                                                KORA
                                            </p>
                                            <p className="text-rose-800 dark:text-rose-300">
                                                {settlement.rejection_reason}
                                            </p>
                                            {onResubmit && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        onResubmit(settlement)
                                                    }
                                                    className="mt-2 inline-flex items-center gap-1 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-rose-700"
                                                >
                                                    Soumettre à nouveau avec une
                                                    nouvelle pièce
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            )}

                        {/* Financial Comparison: Declared vs Validated */}
                        <div className="rounded-2xl border border-slate-200 bg-slate-50/70 p-5 dark:border-slate-800 dark:bg-slate-800/30">
                            <h3 className="text-xs font-bold tracking-wider text-slate-400 uppercase">
                                Contrôle des montants
                            </h3>
                            <div className="mt-3 grid grid-cols-2 gap-4">
                                <div className="rounded-xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900">
                                    <span className="block text-[11px] font-semibold text-slate-400">
                                        Montant déclaré par le Point
                                    </span>
                                    <span className="mt-1 block text-lg font-black text-slate-900 dark:text-white">
                                        {settlement.amount_declared.toLocaleString()}{' '}
                                        FCFA
                                    </span>
                                    <span className="text-[11px] text-slate-500">
                                        Sur un attendu de{' '}
                                        {settlement.amount_expected.toLocaleString()}{' '}
                                        FCFA
                                    </span>
                                </div>

                                <div className="rounded-xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900">
                                    <span className="block text-[11px] font-semibold text-slate-400">
                                        Montant validé par KORA
                                    </span>
                                    <span
                                        className={cn(
                                            'mt-1 block text-lg font-black',
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
                            <h3 className="text-xs font-bold tracking-wider text-slate-400 uppercase">
                                Caractéristiques de l'opération
                            </h3>
                            <div className="divide-y divide-slate-100 rounded-xl border border-slate-200 text-xs dark:divide-slate-800 dark:border-slate-800">
                                <div className="flex justify-between p-3">
                                    <span className="flex items-center gap-1.5 text-slate-500">
                                        <Landmark className="h-3.5 w-3.5" />{' '}
                                        Mode de règlement
                                    </span>
                                    <span className="font-bold text-slate-900 dark:text-white">
                                        {settlement.payment_method ===
                                            'BANK_DEPOSIT' &&
                                            'Dépôt bancaire espèces'}
                                        {settlement.payment_method ===
                                            'BANK_TRANSFER' &&
                                            'Virement bancaire'}
                                        {settlement.payment_method ===
                                            'MOBILE_MONEY' &&
                                            'Mobile Money B2B'}
                                    </span>
                                </div>
                                <div className="flex justify-between p-3">
                                    <span className="flex items-center gap-1.5 text-slate-500">
                                        <Hash className="h-3.5 w-3.5" /> N°
                                        d'opération / Bordereau
                                    </span>
                                    <span className="font-mono font-bold text-slate-900 dark:text-white">
                                        {settlement.payment_reference}
                                    </span>
                                </div>
                                <div className="flex justify-between p-3">
                                    <span className="flex items-center gap-1.5 text-slate-500">
                                        <Calendar className="h-3.5 w-3.5" />{' '}
                                        Période des ventes couvertes
                                    </span>
                                    <span className="font-bold text-slate-900 dark:text-white">
                                        Du {settlement.period_start} au{' '}
                                        {settlement.period_end}
                                    </span>
                                </div>
                                <div className="flex justify-between p-3">
                                    <span className="flex items-center gap-1.5 text-slate-500">
                                        <User className="h-3.5 w-3.5" /> Déclaré
                                        par
                                    </span>
                                    <span className="font-bold text-slate-900 dark:text-white">
                                        {settlement.created_by_name} (
                                        {settlement.created_at})
                                    </span>
                                </div>
                                {settlement.notes && (
                                    <div className="p-3">
                                        <span className="mb-1 block text-slate-500">
                                            Observations :
                                        </span>
                                        <p className="text-slate-700 italic dark:text-slate-300">
                                            "{settlement.notes}"
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Piece justificative preview */}
                        <div className="space-y-3">
                            <h3 className="text-xs font-bold tracking-wider text-slate-400 uppercase">
                                Preuve de paiement téléversée
                            </h3>
                            <div className="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/40">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                                        <FileText className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p className="max-w-xs truncate text-xs font-bold text-slate-900 dark:text-white">
                                            {settlement.proof_file_name ||
                                                'bordereau_bancaire.pdf'}
                                        </p>
                                        <span className="text-[11px] text-slate-500">
                                            {settlement.proof_file_size ||
                                                '380 Ko'}{' '}
                                            · Reçu certifié
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
                                onClick={() =>
                                    setIsTicketsExpanded(!isTicketsExpanded)
                                }
                                className="flex w-full items-center justify-between rounded-xl border border-slate-200 p-3.5 text-xs font-bold text-slate-900 hover:bg-slate-50 dark:border-slate-800 dark:text-white dark:hover:bg-slate-800/40"
                            >
                                <span className="flex items-center gap-2">
                                    <Ticket className="text-gold-dark h-4 w-4 dark:text-gold" />
                                    Billets couverts par ce versement (
                                    {settlement.sales_count} ventes)
                                </span>
                                {isTicketsExpanded ? (
                                    <ChevronUp className="h-4 w-4 text-slate-400" />
                                ) : (
                                    <ChevronDown className="h-4 w-4 text-slate-400" />
                                )}
                            </button>

                            {isTicketsExpanded && (
                                <div className="max-h-48 space-y-2 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50/50 p-3 text-xs dark:border-slate-800 dark:bg-slate-800/20">
                                    {settlement.tickets_covered.map(
                                        (tkNumber) => (
                                            <div
                                                key={tkNumber}
                                                className="flex items-center justify-between rounded-lg border border-slate-100 bg-white px-2 py-1.5 dark:border-slate-800 dark:bg-slate-900"
                                            >
                                                <span className="font-mono font-bold text-slate-800 dark:text-slate-200">
                                                    {tkNumber}
                                                </span>
                                                <span className="text-[11px] text-slate-500">
                                                    Cotonou → Parakou (9 000
                                                    FCFA)
                                                </span>
                                            </div>
                                        ),
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Audit Timeline */}
                        <div className="space-y-3">
                            <h3 className="text-xs font-bold tracking-wider text-slate-400 uppercase">
                                Journal des actions & Rapprochement
                            </h3>
                            <div className="relative space-y-4 pl-6 before:absolute before:top-2 before:bottom-2 before:left-2.5 before:w-0.5 before:bg-slate-200 dark:before:bg-slate-800">
                                {settlement.timeline.map((step, idx) => (
                                    <div key={idx} className="relative">
                                        <div className="absolute top-0.5 -left-6 h-3 w-3 rounded-full border-2 border-white bg-gold dark:border-slate-900" />
                                        <div>
                                            <div className="flex items-center justify-between">
                                                <p className="text-xs font-bold text-slate-900 dark:text-white">
                                                    {step.step}
                                                </p>
                                                <span className="text-[10px] text-slate-400">
                                                    {step.date_label}
                                                </span>
                                            </div>
                                            <p className="mt-0.5 text-[11px] text-slate-500">
                                                Par {step.actor}
                                            </p>
                                            {step.comment && (
                                                <p className="mt-1 rounded-lg border border-slate-100 bg-slate-50 p-2 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300">
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
                    <div className="flex shrink-0 justify-end border-t border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/40">
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
                        className="relative w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="mb-4 flex items-center justify-between border-b pb-3">
                            <h4 className="text-sm font-bold text-slate-900 dark:text-white">
                                {settlement.proof_file_name ||
                                    'Bordereau bancaire'}
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
                        <div className="space-y-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center font-mono text-xs text-slate-700 dark:bg-slate-800/40 dark:text-slate-300">
                            <div className="text-navy border-b pb-2 font-bold tracking-wider uppercase dark:text-gold">
                                REÇU DE VERSEMENT ESPÈCES BANCAIRE
                            </div>
                            <div className="space-y-1 text-left">
                                <p>
                                    Banque : ECOBANK BÉNIN · Agence Saint-Michel
                                </p>
                                <p>
                                    Compte bénéficiaire : KORA EXPLOITATION SAS
                                </p>
                                <p>RIB : BJ063 01001 00293847101 22</p>
                                <p>
                                    Déposant : {settlement.created_by_name} (
                                    {settlement.point_name})
                                </p>
                                <p>
                                    Montant :{' '}
                                    {settlement.amount_declared.toLocaleString()}{' '}
                                    FCFA
                                </p>
                                <p>
                                    Référence guichet :{' '}
                                    {settlement.payment_reference}
                                </p>
                                <p>Date & Heure : {settlement.created_at}</p>
                            </div>
                            <div className="border-t pt-2 text-[10px] font-bold text-emerald-600 dark:text-emerald-400">
                                [TAMPON CAISSE BANCAIRE CERTIFIÉ]
                            </div>
                        </div>
                        <div className="mt-4 flex justify-end">
                            <button
                                type="button"
                                onClick={() => setIsProofModalOpen(false)}
                                className="rounded-xl bg-gold px-4 py-2 text-xs font-black text-kora uppercase"
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
