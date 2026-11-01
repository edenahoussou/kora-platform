import { Download, FileSpreadsheet, FileText, X, Check } from 'lucide-react';
import { useState } from 'react';

type Props = {
    isOpen: boolean;
    exportType: 'EXCEL' | 'PDF';
    periodLabel: string;
    pointName: string;
    onClose: () => void;
};

export function KoraPointExportDemoDialog({
    isOpen,
    exportType,
    periodLabel,
    pointName,
    onClose,
}: Props) {
    const [downloading, setDownloading] = useState(false);
    const [downloadDone, setDownloadDone] = useState(false);

    if (!isOpen) {
        return null;
    }

    const fileName =
        exportType === 'EXCEL'
            ? `Commissions_${pointName.replace(/\s+/g, '_')}_${periodLabel.replace(/\s+/g, '_')}.xlsx`
            : `Releve_Commissions_${pointName.replace(/\s+/g, '_')}_${periodLabel.replace(/\s+/g, '_')}.pdf`;

    const handleDownload = () => {
        setDownloading(true);
        setTimeout(() => {
            setDownloading(false);
            setDownloadDone(true);
            setTimeout(() => {
                setDownloadDone(false);
                onClose();
            }, 1800);
        }, 1000);
    };

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label="Export de démonstration"
            className="fixed inset-0 z-50 flex animate-in items-center justify-center bg-black/60 p-4 backdrop-blur-md fade-in"
        >
            <div className="relative w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-7 dark:border-slate-800 dark:bg-slate-900">
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
                    <div className="grid size-11 place-items-center rounded-2xl bg-amber-500/15 text-amber-700 dark:text-amber-300">
                        {exportType === 'EXCEL' ? (
                            <FileSpreadsheet className="size-6 text-emerald-600" />
                        ) : (
                            <FileText className="size-6 text-rose-600" />
                        )}
                    </div>
                    <div>
                        <div className="flex items-center gap-1.5">
                            <span className="py-0.2 rounded-full bg-amber-500/20 px-2 text-[0.625rem] font-black text-amber-900 uppercase dark:text-amber-200">
                                Exemple de démonstration
                            </span>
                        </div>
                        <h2 className="text-base font-black text-slate-900 dark:text-white">
                            {exportType === 'EXCEL'
                                ? 'Export Excel des Commissions'
                                : 'Relevé officiel des Commissions (PDF)'}
                        </h2>
                    </div>
                </div>

                {/* Preview Details */}
                <div className="my-5 space-y-2.5 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-xs dark:border-slate-800 dark:bg-slate-950">
                    <div className="flex justify-between">
                        <span className="text-slate-500">Périmètre :</span>
                        <strong className="text-slate-900 dark:text-white">
                            {pointName}
                        </strong>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-slate-500">
                            Période exportée :
                        </span>
                        <strong className="text-slate-900 dark:text-white">
                            {periodLabel}
                        </strong>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-slate-500">Nom du fichier :</span>
                        <span className="font-mono text-[0.6875rem] text-slate-600 dark:text-slate-300">
                            {fileName}
                        </span>
                    </div>
                    <div className="border-t border-slate-200 pt-2 text-[0.6875rem] text-slate-400 dark:border-slate-800">
                        Le fichier généré inclut la ventilation par vente, les
                        taux historiques figés, les déductions sur
                        remboursements et la synthèse nette.
                    </div>
                </div>

                {/* Download Status Action */}
                <div className="flex items-center justify-end gap-2.5 pt-2">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        Annuler
                    </button>

                    <button
                        type="button"
                        onClick={handleDownload}
                        disabled={downloading || downloadDone}
                        className="inline-flex items-center gap-2 rounded-xl bg-gold px-5 py-2 text-xs font-black tracking-wider text-kora uppercase shadow-md transition hover:bg-gold/90 disabled:opacity-50"
                    >
                        {downloadDone ? (
                            <>
                                <Check className="size-4" />
                                <span>Fichier téléchargé</span>
                            </>
                        ) : downloading ? (
                            <span>Génération en cours...</span>
                        ) : (
                            <>
                                <Download className="size-4" />
                                <span>Télécharger l’exemple</span>
                            </>
                        )}
                    </button>
                </div>
            </div>
        </div>
    );
}
