import { useForm } from '@inertiajs/react';
import { Check, PencilLine, ShieldAlert, X } from 'lucide-react';
import type { AppRelease } from '@/components/app-releases/shared';
import administration from '@/routes/administration';

type Props = {
    release: AppRelease;
    onClose: () => void;
};

/**
 * Édition des métadonnées d'une release : notes et flag obligatoire. Le
 * binaire, la version et le build sont immuables (le sha256 annoncé aux
 * appareils ne doit jamais changer).
 */
export function ReleaseEditModal({ release, onClose }: Props) {
    const form = useForm({
        release_notes: release.release_notes ?? '',
        is_mandatory: release.is_mandatory,
    });

    const close = () => {
        if (!form.processing) {
            onClose();
        }
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.patch(administration.releases.update.url(release.uuid), {
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
                aria-labelledby="release-edit-title"
                onSubmit={submit}
            >
                <div className="cash-modal-head">
                    <div className="flex items-start gap-3">
                        <span className="cash-opening-icon">
                            <PencilLine className="size-5" />
                        </span>
                        <div>
                            <span className="section-kicker">
                                Version {release.full_version}
                            </span>
                            <h3 id="release-edit-title">Modifier la version</h3>
                            <p>
                                Le package, la version et le build ne sont pas
                                modifiables : publiez une nouvelle version pour
                                corriger un APK.
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
                        <span>Notes de version</span>
                        <textarea
                            className="control min-h-28 py-2 leading-5"
                            maxLength={5000}
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
                                Les terminaux en version antérieure seront
                                bloqués dès leur prochain échange avec le
                                serveur.
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
                        disabled={form.processing || !form.isDirty}
                    >
                        <Check className="size-4" />
                        {form.processing ? 'Enregistrement…' : 'Enregistrer'}
                    </button>
                </div>
            </form>
        </div>
    );
}
