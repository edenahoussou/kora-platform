import { useForm } from '@inertiajs/react';
import { FileUp, Package, ShieldAlert, Upload, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { formatBytes } from '@/components/app-releases/shared';
import type { AppRelease } from '@/components/app-releases/shared';
import administration from '@/routes/administration';

type Props = {
    app: { value: string; label: string };
    latest: AppRelease | null;
    maxPackageKilobytes: number;
    /** Limite PHP effective (upload_max_filesize / post_max_size), en octets. */
    serverMaxUploadBytes: number | null;
    maxBuildNumber: number;
    onClose: () => void;
};

type UploadForm = {
    app: string;
    package: File | null;
    version: string;
    build_number: string;
    release_notes: string;
    is_mandatory: boolean;
};

/**
 * Publication d'un package APK (MEZ-150). Le sha256 et la taille sont
 * calculés par le serveur : le formulaire n'envoie que le fichier et ses
 * métadonnées.
 */
export function ReleaseUploadModal({
    app,
    latest,
    maxPackageKilobytes,
    serverMaxUploadBytes,
    maxBuildNumber,
    onClose,
}: Props) {
    const fileInput = useRef<HTMLInputElement>(null);
    const form = useForm<UploadForm>({
        app: app.value,
        package: null,
        version: '',
        build_number: latest ? String(latest.build_number + 1) : '1',
        release_notes: '',
        is_mandatory: false,
    });

    const [serverError, setServerError] = useState<string | null>(null);
    const appMaxBytes = maxPackageKilobytes * 1024;
    // Au-delà de la limite PHP, la requête est rejetée avant Laravel (aucune
    // erreur de validation possible) : on bloque donc avant l'envoi.
    const limitedByServer =
        serverMaxUploadBytes !== null && serverMaxUploadBytes < appMaxBytes;
    const maxBytes = limitedByServer ? serverMaxUploadBytes : appMaxBytes;
    const tooLarge =
        form.data.package !== null && form.data.package.size > maxBytes;

    const close = () => {
        if (!form.processing) {
            onClose();
        }
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (tooLarge) {
            return;
        }

        setServerError(null);
        form.post(administration.releases.store.url(), {
            forceFormData: true,
            preserveScroll: true,
            // Réponse non-Inertia (413 du serveur web, 500, session
            // expirée…) : message dans la modale plutôt qu'une page brute.
            onHttpException: (response) => {
                setServerError(httpErrorMessage(response.status, maxBytes));

                return false;
            },
            onNetworkError: () => {
                setServerError(
                    'Connexion au serveur interrompue pendant le téléversement. Réessayez.',
                );

                return false;
            },
            onSuccess: () => {
                form.reset();
                onClose();
            },
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
                className="cash-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="release-upload-title"
                onSubmit={submit}
            >
                <div className="cash-modal-head">
                    <div className="flex items-start gap-3">
                        <span className="cash-opening-icon">
                            <Package className="size-5" />
                        </span>
                        <div>
                            <span className="section-kicker">
                                Distribution · {app.label}
                            </span>
                            <h3 id="release-upload-title">
                                Publier une nouvelle version
                            </h3>
                            <p>
                                Le package est publié immédiatement et proposé à
                                tous les terminaux de l’application.
                            </p>
                        </div>
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

                <div className="grid gap-4">
                    <label className="field">
                        <span>
                            Package Android (.apk){' '}
                            <b className="font-black text-destructive">*</b>
                        </span>
                        <button
                            type="button"
                            onClick={() => fileInput.current?.click()}
                            className="flex min-h-[72px] items-center gap-3 rounded-xl border border-dashed border-gold/50 bg-gold/5 px-4 py-3 text-left transition-colors hover:bg-gold/10"
                            aria-invalid={
                                form.errors.package || tooLarge
                                    ? 'true'
                                    : undefined
                            }
                        >
                            <FileUp className="size-5 shrink-0 text-gold" />
                            <span className="min-w-0 text-xs">
                                {form.data.package ? (
                                    <>
                                        <strong className="block truncate text-foreground">
                                            {form.data.package.name}
                                        </strong>
                                        <span className="text-muted-foreground">
                                            {formatBytes(
                                                form.data.package.size,
                                            )}
                                        </span>
                                    </>
                                ) : (
                                    <>
                                        <strong className="block text-foreground">
                                            Choisir le fichier APK
                                        </strong>
                                        <span className="text-muted-foreground">
                                            {formatBytes(maxBytes)} maximum ·
                                            l’empreinte SHA-256 est calculée par
                                            le serveur
                                        </span>
                                    </>
                                )}
                            </span>
                        </button>
                        <input
                            ref={fileInput}
                            type="file"
                            accept=".apk,application/vnd.android.package-archive"
                            className="sr-only"
                            onChange={(event) =>
                                form.setData(
                                    'package',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                        {tooLarge && (
                            <small className="field-error-text font-semibold text-destructive">
                                {limitedByServer
                                    ? `Le fichier dépasse la limite d’envoi du serveur (${formatBytes(maxBytes)}). Augmentez upload_max_filesize et post_max_size dans la configuration PHP.`
                                    : `Le fichier dépasse ${formatBytes(maxBytes)}.`}
                            </small>
                        )}
                        {form.errors.package && (
                            <small className="field-error-text font-semibold text-destructive">
                                {form.errors.package}
                            </small>
                        )}
                    </label>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="field">
                            <span>
                                Version (semver){' '}
                                <b className="font-black text-destructive">*</b>
                            </span>
                            <input
                                className="control font-mono"
                                placeholder={
                                    latest
                                        ? `ex. ${latest.version}`
                                        : 'ex. 1.0.0'
                                }
                                required
                                maxLength={64}
                                inputMode="decimal"
                                autoComplete="off"
                                value={form.data.version}
                                aria-invalid={
                                    form.errors.version ? 'true' : undefined
                                }
                                onChange={(event) =>
                                    form.setData('version', event.target.value)
                                }
                            />
                            {form.errors.version && (
                                <small className="field-error-text font-semibold text-destructive">
                                    {form.errors.version}
                                </small>
                            )}
                        </label>

                        <label className="field">
                            <span>
                                Build number (versionCode){' '}
                                <b className="font-black text-destructive">*</b>
                            </span>
                            <input
                                className="control font-mono"
                                type="number"
                                min={1}
                                max={maxBuildNumber}
                                required
                                value={form.data.build_number}
                                aria-invalid={
                                    form.errors.build_number
                                        ? 'true'
                                        : undefined
                                }
                                onChange={(event) =>
                                    form.setData(
                                        'build_number',
                                        event.target.value,
                                    )
                                }
                            />
                            {form.errors.build_number ? (
                                <small className="field-error-text font-semibold text-destructive">
                                    {form.errors.build_number}
                                </small>
                            ) : (
                                latest && (
                                    <small className="font-medium text-muted-foreground">
                                        Dernier publié : {latest.full_version}
                                    </small>
                                )
                            )}
                        </label>
                    </div>

                    <label className="field">
                        <span>Notes de version</span>
                        <textarea
                            className="control min-h-28 py-2 leading-5"
                            maxLength={5000}
                            placeholder="Nouveautés et correctifs, affichés aux convoyeurs avant la mise à jour."
                            value={form.data.release_notes}
                            onChange={(event) =>
                                form.setData(
                                    'release_notes',
                                    event.target.value,
                                )
                            }
                        />
                        {form.errors.release_notes && (
                            <small className="field-error-text font-semibold text-destructive">
                                {form.errors.release_notes}
                            </small>
                        )}
                    </label>

                    <label className="flex cursor-pointer items-start gap-3 rounded-xl bg-muted/45 p-3.5 ring-1 ring-black/[0.045]">
                        <input
                            type="checkbox"
                            className="mt-0.5 size-4 accent-[var(--destructive)]"
                            checked={form.data.is_mandatory}
                            onChange={(event) =>
                                form.setData(
                                    'is_mandatory',
                                    event.target.checked,
                                )
                            }
                        />
                        <span className="text-xs leading-5">
                            <strong className="flex items-center gap-1.5 text-foreground">
                                <ShieldAlert className="size-3.5 text-destructive" />
                                Mise à jour obligatoire
                            </strong>
                            <span className="text-muted-foreground">
                                Toute version installée antérieure sera bloquée
                                par un écran plein écran jusqu’à l’installation
                                de cette version.
                            </span>
                        </span>
                    </label>
                </div>

                {form.progress && (
                    <div className="mt-4" aria-live="polite">
                        <div className="mb-1.5 flex justify-between text-[0.6875rem] font-bold text-muted-foreground">
                            <span>Téléversement…</span>
                            <span>{form.progress.percentage ?? 0} %</span>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full bg-gold transition-[width]"
                                style={{
                                    width: `${form.progress.percentage ?? 0}%`,
                                }}
                            />
                        </div>
                    </div>
                )}

                {serverError && (
                    <div className="cash-form-error mt-4" role="alert">
                        {serverError}
                    </div>
                )}

                {form.errors.app && (
                    <div className="cash-form-error mt-4" role="alert">
                        {form.errors.app}
                    </div>
                )}

                <div className="cash-modal-actions">
                    <button
                        type="button"
                        onClick={close}
                        className="btn btn-ghost"
                        disabled={form.processing}
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        className="btn btn-primary gap-2 font-black"
                        disabled={
                            form.processing || !form.data.package || tooLarge
                        }
                    >
                        <Upload className="size-4" />
                        {form.processing ? 'Publication…' : 'Publier'}
                    </button>
                </div>
            </form>
        </div>
    );
}

function httpErrorMessage(status: number, maxBytes: number): string {
    switch (status) {
        case 413:
            return `Le serveur a refusé le fichier : il dépasse sa taille maximale d’envoi (${formatBytes(maxBytes)} annoncés). Vérifiez upload_max_filesize / post_max_size (PHP) et client_max_body_size (Nginx).`;
        case 419:
            return 'Votre session a expiré. Actualisez la page puis recommencez.';
        case 403:
            return 'Vous n’avez pas l’autorisation de publier une version.';
        default:
            return `Le serveur a répondu par une erreur (${status}). La version n’a pas été publiée.`;
    }
}
