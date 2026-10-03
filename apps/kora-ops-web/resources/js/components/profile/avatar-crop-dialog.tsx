import {
    Camera,
    Check,
    RefreshCw,
    RotateCw,
    X,
    ZoomIn,
    ZoomOut,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface AvatarCropDialogProps {
    open: boolean;
    imageSrc: string | null;
    onClose: () => void;
    onSave: (croppedImageUrl: string) => void;
}

/**
 * Boîte de dialogue interactive de cadrage et recadrage d'avatar KORA OPS.
 */
export function AvatarCropDialog({
    open,
    imageSrc,
    onClose,
    onSave,
}: AvatarCropDialogProps) {
    const [zoom, setZoom] = useState(1);
    const [rotation, setRotation] = useState(0);
    const [offset, setOffset] = useState({ x: 0, y: 0 });
    const [isDragging, setIsDragging] = useState(false);
    const [dragStart, setDragStart] = useState({ x: 0, y: 0 });
    const imageRef = useRef<HTMLImageElement | null>(null);

    const handleMouseDown = (e: React.MouseEvent) => {
        setIsDragging(true);
        setDragStart({ x: e.clientX - offset.x, y: e.clientY - offset.y });
    };

    const handleMouseMove = (e: React.MouseEvent) => {
        if (!isDragging) {
            return;
        }

        setOffset({
            x: e.clientX - dragStart.x,
            y: e.clientY - dragStart.y,
        });
    };

    const handleMouseUp = () => {
        setIsDragging(false);
    };

    const handleTouchStart = (e: React.TouchEvent) => {
        if (e.touches.length === 1) {
            setIsDragging(true);
            setDragStart({
                x: e.touches[0].clientX - offset.x,
                y: e.touches[0].clientY - offset.y,
            });
        }
    };

    const handleTouchMove = (e: React.TouchEvent) => {
        if (!isDragging || e.touches.length !== 1) {
            return;
        }

        setOffset({
            x: e.touches[0].clientX - dragStart.x,
            y: e.touches[0].clientY - dragStart.y,
        });
    };

    const handleTouchEnd = () => {
        setIsDragging(false);
    };

    const handleRotate = () => {
        setRotation((prev) => (prev + 90) % 360);
    };

    const handleReset = () => {
        setZoom(1);
        setRotation(0);
        setOffset({ x: 0, y: 0 });
    };

    const handleApplyCrop = () => {
        if (!imageRef.current) {
            return;
        }

        const canvas = document.createElement('canvas');
        const cropSize = 400; // Résolution d'export haute définition
        canvas.width = cropSize;
        canvas.height = cropSize;
        const ctx = canvas.getContext('2d');

        if (!ctx) {
            return;
        }

        const img = imageRef.current;
        ctx.fillStyle = '#001D4E';
        ctx.fillRect(0, 0, cropSize, cropSize);

        ctx.save();
        ctx.translate(cropSize / 2, cropSize / 2);
        ctx.rotate((rotation * Math.PI) / 180);
        ctx.scale(zoom, zoom);

        const scaleFactor = cropSize / 240;
        const destWidth = (cropSize * (img.naturalWidth / Math.min(img.naturalWidth, img.naturalHeight)));
        const destHeight = (cropSize * (img.naturalHeight / Math.min(img.naturalWidth, img.naturalHeight)));

        ctx.drawImage(
            img,
            -destWidth / 2 + (offset.x * scaleFactor),
            -destHeight / 2 + (offset.y * scaleFactor),
            destWidth,
            destHeight,
        );
        ctx.restore();

        const croppedDataUrl = canvas.toDataURL('image/jpeg', 0.92);
        onSave(croppedDataUrl);
        onClose();
        handleReset();
        toast.success('Photo de profil mise à jour', {
            description: 'Le cadrage a été appliqué avec succès.',
        });
    };

    if (!imageSrc) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={(val) => !val && onClose()}>
            <DialogContent className="max-w-lg rounded-2xl p-6 sm:p-7">
                <DialogHeader>
                    <div className="flex items-center gap-2 text-foreground">
                        <span className="grid size-8 place-items-center rounded-lg bg-gold/15 text-kora dark:text-gold">
                            <Camera className="size-4" />
                        </span>
                        <div>
                            <DialogTitle className="text-base font-extrabold tracking-[-0.02em]">
                                Cadrer la photo de profil
                            </DialogTitle>
                            <DialogDescription className="text-xs text-muted-foreground mt-0.5">
                                Ajustez le zoom et glissez l'image pour positionner le visage.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div className="my-4 space-y-5">
                    {/* Viewport de cadrage interactif */}
                    <div className="flex flex-col items-center justify-center">
                        <div
                            className="relative size-64 sm:size-72 overflow-hidden rounded-2xl border-2 border-gold/60 bg-slate-900 shadow-xl cursor-grab active:cursor-grabbing select-none"
                            onMouseDown={handleMouseDown}
                            onMouseMove={handleMouseMove}
                            onMouseUp={handleMouseUp}
                            onMouseLeave={handleMouseUp}
                            onTouchStart={handleTouchStart}
                            onTouchMove={handleTouchMove}
                            onTouchEnd={handleTouchEnd}
                        >
                            {/* Image manipulable */}
                            <img
                                ref={imageRef}
                                src={imageSrc}
                                alt="À recadrer"
                                draggable={false}
                                className="pointer-events-none absolute left-1/2 top-1/2 max-w-none origin-center transition-transform duration-75"
                                style={{
                                    transform: `translate(-50%, -50%) translate(${offset.x}px, ${offset.y}px) scale(${zoom}) rotate(${rotation}deg)`,
                                    width: '100%',
                                    height: '100%',
                                    objectFit: 'cover',
                                }}
                            />

                            {/* Masque de cadrage carré arrondi KORA */}
                            <div className="pointer-events-none absolute inset-0 rounded-2xl border-2 border-white/40 ring-4 ring-black/40" />

                            {/* Repères de grille */}
                            <div className="pointer-events-none absolute inset-0 grid grid-cols-3 grid-rows-3 opacity-25">
                                <div className="border-r border-b border-white" />
                                <div className="border-r border-b border-white" />
                                <div className="border-b border-white" />
                                <div className="border-r border-b border-white" />
                                <div className="border-r border-b border-white" />
                                <div className="border-b border-white" />
                                <div className="border-r border-white" />
                                <div className="border-r border-white" />
                                <div />
                            </div>
                        </div>

                        <span className="mt-2 text-[11px] text-muted-foreground">
                            Glissez avec la souris ou le doigt pour centrer le sujet.
                        </span>
                    </div>

                    {/* Contrôles de zoom & rotation */}
                    <div className="rounded-xl border border-border bg-muted/40 p-4 space-y-3">
                        <div className="flex items-center justify-between text-xs font-bold text-foreground">
                            <span className="flex items-center gap-1.5">
                                <ZoomIn className="size-3.5 text-muted-foreground" />
                                <span>Niveau de zoom</span>
                            </span>
                            <span className="font-mono text-[11px] text-muted-foreground">
                                {zoom.toFixed(1)}x
                            </span>
                        </div>

                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setZoom((z) => Math.max(1, z - 0.2))}
                                className="grid size-8 place-items-center rounded-lg border border-border bg-card text-muted-foreground hover:text-foreground"
                                title="Zoom arrière"
                            >
                                <ZoomOut className="size-3.5" />
                            </button>

                            <input
                                type="range"
                                min="1"
                                max="3"
                                step="0.05"
                                value={zoom}
                                onChange={(e) => setZoom(parseFloat(e.target.value))}
                                className="h-2 flex-1 cursor-pointer accent-gold bg-muted rounded-lg"
                            />

                            <button
                                type="button"
                                onClick={() => setZoom((z) => Math.min(3, z + 0.2))}
                                className="grid size-8 place-items-center rounded-lg border border-border bg-card text-muted-foreground hover:text-foreground"
                                title="Zoom avant"
                            >
                                <ZoomIn className="size-3.5" />
                            </button>
                        </div>

                        <div className="flex items-center justify-between pt-2 border-t border-border/60">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={handleRotate}
                                className="rounded-lg text-xs"
                            >
                                <RotateCw className="mr-1.5 size-3.5 text-digital" />
                                Pivoter de 90°
                            </Button>

                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={handleReset}
                                className="rounded-lg text-xs text-muted-foreground hover:text-foreground"
                            >
                                <RefreshCw className="mr-1.5 size-3.5" />
                                Réinitialiser
                            </Button>
                        </div>
                    </div>
                </div>

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={onClose}
                        className="rounded-xl text-xs"
                    >
                        <X className="mr-1.5 size-3.5" />
                        Annuler
                    </Button>

                    <Button
                        type="button"
                        onClick={handleApplyCrop}
                        className="rounded-xl bg-kora text-white hover:bg-[#002d75] dark:bg-gold dark:text-kora text-xs font-bold px-5"
                    >
                        <Check className="mr-1.5 size-4" />
                        Valider et appliquer
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
