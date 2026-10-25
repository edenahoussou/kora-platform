import { Copy, Download, ExternalLink, Link2, QrCode } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useRef } from 'react';
import { toast } from 'sonner';
import { copyToClipboard } from '@/components/app-releases/shared';

type Props = {
    appLabel: string;
    appSlug: string;
    downloadPageUrl: string;
    latestApkUrl: string;
    hasPublishedRelease: boolean;
};

/**
 * Partage du lien public de téléchargement (WhatsApp / e-mail aux équipes
 * terrain) et QR code pointant vers la même page, à scanner depuis un P501
 * ou à imprimer en agence.
 */
export function ReleaseSharePanel({
    appLabel,
    appSlug,
    downloadPageUrl,
    latestApkUrl,
    hasPublishedRelease,
}: Props) {
    const qrContainer = useRef<HTMLDivElement>(null);

    const copy = async (text: string, label: string) => {
        if (await copyToClipboard(text)) {
            toast.success(`${label} copié dans le presse-papiers.`);
        } else {
            toast.error(
                'Copie impossible : sélectionnez le lien manuellement.',
            );
        }
    };

    const downloadQr = () => {
        const svg = qrContainer.current?.querySelector('svg');

        if (!svg) {
            return;
        }

        const markup = new XMLSerializer().serializeToString(svg);
        const blob = new Blob(
            [`<?xml version="1.0" encoding="UTF-8"?>\n${markup}`],
            { type: 'image/svg+xml' },
        );
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `qr-${appSlug}.svg`;
        link.click();
        URL.revokeObjectURL(url);
    };

    return (
        <section className="cash-table-card grid gap-6 p-5 md:grid-cols-[1fr_auto] md:p-6">
            <div className="min-w-0">
                <span className="section-kicker flex items-center gap-1.5">
                    <Link2 className="size-3.5" /> Lien de téléchargement
                </span>
                <h2 className="mt-1 text-lg font-extrabold tracking-[-0.03em] text-foreground">
                    Page publique {appLabel}
                </h2>
                <p className="mt-1 text-xs leading-5 text-muted-foreground">
                    Partagez ce lien aux équipes terrain (WhatsApp, e-mail) : il
                    présente toujours la dernière version publiée et n’exige
                    aucune connexion.
                </p>

                <div className="mt-4 flex flex-col gap-2">
                    <input
                        readOnly
                        value={downloadPageUrl}
                        aria-label="Lien de la page de téléchargement"
                        onFocus={(event) => event.currentTarget.select()}
                        className="h-11 w-full min-w-0 rounded-xl bg-muted/55 px-3 font-mono text-xs text-foreground ring-1 ring-black/[0.045] outline-none focus:ring-2 focus:ring-gold/35"
                    />
                    <button
                        type="button"
                        className="btn btn-primary gap-2 self-start font-black whitespace-nowrap"
                        onClick={() =>
                            copy(downloadPageUrl, 'Lien de téléchargement')
                        }
                    >
                        <Copy className="size-4" /> Copier le lien de
                        téléchargement
                    </button>
                </div>

                <div className="mt-3 flex flex-wrap gap-2">
                    <a
                        href={downloadPageUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="btn btn-ghost gap-2 text-xs"
                    >
                        <ExternalLink className="size-3.5" /> Ouvrir la page
                    </a>
                    <button
                        type="button"
                        className="btn btn-ghost gap-2 text-xs"
                        disabled={!hasPublishedRelease}
                        onClick={() => copy(latestApkUrl, 'Lien direct APK')}
                    >
                        <Copy className="size-3.5" /> Copier le lien direct
                        (.apk)
                    </button>
                </div>

                {!hasPublishedRelease && (
                    <p className="mt-3 text-xs font-semibold text-gold">
                        Aucune version publiée : la page affiche pour l’instant
                        « Aucune version disponible ».
                    </p>
                )}
            </div>

            <div className="flex flex-col items-center gap-2 justify-self-center">
                <div
                    ref={qrContainer}
                    className="rounded-2xl bg-white p-3 ring-1 ring-black/[0.06]"
                >
                    <QRCodeSVG
                        value={downloadPageUrl}
                        size={148}
                        level="M"
                        marginSize={1}
                        title={`QR code de téléchargement ${appLabel}`}
                    />
                </div>
                <span className="flex items-center gap-1 text-[0.6875rem] text-muted-foreground">
                    <QrCode className="size-3" /> Scannez depuis le terminal
                </span>
                <button
                    type="button"
                    className="btn btn-ghost gap-1.5 px-2.5 py-1 text-[0.6875rem]"
                    onClick={downloadQr}
                >
                    <Download className="size-3.5" /> Télécharger le QR code
                </button>
            </div>
        </section>
    );
}
