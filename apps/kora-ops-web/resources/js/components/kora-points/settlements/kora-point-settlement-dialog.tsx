import {
    AlertCircle,
    Building2,
    Calendar,
    Camera,
    Check,
    CheckCircle2,
    Clock,
    FileText,
    Image as ImageIcon,
    Landmark,
    Smartphone,
    Trash2,
    UploadCloud,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import type {
    FinancialHealth,
    KoraPaymentDetails,
    PointSettlementMethod,
    SettlementPeriodPreview,
} from '@/types/kora-points';

type Props = {
    isOpen: boolean;
    health: FinancialHealth;
    pointName: string;
    koraPaymentDetails?: KoraPaymentDetails;
    onClose: () => void;
    onSubmit: (data: {
        saleUuids: string[];
        paymentMethod: PointSettlementMethod;
        paymentReference: string;
        proofFile: File;
        bankName?: string;
        notes?: string;
    }) => void;
};

const getTodayStr = () => new Date().toISOString().split('T')[0];

const get7DaysAgoStr = () => {
    const d = new Date();
    d.setDate(d.getDate() - 6);

    return d.toISOString().split('T')[0];
};

const getMonthStartStr = () => {
    const d = new Date();

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`;
};

const getLastWeekRange = () => {
    const d = new Date();
    d.setDate(d.getDate() - 7);
    const to = d.toISOString().split('T')[0];
    d.setDate(d.getDate() - 6);
    const from = d.toISOString().split('T')[0];

    return { from, to };
};

const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) {
        return `${bytes} o`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} Ko`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
};

export function KoraPointSettlementDialog({
    isOpen,
    health,
    pointName,
    koraPaymentDetails,
    onClose,
    onSubmit,
}: Props) {
    const todayStr = useMemo(() => getTodayStr(), []);
    const [step, setStep] = useState<1 | 2>(1);

    // Étape 1 : Période et calcul
    const [fromDate, setFromDate] = useState<string>(get7DaysAgoStr());
    const [toDate, setToDate] = useState<string>(todayStr);
    const [preview, setPreview] = useState<SettlementPeriodPreview | null>(
        null,
    );
    const [isLoadingPreview, setIsLoadingPreview] = useState(false);
    const [previewError, setPreviewError] = useState<string | null>(null);

    // Étape 2 : Modalité & saisie
    const [paymentMethod, setPaymentMethod] =
        useState<PointSettlementMethod>('BANK_TRANSFER');
    const [paymentReference, setPaymentReference] = useState('');
    const [bankName, setBankName] = useState('');
    const [notes, setNotes] = useState('');

    // Drag & Drop et suivi de progression d'upload
    const [isDragging, setIsDragging] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const [uploadProgress, setUploadProgress] = useState(0);
    const [uploadingFileName, setUploadingFileName] = useState('');
    const [proofFile, setProofFile] = useState<File | null>(null);
    const [proofPreview, setProofPreview] = useState<{
        name: string;
        size: string;
        isPdf: boolean;
    } | null>(null);

    const [error, setError] = useState<string | null>(null);

    const fileInputRef = useRef<HTMLInputElement>(null);
    const cameraInputRef = useRef<HTMLInputElement>(null);
    const uploadTimerRef = useRef<number | null>(null);

    const [prevIsOpen, setPrevIsOpen] = useState(isOpen);

    if (isOpen !== prevIsOpen) {
        setPrevIsOpen(isOpen);

        if (isOpen) {
            setStep(1);
        }
    }

    const fetchKey = isOpen ? `${fromDate}|${toDate}` : '';
    const [prevFetchKey, setPrevFetchKey] = useState(fetchKey);

    if (fetchKey !== prevFetchKey) {
        setPrevFetchKey(fetchKey);

        if (isOpen) {
            setIsLoadingPreview(true);
            setPreviewError(null);
        }
    }

    // Fetch sales preview for the given date range
    useEffect(() => {
        if (!isOpen) {
            return;
        }

        let cancelled = false;
        const queryParams = new URLSearchParams();

        if (fromDate) {
            queryParams.set('from', fromDate);
        }

        if (toDate) {
            queryParams.set('to', toDate);
        }

        fetch(`/kora-point/settlements/preview?${queryParams.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((res) => res.json())
            .then((json) => {
                if (cancelled) {
                    return;
                }

                if (json.success && json.data) {
                    setPreview(json.data);
                } else {
                    setPreviewError(
                        'Impossible de calculer les ventes pour cette période.',
                    );
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setPreviewError(
                        'Erreur de connexion lors du calcul des ventes de la période.',
                    );
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setIsLoadingPreview(false);
                }
            });

        return () => {
            cancelled = true;

            if (uploadTimerRef.current) {
                clearInterval(uploadTimerRef.current);
            }
        };
    }, [isOpen, fromDate, toDate]);

    if (!isOpen) {
        return null;
    }

    const handleApplyPeriod = (newFrom: string, newTo: string) => {
        setFromDate(newFrom);
        setToDate(newTo);
    };

    // Traitement du fichier téléversé avec suivi de progression
    const processFile = (file: File) => {
        setError(null);

        const isPdf =
            file.type === 'application/pdf' ||
            file.name.toLowerCase().endsWith('.pdf');
        const isImage =
            file.type.startsWith('image/') ||
            /\.(jpg|jpeg|png)$/i.test(file.name);

        if (!isPdf && !isImage) {
            setError(
                'Format non supporté. Veuillez sélectionner un fichier PDF ou une image (JPG, PNG).',
            );

            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            setError(
                'Le fichier dépasse la taille maximale autorisée de 10 Mo.',
            );

            return;
        }

        if (uploadTimerRef.current) {
            clearInterval(uploadTimerRef.current);
        }

        setUploadingFileName(file.name);
        setIsUploading(true);
        setUploadProgress(10);

        let current = 10;
        uploadTimerRef.current = window.setInterval(() => {
            current += 20;

            if (current >= 100) {
                if (uploadTimerRef.current) {
                    clearInterval(uploadTimerRef.current);
                }

                setUploadProgress(100);
                setTimeout(() => {
                    setIsUploading(false);
                    setProofFile(file);
                    setProofPreview({
                        name: file.name,
                        size: formatFileSize(file.size),
                        isPdf,
                    });
                }, 120);
            } else {
                setUploadProgress(current);
            }
        }, 60);
    };

    const handleRemoveFile = () => {
        if (uploadTimerRef.current) {
            clearInterval(uploadTimerRef.current);
        }

        setProofFile(null);
        setProofPreview(null);
        setIsUploading(false);
        setUploadProgress(0);

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }

        if (cameraInputRef.current) {
            cameraInputRef.current.value = '';
        }
    };

    // Gestionnaires Drag & Drop
    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(true);
    };

    const handleDragEnter = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);

        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            processFile(e.dataTransfer.files[0]);
        }
    };

    const handleGoToStep2 = () => {
        if (
            !preview ||
            preview.tickets_count === 0 ||
            preview.sale_uuids.length === 0
        ) {
            setError(
                'Aucune vente à reverser sur cette période. Modifiez la plage de dates.',
            );

            return;
        }

        setError(null);
        setStep(2);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);

        if (
            !preview ||
            preview.tickets_count === 0 ||
            preview.sale_uuids.length === 0
        ) {
            setError('Aucune vente à reverser sur cette période.');
            setStep(1);

            return;
        }

        if (!paymentReference.trim()) {
            setError(
                paymentMethod === 'CHECK'
                    ? 'Veuillez renseigner le numéro de chèque.'
                    : 'Veuillez renseigner la référence bancaire ou le TxID.',
            );

            return;
        }

        if (paymentMethod === 'CHECK' && !bankName.trim()) {
            setError(
                'Veuillez renseigner le nom de la banque émettrice du chèque.',
            );

            return;
        }

        if (!proofFile) {
            setError(
                'La preuve de versement (reçu bancaire, chèque ou bordereau) est obligatoire.',
            );

            return;
        }

        onSubmit({
            saleUuids: preview.sale_uuids,
            paymentMethod,
            paymentReference: paymentReference.trim(),
            proofFile,
            bankName: bankName.trim() || undefined,
            notes: notes.trim() || undefined,
        });

        onClose();
    };

    return (
        <div className="cash-modal-backdrop" role="presentation">
            <form
                className="cash-modal cash-opening-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="settlement-dialog-title"
                onSubmit={handleSubmit}
            >
                {/* En-tête canonique Style Caisse */}
                <div className="cash-modal-head">
                    <div>
                        <span className="section-kicker">
                            Trésorerie · Déclaration de versement · Étape {step}
                            /2
                        </span>
                        <h3 id="settlement-dialog-title">
                            {step === 1
                                ? 'Période des ventes à reverser'
                                : 'Mode de versement & Justificatif'}
                        </h3>
                        <p>
                            {step === 1
                                ? `${pointName} · Définissez la période pour calculer le montant net exact dû.`
                                : `Net exact à reverser : ${(preview?.net_amount ?? 0).toLocaleString('fr-FR')} FCFA (${preview?.tickets_count ?? 0} billets)`}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="cash-modal-close"
                        aria-label="Fermer la boîte de dialogue"
                    >
                        <X className="size-4" />
                    </button>
                </div>

                {/* Stepper horizontal compact */}
                <div className="mb-4 flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setStep(1)}
                        className={cn(
                            'flex flex-1 items-center justify-between rounded-lg border px-3 py-1 text-xs font-bold transition-colors',
                            step === 1
                                ? 'border-gold/50 bg-gold/10 text-foreground'
                                : 'border-border bg-muted/30 text-muted-foreground hover:text-foreground',
                        )}
                    >
                        <span>1. Période & Calcul</span>
                        {preview && preview.tickets_count > 0 && (
                            <Check className="size-3 text-emerald-500" />
                        )}
                    </button>
                    <button
                        type="button"
                        disabled={!preview || preview.tickets_count === 0}
                        onClick={handleGoToStep2}
                        className={cn(
                            'flex flex-1 items-center justify-between rounded-lg border px-3 py-1 text-xs font-bold transition-colors',
                            step === 2
                                ? 'border-gold/50 bg-gold/10 text-foreground'
                                : 'border-border bg-muted/30 text-muted-foreground disabled:opacity-40',
                        )}
                    >
                        <span>2. Règlement & Justificatif</span>
                    </button>
                </div>

                {/* Message d'erreur contextuel */}
                {error && (
                    <div className="mb-3 flex items-center gap-2 rounded-xl border border-destructive/30 bg-destructive/10 p-2.5 text-xs font-semibold text-destructive">
                        <AlertCircle className="size-4 shrink-0" />
                        <span>{error}</span>
                    </div>
                )}

                {/* ======================= ÉTAPE 1 : PÉRIODE ET CALCUL ======================= */}
                {step === 1 && (
                    <div className="space-y-4">
                        {/* Raccourcis de période en pilules */}
                        <div className="flex flex-wrap items-center gap-1.5">
                            <span className="mr-1 text-[0.625rem] font-bold text-muted-foreground uppercase">
                                Raccourcis :
                            </span>
                            <button
                                type="button"
                                onClick={() =>
                                    handleApplyPeriod(
                                        get7DaysAgoStr(),
                                        todayStr,
                                    )
                                }
                                className={cn(
                                    'rounded-md px-2.5 py-1 text-xs font-semibold transition-colors',
                                    fromDate === get7DaysAgoStr() &&
                                        toDate === todayStr
                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                        : 'bg-muted/70 text-muted-foreground hover:bg-muted hover:text-foreground',
                                )}
                            >
                                7 derniers jours
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    const r = getLastWeekRange();
                                    handleApplyPeriod(r.from, r.to);
                                }}
                                className="rounded-md bg-muted/70 px-2.5 py-1 text-xs font-semibold text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            >
                                Semaine passée
                            </button>
                            <button
                                type="button"
                                onClick={() =>
                                    handleApplyPeriod(
                                        getMonthStartStr(),
                                        todayStr,
                                    )
                                }
                                className={cn(
                                    'rounded-md px-2.5 py-1 text-xs font-semibold transition-colors',
                                    fromDate === getMonthStartStr() &&
                                        toDate === todayStr
                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                        : 'bg-muted/70 text-muted-foreground hover:bg-muted hover:text-foreground',
                                )}
                            >
                                Mois en cours
                            </button>
                            <button
                                type="button"
                                onClick={() => handleApplyPeriod('', todayStr)}
                                className={cn(
                                    'rounded-md px-2.5 py-1 text-xs font-semibold transition-colors',
                                    !fromDate
                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                        : 'bg-muted/70 text-muted-foreground hover:bg-muted hover:text-foreground',
                                )}
                            >
                                Toutes les ventes à reverser
                            </button>
                        </div>

                        {/* Sélecteurs de date */}
                        <div className="grid grid-cols-2 gap-3">
                            <label className="field">
                                <span>Ventes réalisées à partir du</span>
                                <input
                                    type="date"
                                    value={fromDate}
                                    onChange={(e) =>
                                        handleApplyPeriod(
                                            e.target.value,
                                            toDate,
                                        )
                                    }
                                    className="control"
                                />
                            </label>
                            <label className="field">
                                <span>Jusqu'au (inclus)</span>
                                <input
                                    type="date"
                                    value={toDate}
                                    onChange={(e) =>
                                        handleApplyPeriod(
                                            fromDate,
                                            e.target.value,
                                        )
                                    }
                                    className="control"
                                />
                            </label>
                        </div>

                        {/* Bloc de calcul dynamique */}
                        <div>
                            {isLoadingPreview ? (
                                <div className="flex items-center justify-center rounded-xl border border-border bg-muted/20 p-4">
                                    <Clock className="mr-2 size-4 animate-spin text-gold" />
                                    <span className="text-xs text-muted-foreground">
                                        Calcul des ventes non reversées de la
                                        période...
                                    </span>
                                </div>
                            ) : previewError ? (
                                <div className="rounded-xl border border-destructive/20 bg-destructive/10 p-3 text-xs text-destructive">
                                    {previewError}
                                </div>
                            ) : preview && preview.tickets_count > 0 ? (
                                <div className="space-y-2.5 rounded-xl border border-border bg-muted/15 p-3.5">
                                    <div className="flex items-center justify-between border-b border-border/60 pb-1.5 text-xs">
                                        <span className="flex items-center gap-1.5 font-extrabold text-foreground">
                                            <CheckCircle2 className="size-4 text-emerald-500" />
                                            {preview.tickets_count} billet(s)
                                            non reversé(s) détecté(s)
                                        </span>
                                        <span className="text-[0.6875rem] text-muted-foreground">
                                            Total restant dû :{' '}
                                            <strong className="text-foreground">
                                                {health.current_exposure.toLocaleString(
                                                    'fr-FR',
                                                )}{' '}
                                                FCFA
                                            </strong>
                                        </span>
                                    </div>
                                    <div className="grid grid-cols-3 gap-2">
                                        <div className="rounded-xl border border-border bg-card p-2.5">
                                            <span className="block text-[0.625rem] font-bold text-muted-foreground uppercase">
                                                Brut perçu
                                            </span>
                                            <strong className="mt-0.5 block font-mono text-xs font-bold text-foreground">
                                                {preview.gross_amount.toLocaleString(
                                                    'fr-FR',
                                                )}{' '}
                                                FCFA
                                            </strong>
                                        </div>
                                        <div className="rounded-xl border border-border bg-card p-2.5">
                                            <span className="block text-[0.625rem] font-bold text-muted-foreground uppercase">
                                                Commission conservée
                                            </span>
                                            <strong className="mt-0.5 block font-mono text-xs font-bold text-gold">
                                                +
                                                {preview.commission_amount.toLocaleString(
                                                    'fr-FR',
                                                )}{' '}
                                                FCFA
                                            </strong>
                                        </div>
                                        {/* Card Net dû : fond Navy unifié, solide, sans dégradé */}
                                        <div className="rounded-xl border border-[#001d4e] bg-[#001d4e] p-2.5 text-white">
                                            <span className="block text-[0.625rem] font-bold tracking-wider text-white/70 uppercase">
                                                Net exact dû
                                            </span>
                                            <strong className="mt-0.5 block font-mono text-xs font-black text-white">
                                                {preview.net_amount.toLocaleString(
                                                    'fr-FR',
                                                )}{' '}
                                                FCFA
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="rounded-xl border border-border bg-muted/20 p-3.5 text-center">
                                    <CheckCircle2 className="mx-auto mb-1 size-5 text-emerald-500" />
                                    <p className="text-xs font-bold text-foreground">
                                        Toutes les ventes de cette période ont
                                        déjà été entièrement reversées.
                                    </p>
                                    <p className="mt-0.5 text-[0.6875rem] text-muted-foreground">
                                        Sélectionnez une autre période ou
                                        utilisez les raccourcis ci-dessus.
                                    </p>
                                </div>
                            )}
                        </div>

                        {/* Actions Étape 1 */}
                        <div className="cash-modal-actions pt-2">
                            <button
                                type="button"
                                onClick={onClose}
                                className="btn btn-outline btn-sm"
                            >
                                Annuler
                            </button>
                            <button
                                type="button"
                                onClick={handleGoToStep2}
                                disabled={
                                    !preview ||
                                    preview.tickets_count === 0 ||
                                    isLoadingPreview
                                }
                                className="btn btn-primary btn-sm flex items-center gap-1.5 font-bold"
                            >
                                <span>Continuer vers le paiement</span>
                                <span>→</span>
                            </button>
                        </div>
                    </div>
                )}

                {/* ======================= ÉTAPE 2 : RÈGLEMENT ET JUSTIFICATIF ======================= */}
                {step === 2 && (
                    <div className="space-y-3.5">
                        {/* Récapitulatif compact de la sélection */}
                        <div className="flex items-center justify-between rounded-xl border border-border/80 bg-muted/40 px-3 py-2 text-xs">
                            <div className="flex items-center gap-2 truncate">
                                <Calendar className="size-3.5 shrink-0 text-gold" />
                                <span className="truncate text-muted-foreground">
                                    Période :{' '}
                                    <strong className="text-foreground">
                                        {fromDate || 'Origine'}
                                    </strong>{' '}
                                    au{' '}
                                    <strong className="text-foreground">
                                        {toDate}
                                    </strong>{' '}
                                    · {preview?.tickets_count} billet(s)
                                </span>
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                <strong className="font-mono text-xs font-black text-foreground">
                                    {preview?.net_amount.toLocaleString(
                                        'fr-FR',
                                    )}{' '}
                                    FCFA
                                </strong>
                                <button
                                    type="button"
                                    onClick={() => setStep(1)}
                                    className="ml-1 text-[0.6875rem] font-bold text-primary hover:underline"
                                >
                                    Modifier
                                </button>
                            </div>
                        </div>

                        {/* Choix du mode de paiement (4 boutons compacts) */}
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <button
                                type="button"
                                onClick={() =>
                                    setPaymentMethod('BANK_TRANSFER')
                                }
                                className={cn(
                                    'flex items-center justify-center gap-1.5 rounded-xl border px-2.5 py-2 text-xs font-bold transition-all',
                                    paymentMethod === 'BANK_TRANSFER'
                                        ? 'border-gold bg-gold/15 text-foreground ring-1 ring-gold/30'
                                        : 'border-border bg-card text-muted-foreground hover:bg-muted/40',
                                )}
                            >
                                <Landmark className="size-3.5 shrink-0 text-digital" />
                                <span>Virement</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => setPaymentMethod('BANK_DEPOSIT')}
                                className={cn(
                                    'flex items-center justify-center gap-1.5 rounded-xl border px-2.5 py-2 text-xs font-bold transition-all',
                                    paymentMethod === 'BANK_DEPOSIT'
                                        ? 'border-gold bg-gold/15 text-foreground ring-1 ring-gold/30'
                                        : 'border-border bg-card text-muted-foreground hover:bg-muted/40',
                                )}
                            >
                                <Building2 className="size-3.5 shrink-0 text-emerald-500" />
                                <span>Dépôt banque</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => setPaymentMethod('CHECK')}
                                className={cn(
                                    'flex items-center justify-center gap-1.5 rounded-xl border px-2.5 py-2 text-xs font-bold transition-all',
                                    paymentMethod === 'CHECK'
                                        ? 'border-gold bg-gold/15 text-foreground ring-1 ring-gold/30'
                                        : 'border-border bg-card text-muted-foreground hover:bg-muted/40',
                                )}
                            >
                                <FileText className="size-3.5 shrink-0 text-gold" />
                                <span>Chèque</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => setPaymentMethod('MOBILE_MONEY')}
                                className={cn(
                                    'flex items-center justify-center gap-1.5 rounded-xl border px-2.5 py-2 text-xs font-bold transition-all',
                                    paymentMethod === 'MOBILE_MONEY'
                                        ? 'border-gold bg-gold/15 text-foreground ring-1 ring-gold/30'
                                        : 'border-border bg-card text-muted-foreground hover:bg-muted/40',
                                )}
                            >
                                <Smartphone className="size-3.5 shrink-0 text-primary" />
                                <span>Mobile Money</span>
                            </button>
                        </div>

                        {/* Indication contextuelle selon le mode choisi (sans fausses coordonnées KORA) */}
                        {paymentMethod === 'CHECK' && (
                            <div className="rounded-xl border border-gold/40 bg-muted/40 p-2.5 text-xs text-foreground">
                                <span className="block text-[0.625rem] font-bold tracking-wider text-muted-foreground uppercase">
                                    Ordre légal obligatoire du chèque (barré,
                                    non endossable) :
                                </span>
                                <strong className="mt-0.5 block text-sm font-black text-foreground">
                                    {koraPaymentDetails?.check_order ??
                                        'KORA TRANSPORT & MOBILITY SA'}
                                </strong>
                            </div>
                        )}

                        {paymentMethod === 'BANK_TRANSFER' && (
                            <p className="text-[0.6875rem] text-muted-foreground">
                                Effectuez votre virement vers le compte bancaire
                                KORA habituel et indiquez la référence
                                d'opération ci-dessous.
                            </p>
                        )}

                        {paymentMethod === 'BANK_DEPOSIT' && (
                            <p className="text-[0.6875rem] text-muted-foreground">
                                Effectuez votre versement au guichet bancaire
                                sur le compte KORA habituel et joignez le reçu
                                ou bordereau ci-dessous.
                            </p>
                        )}

                        {paymentMethod === 'MOBILE_MONEY' && (
                            <p className="text-[0.6875rem] text-muted-foreground">
                                Effectuez le transfert vers le compte marchand
                                KORA et saisissez le numéro de transaction
                                (TxID) ci-dessous.
                            </p>
                        )}

                        {/* Champs de saisie (référence et banque/notes) */}
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <label className="field">
                                <span>
                                    {paymentMethod === 'CHECK'
                                        ? 'Numéro du chèque *'
                                        : paymentMethod === 'MOBILE_MONEY'
                                          ? 'ID de transaction (TxID) *'
                                          : 'N° d’opération ou bordereau bancaire *'}
                                </span>
                                <input
                                    type="text"
                                    value={paymentReference}
                                    onChange={(e) =>
                                        setPaymentReference(e.target.value)
                                    }
                                    placeholder={
                                        paymentMethod === 'CHECK'
                                            ? 'Ex: CHQ-0091823'
                                            : paymentMethod === 'MOBILE_MONEY'
                                              ? 'Ex: TXN-MTN-882910'
                                              : 'Ex: VER-BOA-2026-9812'
                                    }
                                    className="control"
                                    required
                                />
                            </label>

                            {paymentMethod === 'CHECK' ? (
                                <label className="field">
                                    <span>Banque émettrice du chèque *</span>
                                    <input
                                        type="text"
                                        value={bankName}
                                        onChange={(e) =>
                                            setBankName(e.target.value)
                                        }
                                        placeholder="Ex: Ecobank, BOA, NSIA, SG..."
                                        className="control"
                                        required
                                    />
                                </label>
                            ) : (
                                <label className="field">
                                    <span>Observations (Facultatif)</span>
                                    <input
                                        type="text"
                                        value={notes}
                                        onChange={(e) =>
                                            setNotes(e.target.value)
                                        }
                                        placeholder="Ex: Agence bancaire, compte émetteur..."
                                        className="control"
                                    />
                                </label>
                            )}
                        </div>

                        {/* ================= ZONE DE DRAG & DROP AVEC SUIVI DE PROGRESSION ================= */}
                        <div className="space-y-1.5">
                            <div className="flex items-center justify-between">
                                <span className="text-[0.6875rem] font-bold text-muted-foreground uppercase">
                                    Preuve de versement (Reçu, chèque ou
                                    bordereau) *
                                </span>
                                <span className="text-[0.625rem] font-medium text-muted-foreground">
                                    PDF, JPG, PNG (Max 10 Mo)
                                </span>
                            </div>

                            <input
                                ref={fileInputRef}
                                type="file"
                                accept=".pdf,image/jpeg,image/png,image/jpg"
                                className="hidden"
                                onChange={(e) =>
                                    e.target.files?.[0] &&
                                    processFile(e.target.files[0])
                                }
                            />
                            <input
                                ref={cameraInputRef}
                                type="file"
                                accept="image/*"
                                capture="environment"
                                className="hidden"
                                onChange={(e) =>
                                    e.target.files?.[0] &&
                                    processFile(e.target.files[0])
                                }
                            />

                            {/* Cas 1 : En cours d'upload avec progression */}
                            {isUploading ? (
                                <div className="space-y-2 rounded-xl border border-border bg-muted/30 p-3">
                                    <div className="flex items-center justify-between text-xs">
                                        <div className="flex items-center gap-2 truncate font-bold text-foreground">
                                            <Clock className="size-4 shrink-0 animate-spin text-gold" />
                                            <span className="truncate">
                                                {uploadingFileName}
                                            </span>
                                        </div>
                                        <span className="font-mono text-xs font-black text-foreground">
                                            {uploadProgress}%
                                        </span>
                                    </div>
                                    <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted/60">
                                        <div
                                            className="h-full rounded-full bg-[#001d4e] transition-all duration-150"
                                            style={{
                                                width: `${uploadProgress}%`,
                                            }}
                                        />
                                    </div>
                                    <p className="text-[0.625rem] font-medium text-muted-foreground">
                                        Téléversement et contrôle du fichier en
                                        cours...
                                    </p>
                                </div>
                            ) : proofPreview ? (
                                /* Cas 2 : Fichier prêt et vérifié */
                                <div className="flex items-center justify-between rounded-xl border border-emerald-500/40 bg-emerald-500/10 p-2.5">
                                    <div className="flex items-center gap-2.5 truncate">
                                        <div className="grid size-8 shrink-0 place-items-center rounded-lg bg-emerald-600 text-white">
                                            {proofPreview.isPdf ? (
                                                <FileText className="size-4" />
                                            ) : (
                                                <ImageIcon className="size-4" />
                                            )}
                                        </div>
                                        <div className="truncate">
                                            <p className="truncate text-xs font-bold text-foreground">
                                                {proofPreview.name}
                                            </p>
                                            <p className="mt-0.5 flex items-center gap-1 text-[0.625rem] font-semibold text-emerald-600">
                                                <CheckCircle2 className="size-3" />
                                                <span>
                                                    {proofPreview.size} ·
                                                    Justificatif prêt
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={handleRemoveFile}
                                        className="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-muted/50 hover:text-destructive"
                                        aria-label="Supprimer le fichier"
                                    >
                                        <Trash2 className="size-4" />
                                    </button>
                                </div>
                            ) : (
                                /* Cas 3 : Zone de Drag & Drop inactive / survol */
                                <div
                                    onDragOver={handleDragOver}
                                    onDragEnter={handleDragEnter}
                                    onDragLeave={handleDragLeave}
                                    onDrop={handleDrop}
                                    onClick={() =>
                                        fileInputRef.current?.click()
                                    }
                                    className={cn(
                                        'cursor-pointer rounded-xl border-2 border-dashed p-4 text-center transition-all',
                                        isDragging
                                            ? 'scale-[1.01] border-gold bg-gold/10'
                                            : 'border-border/80 bg-muted/20 hover:border-gold/60 hover:bg-muted/40',
                                    )}
                                >
                                    <UploadCloud
                                        className={cn(
                                            'mx-auto size-6 transition-transform',
                                            isDragging
                                                ? 'scale-110 text-gold'
                                                : 'text-muted-foreground',
                                        )}
                                    />
                                    <p className="mt-1 text-xs font-bold text-foreground">
                                        Glissez-déposez votre justificatif ici
                                    </p>
                                    <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                        ou{' '}
                                        <span className="font-bold text-primary underline">
                                            parcourez vos fichiers
                                        </span>{' '}
                                        (PDF, JPG, PNG max 10 Mo)
                                    </p>
                                    <div className="mt-2.5 flex items-center justify-center gap-2">
                                        <button
                                            type="button"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                cameraInputRef.current?.click();
                                            }}
                                            className="btn btn-outline btn-sm flex items-center gap-1 px-2.5 py-0.5 text-[0.6875rem]"
                                        >
                                            <Camera className="size-3" />
                                            <span>Prendre une photo</span>
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Actions Étape 2 */}
                        <div className="cash-modal-actions pt-2">
                            <button
                                type="button"
                                onClick={() => setStep(1)}
                                className="btn btn-outline btn-sm"
                            >
                                ← Modifier la période
                            </button>
                            <button
                                type="submit"
                                disabled={
                                    !proofFile ||
                                    !paymentReference.trim() ||
                                    isUploading
                                }
                                className="btn btn-primary btn-sm flex items-center gap-1.5 font-bold"
                            >
                                <CheckCircle2 className="size-3.5" />
                                <span>
                                    Valider le versement (
                                    {preview?.net_amount.toLocaleString(
                                        'fr-FR',
                                    )}{' '}
                                    FCFA)
                                </span>
                            </button>
                        </div>
                    </div>
                )}
            </form>
        </div>
    );
}
