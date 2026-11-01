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
    if (bytes < 1024) return `${bytes} o`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} Ko`;
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
    if (!isOpen) return null;

    const todayStr = useMemo(() => getTodayStr(), []);
    const [step, setStep] = useState<1 | 2>(1);

    // Étape 1 : Période et calcul
    const [fromDate, setFromDate] = useState<string>(get7DaysAgoStr());
    const [toDate, setToDate] = useState<string>(todayStr);
    const [preview, setPreview] = useState<SettlementPeriodPreview | null>(null);
    const [isLoadingPreview, setIsLoadingPreview] = useState(false);
    const [previewError, setPreviewError] = useState<string | null>(null);

    // Étape 2 : Modalité & saisie
    const [paymentMethod, setPaymentMethod] = useState<PointSettlementMethod>('BANK_TRANSFER');
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

    // Fetch sales preview for the given date range
    const fetchPreview = async (from: string, to: string) => {
        setIsLoadingPreview(true);
        setPreviewError(null);
        try {
            const queryParams = new URLSearchParams();
            if (from) queryParams.set('from', from);
            if (to) queryParams.set('to', to);

            const res = await fetch(`/kora-point/settlements/preview?${queryParams.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const json = await res.json();
            if (json.success && json.data) {
                setPreview(json.data);
            } else {
                setPreviewError('Impossible de calculer les ventes pour cette période.');
            }
        } catch {
            setPreviewError('Erreur de connexion lors du calcul des ventes de la période.');
        } finally {
            setIsLoadingPreview(false);
        }
    };

    useEffect(() => {
        if (isOpen) {
            setStep(1);
            fetchPreview(fromDate, toDate);
        }
        return () => {
            if (uploadTimerRef.current) {
                clearInterval(uploadTimerRef.current);
            }
        };
    }, [isOpen]);

    const handleApplyPeriod = (newFrom: string, newTo: string) => {
        setFromDate(newFrom);
        setToDate(newTo);
        fetchPreview(newFrom, newTo);
    };

    // Traitement du fichier téléversé avec suivi de progression
    const processFile = (file: File) => {
        setError(null);

        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
        const isImage = file.type.startsWith('image/') || /\.(jpg|jpeg|png)$/i.test(file.name);

        if (!isPdf && !isImage) {
            setError('Format non supporté. Veuillez sélectionner un fichier PDF ou une image (JPG, PNG).');
            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            setError('Le fichier dépasse la taille maximale autorisée de 10 Mo.');
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
        if (fileInputRef.current) fileInputRef.current.value = '';
        if (cameraInputRef.current) cameraInputRef.current.value = '';
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
        if (!preview || preview.tickets_count === 0 || preview.sale_uuids.length === 0) {
            setError('Aucune vente à reverser sur cette période. Modifiez la plage de dates.');
            return;
        }
        setError(null);
        setStep(2);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);

        if (!preview || preview.tickets_count === 0 || preview.sale_uuids.length === 0) {
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
            setError('Veuillez renseigner le nom de la banque émettrice du chèque.');
            return;
        }

        if (!proofFile) {
            setError('La preuve de versement (reçu bancaire, chèque ou bordereau) est obligatoire.');
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
                            Trésorerie · Déclaration de versement · Étape {step}/2
                        </span>
                        <h3 id="settlement-dialog-title">
                            {step === 1 ? 'Période des ventes à reverser' : 'Mode de versement & Justificatif'}
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
                <div className="flex items-center gap-2 mb-4">
                    <button
                        type="button"
                        onClick={() => setStep(1)}
                        className={cn(
                            'flex-1 py-1 px-3 rounded-lg text-xs font-bold border transition-colors flex items-center justify-between',
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
                            'flex-1 py-1 px-3 rounded-lg text-xs font-bold border transition-colors flex items-center justify-between',
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
                    <div className="mb-3 rounded-xl border border-destructive/30 bg-destructive/10 p-2.5 text-xs font-semibold text-destructive flex items-center gap-2">
                        <AlertCircle className="size-4 shrink-0" />
                        <span>{error}</span>
                    </div>
                )}

                {/* ======================= ÉTAPE 1 : PÉRIODE ET CALCUL ======================= */}
                {step === 1 && (
                    <div className="space-y-4">
                        {/* Raccourcis de période en pilules */}
                        <div className="flex flex-wrap items-center gap-1.5">
                            <span className="text-[0.625rem] font-bold text-muted-foreground uppercase mr-1">
                                Raccourcis :
                            </span>
                            <button
                                type="button"
                                onClick={() => handleApplyPeriod(get7DaysAgoStr(), todayStr)}
                                className={cn(
                                    'rounded-md px-2.5 py-1 text-xs font-semibold transition-colors',
                                    fromDate === get7DaysAgoStr() && toDate === todayStr
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
                                className="rounded-md bg-muted/70 px-2.5 py-1 text-xs font-semibold text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                            >
                                Semaine passée
                            </button>
                            <button
                                type="button"
                                onClick={() => handleApplyPeriod(getMonthStartStr(), todayStr)}
                                className={cn(
                                    'rounded-md px-2.5 py-1 text-xs font-semibold transition-colors',
                                    fromDate === getMonthStartStr() && toDate === todayStr
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
                                    onChange={(e) => handleApplyPeriod(e.target.value, toDate)}
                                    className="control"
                                />
                            </label>
                            <label className="field">
                                <span>Jusqu'au (inclus)</span>
                                <input
                                    type="date"
                                    value={toDate}
                                    onChange={(e) => handleApplyPeriod(fromDate, e.target.value)}
                                    className="control"
                                />
                            </label>
                        </div>

                        {/* Bloc de calcul dynamique */}
                        <div>
                            {isLoadingPreview ? (
                                <div className="flex items-center justify-center p-4 rounded-xl bg-muted/20 border border-border">
                                    <Clock className="size-4 animate-spin text-gold mr-2" />
                                    <span className="text-xs text-muted-foreground">
                                        Calcul des ventes non reversées de la période...
                                    </span>
                                </div>
                            ) : previewError ? (
                                <div className="p-3 rounded-xl border border-destructive/20 bg-destructive/10 text-xs text-destructive">
                                    {previewError}
                                </div>
                            ) : preview && preview.tickets_count > 0 ? (
                                <div className="rounded-xl border border-border bg-muted/15 p-3.5 space-y-2.5">
                                    <div className="flex items-center justify-between border-b border-border/60 pb-1.5 text-xs">
                                        <span className="font-extrabold text-foreground flex items-center gap-1.5">
                                            <CheckCircle2 className="size-4 text-emerald-500" />
                                            {preview.tickets_count} billet(s) non reversé(s) détecté(s)
                                        </span>
                                        <span className="text-[0.6875rem] text-muted-foreground">
                                            Total restant dû : <strong className="text-foreground">{health.current_exposure.toLocaleString('fr-FR')} FCFA</strong>
                                        </span>
                                    </div>
                                    <div className="grid grid-cols-3 gap-2">
                                        <div className="rounded-xl bg-card p-2.5 border border-border">
                                            <span className="block text-[0.625rem] font-bold text-muted-foreground uppercase">
                                                Brut perçu
                                            </span>
                                            <strong className="block font-mono text-xs font-bold text-foreground mt-0.5">
                                                {preview.gross_amount.toLocaleString('fr-FR')} FCFA
                                            </strong>
                                        </div>
                                        <div className="rounded-xl bg-card p-2.5 border border-border">
                                            <span className="block text-[0.625rem] font-bold text-muted-foreground uppercase">
                                                Commission conservée
                                            </span>
                                            <strong className="block font-mono text-xs font-bold text-gold mt-0.5">
                                                +{preview.commission_amount.toLocaleString('fr-FR')} FCFA
                                            </strong>
                                        </div>
                                        {/* Card Net dû : fond Navy unifié, solide, sans dégradé */}
                                        <div className="rounded-xl bg-[#001d4e] p-2.5 border border-[#001d4e] text-white">
                                            <span className="block text-[0.625rem] font-bold text-white/70 uppercase tracking-wider">
                                                Net exact dû
                                            </span>
                                            <strong className="block font-mono text-xs font-black text-white mt-0.5">
                                                {preview.net_amount.toLocaleString('fr-FR')} FCFA
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="rounded-xl border border-border bg-muted/20 p-3.5 text-center">
                                    <CheckCircle2 className="size-5 text-emerald-500 mx-auto mb-1" />
                                    <p className="text-xs font-bold text-foreground">
                                        Toutes les ventes de cette période ont déjà été entièrement reversées.
                                    </p>
                                    <p className="text-[0.6875rem] text-muted-foreground mt-0.5">
                                        Sélectionnez une autre période ou utilisez les raccourcis ci-dessus.
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
                                disabled={!preview || preview.tickets_count === 0 || isLoadingPreview}
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
                        <div className="flex items-center justify-between rounded-xl bg-muted/40 border border-border/80 px-3 py-2 text-xs">
                            <div className="flex items-center gap-2 truncate">
                                <Calendar className="size-3.5 text-gold shrink-0" />
                                <span className="text-muted-foreground truncate">
                                    Période : <strong className="text-foreground">{fromDate || 'Origine'}</strong> au <strong className="text-foreground">{toDate}</strong>
                                    {' '}· {preview?.tickets_count} billet(s)
                                </span>
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
                                <strong className="font-mono text-xs font-black text-foreground">
                                    {preview?.net_amount.toLocaleString('fr-FR')} FCFA
                                </strong>
                                <button
                                    type="button"
                                    onClick={() => setStep(1)}
                                    className="text-[0.6875rem] font-bold text-primary hover:underline ml-1"
                                >
                                    Modifier
                                </button>
                            </div>
                        </div>

                        {/* Choix du mode de paiement (4 boutons compacts) */}
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <button
                                type="button"
                                onClick={() => setPaymentMethod('BANK_TRANSFER')}
                                className={cn(
                                    'flex items-center justify-center gap-1.5 rounded-xl border py-2 px-2.5 text-xs font-bold transition-all',
                                    paymentMethod === 'BANK_TRANSFER'
                                        ? 'border-gold bg-gold/15 text-foreground ring-1 ring-gold/30'
                                        : 'border-border bg-card text-muted-foreground hover:bg-muted/40',
                                )}
                            >
                                <Landmark className="size-3.5 text-digital shrink-0" />
                                <span>Virement</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => setPaymentMethod('BANK_DEPOSIT')}
                                className={cn(
                                    'flex items-center justify-center gap-1.5 rounded-xl border py-2 px-2.5 text-xs font-bold transition-all',
                                    paymentMethod === 'BANK_DEPOSIT'
                                        ? 'border-gold bg-gold/15 text-foreground ring-1 ring-gold/30'
                                        : 'border-border bg-card text-muted-foreground hover:bg-muted/40',
                                )}
                            >
                                <Building2 className="size-3.5 text-emerald-500 shrink-0" />
                                <span>Dépôt banque</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => setPaymentMethod('CHECK')}
                                className={cn(
                                    'flex items-center justify-center gap-1.5 rounded-xl border py-2 px-2.5 text-xs font-bold transition-all',
                                    paymentMethod === 'CHECK'
                                        ? 'border-gold bg-gold/15 text-foreground ring-1 ring-gold/30'
                                        : 'border-border bg-card text-muted-foreground hover:bg-muted/40',
                                )}
                            >
                                <FileText className="size-3.5 text-gold shrink-0" />
                                <span>Chèque</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => setPaymentMethod('MOBILE_MONEY')}
                                className={cn(
                                    'flex items-center justify-center gap-1.5 rounded-xl border py-2 px-2.5 text-xs font-bold transition-all',
                                    paymentMethod === 'MOBILE_MONEY'
                                        ? 'border-gold bg-gold/15 text-foreground ring-1 ring-gold/30'
                                        : 'border-border bg-card text-muted-foreground hover:bg-muted/40',
                                )}
                            >
                                <Smartphone className="size-3.5 text-primary shrink-0" />
                                <span>Mobile Money</span>
                            </button>
                        </div>

                        {/* Indication contextuelle selon le mode choisi (sans fausses coordonnées KORA) */}
                        {paymentMethod === 'CHECK' && (
                            <div className="rounded-xl bg-muted/40 border border-gold/40 p-2.5 text-xs text-foreground">
                                <span className="text-[0.625rem] font-bold text-muted-foreground uppercase tracking-wider block">
                                    Ordre légal obligatoire du chèque (barré, non endossable) :
                                </span>
                                <strong className="text-sm font-black text-foreground block mt-0.5">
                                    {koraPaymentDetails?.check_order ?? 'KORA TRANSPORT & MOBILITY SA'}
                                </strong>
                            </div>
                        )}

                        {paymentMethod === 'BANK_TRANSFER' && (
                            <p className="text-[0.6875rem] text-muted-foreground">
                                Effectuez votre virement vers le compte bancaire KORA habituel et indiquez la référence d'opération ci-dessous.
                            </p>
                        )}

                        {paymentMethod === 'BANK_DEPOSIT' && (
                            <p className="text-[0.6875rem] text-muted-foreground">
                                Effectuez votre versement au guichet bancaire sur le compte KORA habituel et joignez le reçu ou bordereau ci-dessous.
                            </p>
                        )}

                        {paymentMethod === 'MOBILE_MONEY' && (
                            <p className="text-[0.6875rem] text-muted-foreground">
                                Effectuez le transfert vers le compte marchand KORA et saisissez le numéro de transaction (TxID) ci-dessous.
                            </p>
                        )}

                        {/* Champs de saisie (référence et banque/notes) */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                                    onChange={(e) => setPaymentReference(e.target.value)}
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
                                        onChange={(e) => setBankName(e.target.value)}
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
                                        onChange={(e) => setNotes(e.target.value)}
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
                                    Preuve de versement (Reçu, chèque ou bordereau) *
                                </span>
                                <span className="text-[0.625rem] text-muted-foreground font-medium">
                                    PDF, JPG, PNG (Max 10 Mo)
                                </span>
                            </div>

                            <input
                                ref={fileInputRef}
                                type="file"
                                accept=".pdf,image/jpeg,image/png,image/jpg"
                                className="hidden"
                                onChange={(e) => e.target.files?.[0] && processFile(e.target.files[0])}
                            />
                            <input
                                ref={cameraInputRef}
                                type="file"
                                accept="image/*"
                                capture="environment"
                                className="hidden"
                                onChange={(e) => e.target.files?.[0] && processFile(e.target.files[0])}
                            />

                            {/* Cas 1 : En cours d'upload avec progression */}
                            {isUploading ? (
                                <div className="rounded-xl border border-border bg-muted/30 p-3 space-y-2">
                                    <div className="flex items-center justify-between text-xs">
                                        <div className="flex items-center gap-2 font-bold text-foreground truncate">
                                            <Clock className="size-4 animate-spin text-gold shrink-0" />
                                            <span className="truncate">{uploadingFileName}</span>
                                        </div>
                                        <span className="font-mono text-xs font-black text-foreground">
                                            {uploadProgress}%
                                        </span>
                                    </div>
                                    <div className="w-full bg-muted/60 rounded-full h-1.5 overflow-hidden">
                                        <div
                                            className="bg-[#001d4e] h-full transition-all duration-150 rounded-full"
                                            style={{ width: `${uploadProgress}%` }}
                                        />
                                    </div>
                                    <p className="text-[0.625rem] text-muted-foreground font-medium">
                                        Téléversement et contrôle du fichier en cours...
                                    </p>
                                </div>
                            ) : proofPreview ? (
                                /* Cas 2 : Fichier prêt et vérifié */
                                <div className="flex items-center justify-between rounded-xl border border-emerald-500/40 bg-emerald-500/10 p-2.5">
                                    <div className="flex items-center gap-2.5 truncate">
                                        <div className="grid size-8 place-items-center rounded-lg bg-emerald-600 text-white shrink-0">
                                            {proofPreview.isPdf ? (
                                                <FileText className="size-4" />
                                            ) : (
                                                <ImageIcon className="size-4" />
                                            )}
                                        </div>
                                        <div className="truncate">
                                            <p className="text-xs font-bold text-foreground truncate">
                                                {proofPreview.name}
                                            </p>
                                            <p className="text-[0.625rem] text-emerald-600 font-semibold mt-0.5 flex items-center gap-1">
                                                <CheckCircle2 className="size-3" />
                                                <span>{proofPreview.size} · Justificatif prêt</span>
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={handleRemoveFile}
                                        className="p-1.5 text-muted-foreground hover:text-destructive transition-colors rounded-lg hover:bg-muted/50"
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
                                    onClick={() => fileInputRef.current?.click()}
                                    className={cn(
                                        'rounded-xl border-2 border-dashed p-4 text-center cursor-pointer transition-all',
                                        isDragging
                                            ? 'border-gold bg-gold/10 scale-[1.01]'
                                            : 'border-border/80 bg-muted/20 hover:border-gold/60 hover:bg-muted/40',
                                    )}
                                >
                                    <UploadCloud
                                        className={cn(
                                            'size-6 mx-auto transition-transform',
                                            isDragging ? 'text-gold scale-110' : 'text-muted-foreground',
                                        )}
                                    />
                                    <p className="text-xs font-bold text-foreground mt-1">
                                        Glissez-déposez votre justificatif ici
                                    </p>
                                    <p className="text-[0.625rem] text-muted-foreground mt-0.5">
                                        ou <span className="text-primary font-bold underline">parcourez vos fichiers</span> (PDF, JPG, PNG max 10 Mo)
                                    </p>
                                    <div className="mt-2.5 flex items-center justify-center gap-2">
                                        <button
                                            type="button"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                cameraInputRef.current?.click();
                                            }}
                                            className="btn btn-outline btn-sm text-[0.6875rem] py-0.5 px-2.5 flex items-center gap-1"
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
                                disabled={!proofFile || !paymentReference.trim() || isUploading}
                                className="btn btn-primary btn-sm flex items-center gap-1.5 font-bold"
                            >
                                <CheckCircle2 className="size-3.5" />
                                <span>
                                    Valider le versement ({preview?.net_amount.toLocaleString('fr-FR')} FCFA)
                                </span>
                            </button>
                        </div>
                    </div>
                )}
            </form>
        </div>
    );
}
