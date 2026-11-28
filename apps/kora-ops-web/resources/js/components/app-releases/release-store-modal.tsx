import { useForm } from '@inertiajs/react';
import { Globe, X } from 'lucide-react';
import type { AppRelease } from '@/components/app-releases/shared';

type Props = {
    latest: AppRelease | null;
    maxBuildNumber: number;
    onClose: () => void;
};

type StoreReleaseForm = {
    platform: 'android' | 'ios';
    environment: 'staging' | 'production';
    store_url: string;
    version: string;
    build_number: string;
    release_notes: string;
    is_mandatory: boolean;
};

/**
 * Création d'une release store Passager (#342). Aucun binaire n'est envoyé :
 * l'URL publique du store est référencée, puis confirmée Ops avant exposition.
 */
export function ReleaseStoreModal({ latest, maxBuildNumber, onClose }: Props) {
    const form = useForm<StoreReleaseForm>({
        platform: 'android',
        environment: 'production',
        store_url: '',
        version: '',
        build_number: latest ? String(latest.build_number + 1) : '1',
        release_notes: '',
        is_mandatory: false,
    });

    const close = () => {
        if (!form.processing) {
            onClose();
        }
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.post('/administration/releases/store', {
            preserveScroll: true,
            onSuccess: onClose,
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
                aria-labelledby="release-store-title"
                onSubmit={submit}
            >
                <div className="cash-modal-head">
                    <div className="flex items-start gap-3">
                        <span className="cash-opening-icon">
                            <Globe className="size-5" />
                        </span>
                        <div>
                            <span className="section-kicker">
                                Distribution · Store
                            </span>
                            <h3 id="release-store-title">
                                Ajouter une release store
                            </h3>
                            <p>
                                Référencez la version publiée sur Google Play ou
                                l’App Store. Elle reste invisible tant que la
                                confirmation Ops n’est pas enregistrée.
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
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="field">
                            <span>Plateforme</span>
                            <select
                                className="control"
                                value={form.data.platform}
                                onChange={(event) =>
                                    form.setData(
                                        'platform',
                                        event.target.value as 'android' | 'ios',
                                    )
                                }
                            >
                                <option value="android">Android</option>
                                <option value="ios">iOS</option>
                            </select>
                        </label>

                        <label className="field">
                            <span>Environnement</span>
                            <select
                                className="control"
                                value={form.data.environment}
                                onChange={(event) =>
                                    form.setData(
                                        'environment',
                                        event.target.value as
                                            'staging' | 'production',
                                    )
                                }
                            >
                                <option value="production">Production</option>
                                <option value="staging">Staging</option>
                            </select>
                        </label>
                    </div>

                    <label className="field">
                        <span>
                            Lien du store{' '}
                            <b className="font-black text-destructive">*</b>
                        </span>
                        <input
                            className="control font-mono"
                            type="url"
                            required
                            maxLength={2048}
                            placeholder="https://play.google.com/store/apps/details?id=…"
                            value={form.data.store_url}
                            aria-invalid={
                                form.errors.store_url ? 'true' : undefined
                            }
                            onChange={(event) =>
                                form.setData('store_url', event.target.value)
                            }
                        />
                        {form.errors.store_url && (
                            <small className="field-error-text font-semibold text-destructive">
                                {form.errors.store_url}
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
                                required
                                maxLength={64}
                                inputMode="decimal"
                                autoComplete="off"
                                placeholder="ex. 1.0.0"
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
                                Build number{' '}
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
                            {form.errors.build_number && (
                                <small className="field-error-text font-semibold text-destructive">
                                    {form.errors.build_number}
                                </small>
                            )}
                        </label>
                    </div>

                    <label className="field">
                        <span>Notes de version</span>
                        <textarea
                            className="control min-h-28 py-2 leading-5"
                            maxLength={5000}
                            placeholder="Nouveautés et correctifs affichés aux voyageurs."
                            value={form.data.release_notes}
                            onChange={(event) =>
                                form.setData(
                                    'release_notes',
                                    event.target.value,
                                )
                            }
                        />
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
                            <strong className="text-foreground">
                                Mise à jour obligatoire
                            </strong>
                            <span className="text-muted-foreground">
                                Toute installation antérieure sera bloquée
                                jusqu’à cette version.
                            </span>
                        </span>
                    </label>
                </div>

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
                        disabled={form.processing}
                    >
                        {form.processing ? 'Enregistrement…' : 'Enregistrer'}
                    </button>
                </div>
            </form>
        </div>
    );
}
