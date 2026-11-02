import { Head, Link, useForm } from '@inertiajs/react';
import { FileText, Upload } from 'lucide-react';
import type { FormEvent } from 'react';
import {
    DateLabel,
    EmptyState,
    PartnerCard,
    PartnerPage,
    inputClass,
    labelClass,
} from '@/components/kora-points/partner/partner-ui';
import type {
    PartnerDocument,
    PartnerOrganization,
} from '@/types/kora-point-partner';

type DocumentForm = {
    kind: string;
    title: string;
    reference: string;
    signed_at: string;
    expires_at: string;
    file: File | null;
};

export default function PartnerDocumentsPage({
    organization,
    documents,
    upload_url,
}: {
    organization: PartnerOrganization;
    documents: PartnerDocument[];
    upload_url: string;
}) {
    const form = useForm<DocumentForm>({
        kind: 'agreement',
        title: '',
        reference: '',
        signed_at: '',
        expires_at: '',
        file: null,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(upload_url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title={`Documents — ${organization.name}`} />
            <PartnerPage
                title="Documents"
                description="Les documents sont déposés au niveau de l’organisation et partagés avec ses Points. Les suppressions sont gérées par KORA OPS."
                organizationName={organization.name}
            >
                <PartnerCard title="Ajouter un document">
                    <form
                        onSubmit={submit}
                        className="grid gap-4 p-5 sm:grid-cols-2"
                    >
                        <label className={labelClass}>
                            Type de document{' '}
                            <span className="text-destructive">*</span>
                            <select
                                className={inputClass}
                                value={form.data.kind}
                                onChange={(event) =>
                                    form.setData('kind', event.target.value)
                                }
                            >
                                <option value="agreement">
                                    Convention / contrat
                                </option>
                                <option value="identity">Identité</option>
                                <option value="address">
                                    Justificatif d’adresse
                                </option>
                                <option value="bank_details">
                                    Coordonnées bancaires
                                </option>
                                <option value="photo">Photo</option>
                                <option value="other">Autre</option>
                            </select>
                            {form.errors.kind ? (
                                <span className="mt-1 block text-xs text-destructive">
                                    {form.errors.kind}
                                </span>
                            ) : null}
                        </label>
                        <label className={labelClass}>
                            Nom du document{' '}
                            <span className="text-destructive">*</span>
                            <input
                                className={inputClass}
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                                required
                            />
                            {form.errors.title ? (
                                <span className="mt-1 block text-xs text-destructive">
                                    {form.errors.title}
                                </span>
                            ) : null}
                        </label>
                        <label className={labelClass}>
                            Référence
                            <input
                                className={inputClass}
                                value={form.data.reference}
                                onChange={(event) =>
                                    form.setData(
                                        'reference',
                                        event.target.value,
                                    )
                                }
                            />
                            {form.errors.reference ? (
                                <span className="mt-1 block text-xs text-destructive">
                                    {form.errors.reference}
                                </span>
                            ) : null}
                        </label>
                        <label className={labelClass}>
                            Fichier <span className="text-destructive">*</span>
                            <input
                                className={`${inputClass} file:mr-3 file:rounded file:border-0 file:bg-muted file:px-2 file:py-1`}
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                onChange={(event) =>
                                    form.setData(
                                        'file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                                required
                            />
                            <span className="mt-1 block text-xs font-normal text-muted-foreground">
                                PDF, JPG ou PNG · 10 Mo maximum.
                            </span>
                            {form.errors.file ? (
                                <span className="mt-1 block text-xs text-destructive">
                                    {form.errors.file}
                                </span>
                            ) : null}
                        </label>
                        <label className={labelClass}>
                            Date de signature
                            <input
                                className={inputClass}
                                type="date"
                                value={form.data.signed_at}
                                onChange={(event) =>
                                    form.setData(
                                        'signed_at',
                                        event.target.value,
                                    )
                                }
                            />
                            {form.errors.signed_at ? (
                                <span className="mt-1 block text-xs text-destructive">
                                    {form.errors.signed_at}
                                </span>
                            ) : null}
                        </label>
                        <label className={labelClass}>
                            Date d’expiration
                            <input
                                className={inputClass}
                                type="date"
                                value={form.data.expires_at}
                                onChange={(event) =>
                                    form.setData(
                                        'expires_at',
                                        event.target.value,
                                    )
                                }
                            />
                            {form.errors.expires_at ? (
                                <span className="mt-1 block text-xs text-destructive">
                                    {form.errors.expires_at}
                                </span>
                            ) : null}
                        </label>
                        <div className="flex items-end justify-end sm:col-span-2">
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:opacity-90 disabled:opacity-60"
                            >
                                <Upload className="size-4" />{' '}
                                {form.processing
                                    ? 'Envoi…'
                                    : 'Ajouter le document'}
                            </button>
                        </div>
                    </form>
                </PartnerCard>

                <PartnerCard title="Documents enregistrés">
                    {documents.length === 0 ? (
                        <EmptyState>Aucun document enregistré.</EmptyState>
                    ) : (
                        <ul className="divide-y divide-border">
                            {documents.map((document) => (
                                <li
                                    key={document.uuid}
                                    className="flex flex-wrap items-center justify-between gap-4 px-5 py-4"
                                >
                                    <div className="flex min-w-0 items-start gap-3">
                                        <FileText className="mt-0.5 size-5 shrink-0 text-primary" />
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold text-foreground">
                                                {document.title}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {document.kind}
                                                {document.reference
                                                    ? ` · Réf. ${document.reference}`
                                                    : ''}
                                            </p>
                                            {document.expires_at ? (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Expire le{' '}
                                                    <DateLabel
                                                        value={
                                                            document.expires_at
                                                        }
                                                    />
                                                </p>
                                            ) : null}
                                        </div>
                                    </div>
                                    <a
                                        href={document.download_url}
                                        className="rounded-md border border-input px-3 py-1.5 text-sm font-medium hover:bg-muted"
                                    >
                                        Télécharger
                                    </a>
                                </li>
                            ))}
                        </ul>
                    )}
                </PartnerCard>
                <p className="text-sm text-muted-foreground">
                    Les Points rattachés consultent ces documents sur leur fiche
                    et n’ajoutent pas de dossier séparé.{' '}
                    <Link
                        href="/kora-partner/organization"
                        className="font-semibold text-primary hover:underline"
                    >
                        Voir Mon organisation
                    </Link>
                </p>
            </PartnerPage>
        </>
    );
}
