import { useState, useMemo } from 'react';
import {
    X,
    Coins,
    Building2,
    Landmark,
    FileText,
    UploadCloud,
    Camera,
    Image as ImageIcon,
    CheckCircle2,
    AlertCircle,
    Info,
    Calendar,
    Hash,
    Calculator,
    Check,
} from 'lucide-react';
import {
    GroupSettlementBreakdown,
    OrganizationSubPoint,
    PointSettlementMethod,
} from '../mock/kora-point-mock-data';
import { cn } from '@/lib/utils';

type Props = {
    isOpen: boolean;
    organizationName: string;
    subpoints: OrganizationSubPoint[];
    onClose: () => void;
    onSubmit: (data: {
        totalAmountDeclared: number;
        paymentMethod: PointSettlementMethod;
        paymentReference: string;
        proofFileName?: string;
        breakdown: GroupSettlementBreakdown[];
        notes?: string;
    }) => void;
};

export function KoraOrgGroupSettlementDialog({
    isOpen,
    organizationName,
    subpoints,
    onClose,
    onSubmit,
}: Props) {
    if (!isOpen) return null;

    // Step state: 1: Selection & Ventilation, 2: Payment & Proof
    const [step, setStep] = useState<1 | 2>(1);

    // Selected subpoints (initially all with positive exposure)
    const [selectedSubPointUuids, setSelectedSubPointUuids] = useState<string[]>(
        subpoints.filter((sp) => sp.current_exposure > 0).map((sp) => sp.uuid),
    );

    // Sum of debts of selected subpoints
    const totalSelectedDebt = useMemo(() => {
        return subpoints
            .filter((sp) => selectedSubPointUuids.includes(sp.uuid))
            .reduce((sum, sp) => sum + sp.current_exposure, 0);
    }, [subpoints, selectedSubPointUuids]);

    // Declared global amount (defaulted to totalSelectedDebt)
    const [totalDeclared, setTotalDeclared] = useState<number>(totalSelectedDebt || 1500000);

    // Breakdown allocations: { [subpointUuid]: allocatedAmount }
    const [allocations, setAllocations] = useState<{ [uuid: string]: number }>(() => {
        const initial: { [uuid: string]: number } = {};
        subpoints.forEach((sp) => {
            initial[sp.uuid] = sp.current_exposure;
        });
        return initial;
    });

    // Payment details
    const [paymentMethod, setPaymentMethod] = useState<PointSettlementMethod>('BANK_TRANSFER');
    const [paymentReference, setPaymentReference] = useState('');
    const [notes, setNotes] = useState('');
    const [proofFile, setProofFile] = useState<{ name: string; size: string } | null>({
        name: 'ordre_virement_groupé_boa_laposte.pdf',
        size: '520 Ko',
    });
    const [error, setError] = useState<string | null>(null);

    // Balance check: sum of allocations vs totalDeclared
    const allocatedSum = useMemo(() => {
        return selectedSubPointUuids.reduce(
            (sum, uuid) => sum + (allocations[uuid] || 0),
            0,
        );
    }, [selectedSubPointUuids, allocations]);

    const balanceDelta = totalDeclared - allocatedSum;
    const isBalanced = Math.abs(balanceDelta) < 1;

    // Handler for automatic prorated ventilation
    const handleProrateVentilation = () => {
        if (totalSelectedDebt <= 0 || totalDeclared <= 0) return;

        const newAllocations: { [uuid: string]: number } = { ...allocations };
        let distributedSoFar = 0;

        const selectedPoints = subpoints.filter((sp) =>
            selectedSubPointUuids.includes(sp.uuid),
        );

        selectedPoints.forEach((sp, idx) => {
            if (idx === selectedPoints.length - 1) {
                // Assign remaining delta to last to avoid rounding errors
                newAllocations[sp.uuid] = totalDeclared - distributedSoFar;
            } else {
                const ratio = sp.current_exposure / totalSelectedDebt;
                const share = Math.round((totalDeclared * ratio) / 1000) * 1000;
                newAllocations[sp.uuid] = share;
                distributedSoFar += share;
            }
        });

        setAllocations(newAllocations);
    };

    const toggleSubpoint = (uuid: string) => {
        setSelectedSubPointUuids((prev) =>
            prev.includes(uuid) ? prev.filter((id) => id !== uuid) : [...prev, uuid],
        );
    };

    const handleAllocationChange = (uuid: string, val: number) => {
        setAllocations((prev) => ({
            ...prev,
            [uuid]: Math.max(0, val),
        }));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);

        if (!isBalanced) {
            setError(
                `La répartition n'est pas équilibrée. Veuillez ajuster les montants pour résorber l'écart de ${balanceDelta.toLocaleString()} FCFA.`,
            );
            return;
        }

        if (!paymentReference.trim()) {
            setError('Veuillez renseigner la référence bancaire ou le n° d’ordre de virement.');
            return;
        }

        const breakdown: GroupSettlementBreakdown[] = subpoints
            .filter((sp) => selectedSubPointUuids.includes(sp.uuid))
            .map((sp) => ({
                subpoint_uuid: sp.uuid,
                subpoint_name: sp.name,
                current_exposure: sp.current_exposure,
                amount_allocated: allocations[sp.uuid] || 0,
            }));

        onSubmit({
            totalAmountDeclared: totalDeclared,
            paymentMethod,
            paymentReference: paymentReference.trim(),
            proofFileName: proofFile?.name,
            breakdown,
            notes: notes.trim() || undefined,
        });

        onClose();
    };

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm overflow-y-auto animate-in fade-in duration-200"
            role="dialog"
            aria-modal="true"
        >
            <div className="relative my-8 w-full max-w-3xl rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900 overflow-hidden">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4 dark:border-slate-800 dark:bg-slate-800/40">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gold/15 text-gold-dark dark:bg-gold/25 dark:text-gold">
                            <Coins className="h-5 w-5" />
                        </div>
                        <div>
                            <h2 className="text-base font-black text-slate-900 dark:text-white">
                                Préparer un reversement groupé multi-points
                            </h2>
                            <p className="text-xs text-slate-500">
                                {organizationName} · Rôle Trésorier d'Organisation
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

                <form onSubmit={handleSubmit} className="p-6 space-y-6">
                    {error && (
                        <div className="rounded-xl border border-rose-300 bg-rose-50 p-4 text-xs font-semibold text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200 flex items-center gap-2">
                            <AlertCircle className="h-4 w-4 shrink-0 text-rose-600" />
                            <span>{error}</span>
                        </div>
                    )}

                    {/* Step Tabs */}
                    <div className="flex border-b border-slate-200 dark:border-slate-800 text-xs font-bold">
                        <button
                            type="button"
                            onClick={() => setStep(1)}
                            className={cn(
                                'pb-3 px-4 border-b-2 flex items-center gap-2',
                                step === 1
                                    ? 'border-gold text-slate-900 dark:text-white'
                                    : 'border-transparent text-slate-400 hover:text-slate-600',
                            )}
                        >
                            <span className="flex h-5 w-5 items-center justify-center rounded-full bg-slate-100 text-[10px] dark:bg-slate-800">
                                1
                            </span>
                            Bureaux & Ventilation comptable
                        </button>
                        <button
                            type="button"
                            onClick={() => setStep(2)}
                            className={cn(
                                'pb-3 px-4 border-b-2 flex items-center gap-2',
                                step === 2
                                    ? 'border-gold text-slate-900 dark:text-white'
                                    : 'border-transparent text-slate-400 hover:text-slate-600',
                            )}
                        >
                            <span className="flex h-5 w-5 items-center justify-center rounded-full bg-slate-100 text-[10px] dark:bg-slate-800">
                                2
                            </span>
                            Règlement bancaire & Justificatif
                        </button>
                    </div>

                    {step === 1 && (
                        <div className="space-y-5">
                            {/* Global Amount Banner */}
                            <div className="rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-800/30">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                                    <div>
                                        <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                            <Coins className="mr-1.5 h-4 w-4 text-gold-dark dark:text-gold" />
                                            Montant global déclaré du virement
                                            <span className="text-red-500 font-bold ml-1">*</span>
                                        </label>
                                        <div className="relative">
                                            <input
                                                type="number"
                                                step={10000}
                                                value={totalDeclared || ''}
                                                onChange={(e) =>
                                                    setTotalDeclared(Number(e.target.value))
                                                }
                                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-base font-black text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                            />
                                            <span className="absolute right-3 top-2.5 text-xs font-black text-slate-400">
                                                FCFA
                                            </span>
                                        </div>
                                    </div>

                                    <div className="flex flex-col justify-end gap-1.5">
                                        <div className="flex justify-between text-xs">
                                            <span className="text-slate-500">Dette cumulée sélectionnée :</span>
                                            <span className="font-bold text-slate-900 dark:text-white">
                                                {totalSelectedDebt.toLocaleString()} FCFA
                                            </span>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={handleProrateVentilation}
                                            className="inline-flex items-center justify-center gap-1.5 rounded-xl bg-navy px-3 py-2 text-xs font-bold text-white hover:bg-navy/90 dark:bg-slate-700"
                                        >
                                            <Calculator className="h-3.5 w-3.5 text-gold" />
                                            Ventiler automatiquement au prorata
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {/* Subpoints ventilation table */}
                            <div className="space-y-2">
                                <div className="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300">
                                    <span className="flex items-center gap-1.5">
                                        <Building2 className="h-4 w-4 text-slate-500" />
                                        Répartition par bureau de poste ({selectedSubPointUuids.length} sélectionnés)
                                    </span>
                                    {isBalanced ? (
                                        <span className="inline-flex items-center text-[11px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 px-2.5 py-0.5 rounded-full dark:bg-emerald-950/40">
                                            <Check className="h-3 w-3 mr-1" />
                                            Ventilation équilibrée (0 FCFA d'écart)
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center text-[11px] font-bold text-amber-700 dark:text-amber-300 bg-amber-50 px-2.5 py-0.5 rounded-full dark:bg-amber-950/40">
                                            <AlertCircle className="h-3 w-3 mr-1" />
                                            Écart : {balanceDelta > 0 ? `+${balanceDelta.toLocaleString()}` : balanceDelta.toLocaleString()} FCFA
                                        </span>
                                    )}
                                </div>

                                <div className="rounded-xl border border-slate-200 overflow-hidden text-xs dark:border-slate-800">
                                    <table className="w-full text-left">
                                        <thead className="bg-slate-50 text-slate-500 dark:bg-slate-800/40 border-b border-slate-200 dark:border-slate-800">
                                            <tr>
                                                <th className="px-3 py-2 w-8"></th>
                                                <th className="px-3 py-2 font-bold uppercase">Bureau</th>
                                                <th className="px-3 py-2 font-bold uppercase text-right">Dette actuelle</th>
                                                <th className="px-3 py-2 font-bold uppercase text-right w-44">Montant alloué</th>
                                                <th className="px-3 py-2 font-bold uppercase text-right">Solde résiduel</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                            {subpoints.map((sp) => {
                                                const isChecked = selectedSubPointUuids.includes(sp.uuid);
                                                const allocated = allocations[sp.uuid] || 0;
                                                const residual = Math.max(0, sp.current_exposure - (isChecked ? allocated : 0));

                                                return (
                                                    <tr
                                                        key={sp.uuid}
                                                        className={cn(
                                                            'transition-colors',
                                                            isChecked ? 'bg-white dark:bg-slate-900' : 'bg-slate-50/50 opacity-60 dark:bg-slate-900/50',
                                                        )}
                                                    >
                                                        <td className="px-3 py-2.5 text-center">
                                                            <input
                                                                type="checkbox"
                                                                checked={isChecked}
                                                                onChange={() => toggleSubpoint(sp.uuid)}
                                                                className="rounded border-slate-300 text-gold focus:ring-gold"
                                                            />
                                                        </td>
                                                        <td className="px-3 py-2.5 font-bold text-slate-900 dark:text-white">
                                                            {sp.name}
                                                            <span className="block font-mono text-[10px] text-slate-400 font-normal">
                                                                {sp.code} · {sp.city}
                                                            </span>
                                                        </td>
                                                        <td className="px-3 py-2.5 text-right font-semibold text-slate-700 dark:text-slate-300">
                                                            {sp.current_exposure.toLocaleString()} FCFA
                                                        </td>
                                                        <td className="px-3 py-2.5 text-right">
                                                            <div className="relative">
                                                                <input
                                                                    type="number"
                                                                    disabled={!isChecked}
                                                                    step={5000}
                                                                    value={isChecked ? allocated : 0}
                                                                    onChange={(e) =>
                                                                        handleAllocationChange(
                                                                            sp.uuid,
                                                                            Number(e.target.value),
                                                                        )
                                                                    }
                                                                    className="w-full text-right rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs font-bold text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                                                />
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-2.5 text-right font-black text-amber-600 dark:text-amber-400">
                                                            {residual.toLocaleString()} FCFA
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div className="flex justify-end pt-2">
                                <button
                                    type="button"
                                    onClick={() => setStep(2)}
                                    className="rounded-xl bg-gold px-6 py-2.5 text-xs font-black uppercase text-kora hover:bg-gold-light"
                                >
                                    Suivant : Règlement & Justificatif →
                                </button>
                            </div>
                        </div>
                    )}

                    {step === 2 && (
                        <div className="space-y-5">
                            {/* Payment info inputs */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-1.5">
                                    <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <Landmark className="mr-1.5 h-4 w-4 text-slate-500" />
                                        Mode de règlement
                                        <span className="text-red-500 font-bold ml-1">*</span>
                                    </label>
                                    <select
                                        value={paymentMethod}
                                        onChange={(e) =>
                                            setPaymentMethod(e.target.value as PointSettlementMethod)
                                        }
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    >
                                        <option value="BANK_TRANSFER">
                                            Virement interbancaire (Compte BOA KORA SAS)
                                        </option>
                                        <option value="BANK_DEPOSIT">
                                            Dépôt espèces en agence bancaire
                                        </option>
                                    </select>
                                </div>

                                <div className="space-y-1.5">
                                    <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                                        <Hash className="mr-1.5 h-4 w-4 text-slate-500" />
                                        N° d'ordre de virement / Référence banque
                                        <span className="text-red-500 font-bold ml-1">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={paymentReference}
                                        onChange={(e) => setPaymentReference(e.target.value)}
                                        placeholder="Ex: VIR-BOA-LP-2026-9901"
                                        className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        required
                                    />
                                </div>
                            </div>

                            {/* Proof file upload */}
                            <div className="space-y-2">
                                <label className="flex items-center text-xs font-bold text-slate-700 dark:text-slate-300">
                                    <UploadCloud className="mr-1.5 h-4 w-4 text-slate-500" />
                                    Ordre de virement signé ou avis bancaire
                                    <span className="text-red-500 font-bold ml-1">*</span>
                                </label>
                                {proofFile ? (
                                    <div className="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 text-xs dark:border-emerald-800/60 dark:bg-emerald-950/20">
                                        <div className="flex items-center gap-3">
                                            <FileText className="h-6 w-6 text-emerald-600" />
                                            <div>
                                                <p className="font-bold text-slate-900 dark:text-white">
                                                    {proofFile.name}
                                                </p>
                                                <span className="text-slate-500">{proofFile.size} · Justificatif conforme</span>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setProofFile(null)}
                                            className="text-xs font-bold text-slate-600 hover:text-rose-600 dark:text-slate-300"
                                        >
                                            Remplacer
                                        </button>
                                    </div>
                                ) : (
                                    <div className="rounded-xl border-2 border-dashed border-slate-300 p-6 text-center dark:border-slate-700">
                                        <UploadCloud className="mx-auto h-8 w-8 text-slate-400" />
                                        <p className="mt-2 text-xs font-semibold text-slate-700 dark:text-slate-300">
                                            Déposez l'avis de virement ici
                                        </p>
                                        <div className="mt-3 flex justify-center gap-2">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setProofFile({
                                                        name: 'avis_virement_boa_0108.pdf',
                                                        size: '420 Ko',
                                                    })
                                                }
                                                className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                            >
                                                Choisir un fichier
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Notes */}
                            <div className="space-y-1">
                                <label className="text-xs font-bold text-slate-700 dark:text-slate-300">
                                    Notes comptables pour la trésorerie KORA
                                </label>
                                <textarea
                                    value={notes}
                                    onChange={(e) => setNotes(e.target.value)}
                                    rows={2}
                                    placeholder="Précisions sur les comptes auxiliaires ou modalités de lettrage..."
                                    className="w-full rounded-xl border border-slate-300 bg-white p-3 text-xs font-normal text-slate-900 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                />
                            </div>

                            {/* Actions */}
                            <div className="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setStep(1)}
                                    className="rounded-xl border border-slate-300 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300"
                                >
                                    ← Retour à la ventilation
                                </button>
                                <button
                                    type="submit"
                                    disabled={!isBalanced}
                                    className={cn(
                                        'inline-flex items-center gap-2 rounded-xl px-6 py-2.5 text-xs font-black uppercase tracking-wider',
                                        isBalanced
                                            ? 'bg-gold text-kora shadow-sm hover:bg-gold-light'
                                            : 'bg-slate-200 text-slate-400 cursor-not-allowed dark:bg-slate-800',
                                    )}
                                >
                                    <CheckCircle2 className="h-4 w-4" />
                                    Transmettre le reversement groupé
                                </button>
                            </div>
                        </div>
                    )}
                </form>
            </div>
        </div>
    );
}
