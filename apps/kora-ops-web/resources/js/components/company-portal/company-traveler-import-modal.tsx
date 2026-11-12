import { useForm } from '@inertiajs/react';
import { Download, FileUp, FileSpreadsheet, X } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';
import companyPortal from '@/routes/company-portal';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function CompanyTravelerImportModal({ open, onOpenChange }: Props) {
    const form = useForm<{ file: File | null }>({ file: null });
    const fileInput = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (open) {
            form.setData('file', null);
            form.clearErrors();

            if (fileInput.current) {
                fileInput.current.value = '';
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    if (!open) {
        return null;
    }

    const close = () => onOpenChange(false);

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (form.data.file === null) {
            return;
        }

        form.post(companyPortal.travelers.import.url(), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <div
            className="cash-modal-backdrop"
            role="presentation"
            onMouseDown={(event) =>
                event.target === event.currentTarget && close()
            }
        >
            <form
                className="cash-modal cash-opening-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="company-traveler-import-title"
                onSubmit={submit}
            >
                <div className="cash-modal-head">
                    <div>
                        <span className="section-kicker">
                            Membres voyageurs
                        </span>
                        <h3 id="company-traveler-import-title">
                            Importer une liste de voyageurs
                        </h3>
                        <p>
                            Les lignes valides créent ou mettent à jour les
                            fiches ; le téléphone sert de clé de dédoublonnage.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={close}
                        className="cash-modal-close"
                        aria-label="Fermer"
                        disabled={form.processing}
                    >
                        <X className="size-4" />
                    </button>
                </div>

                <div className="cash-opening-list">
                    <div className="cash-opening-row">
                        <div className="cash-opening-row-head">
                            <span className="cash-opening-icon">
                                <FileSpreadsheet className="size-4" />
                            </span>
                            <div>
                                <strong>Fichier Excel ou CSV</strong>
                                <small>
                                    Première ligne = en-têtes (prenom, nom,
                                    telephone…). Formats acceptés : .xlsx, .xls,
                                    .csv — 100 Mo max.
                                </small>
                            </div>
                        </div>
                        <div className="cash-opening-fields">
                            <a
                                href={companyPortal.travelers.template.url()}
                                className="flex items-center justify-center gap-2 rounded-xl border border-dashed border-gold/40 bg-gold/5 px-4 py-3 text-xs font-bold text-foreground transition-colors hover:bg-gold/10"
                            >
                                <Download className="size-4 text-gold" />
                                Télécharger le modèle Excel
                            </a>
                            <label className="field">
                                <span>
                                    Fichier à importer <b>*</b>
                                </span>
                                <input
                                    ref={fileInput}
                                    type="file"
                                    accept=".xlsx,.xls,.csv,.txt"
                                    className="control file:mr-3 file:rounded-lg file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-foreground"
                                    onChange={(event) =>
                                        form.setData(
                                            'file',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                    aria-invalid={!!form.errors.file}
                                />
                                {form.errors.file && (
                                    <small className="field-error-text">
                                        {form.errors.file}
                                    </small>
                                )}
                            </label>
                        </div>
                    </div>
                </div>

                <div className="cash-modal-actions">
                    <button
                        type="button"
                        className="btn btn-ghost"
                        onClick={close}
                        disabled={form.processing}
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        className={cn(
                            'btn btn-primary',
                            (form.processing || form.data.file === null) &&
                                'is-disabled',
                        )}
                        disabled={form.processing || form.data.file === null}
                    >
                        <FileUp className="size-4" />
                        {form.processing ? 'Import en cours…' : 'Importer'}
                    </button>
                </div>
            </form>
        </div>
    );
}
