import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, FileText, Upload } from 'lucide-react';
import type { FormEvent } from 'react';
import {
    DateLabel,
    EmptyState,
    Money,
    PartnerCard,
    PartnerPage,
    tableCellClass,
    tableHeadClass,
} from '@/components/kora-points/partner/partner-ui';

type Remittance = {
    uuid: string;
    status: string;
    amount: number;
    received_amount: number | null;
    method: string;
    reference: string;
    note: string | null;
    submitted_at: string | null;
    validated_at: string | null;
};
type RemittanceLine = {
    sale_uuid: string;
    point_uuid: string;
    point_name: string;
    point_code: string;
    amount: number;
};
type ProofForm = {
    kind: string;
    title: string;
    reference: string;
    file: File | null;
};

const statusLabel: Record<string, string> = {
    draft: 'Brouillon',
    submitted: 'Soumis à KORA',
    reconciliation: 'À rapprocher',
    validated: 'Validé',
    rejected: 'Rejeté',
};

export default function PartnerRemittanceShowPage({
    organization_name,
    remittance,
    lines,
    has_proof,
    can_submit,
}: {
    organization_name: string;
    remittance: Remittance;
    lines: RemittanceLine[];
    has_proof: boolean;
    can_submit: boolean;
}) {
    const form = useForm<ProofForm>({
        kind: 'remittance_proof',
        title: 'Preuve de reversement',
        reference: '',
        file: null,
    });
    const uploadProof = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/kora-points/remittances/${remittance.uuid}/proof`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title={`Bordereau — ${organization_name}`} />
            <PartnerPage
                title="Bordereau de reversement"
                description="Suivez la déclaration groupée et les ventes qu’elle couvre."
                organizationName={organization_name}
            >
                <Link
                    href="/kora-points/remittances"
                    className="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> Retour aux reversements
                </Link>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <PartnerCard title="Référence">
                        <p className="px-5 pb-5 text-sm font-semibold">
                            {remittance.reference}
                        </p>
                    </PartnerCard>
                    <PartnerCard title="Statut">
                        <p className="px-5 pb-5 text-sm font-semibold">
                            {statusLabel[remittance.status] ??
                                remittance.status}
                        </p>
                    </PartnerCard>
                    <PartnerCard title="Montant déclaré">
                        <p className="px-5 pb-5 text-sm font-semibold">
                            <Money amount={remittance.amount} />
                        </p>
                    </PartnerCard>
                    <PartnerCard title="Montant reçu">
                        <p className="px-5 pb-5 text-sm font-semibold">
                            {remittance.received_amount === null ? (
                                'En attente'
                            ) : (
                                <Money amount={remittance.received_amount} />
                            )}
                        </p>
                    </PartnerCard>
                </div>

                <PartnerCard title="Ventes incluses">
                    {lines.length === 0 ? (
                        <EmptyState>
                            Aucune vente liée à ce bordereau.
                        </EmptyState>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[620px] border-collapse">
                                <thead className="bg-muted/40">
                                    <tr>
                                        <th className={tableHeadClass}>
                                            Point
                                        </th>
                                        <th className={tableHeadClass}>
                                            Vente
                                        </th>
                                        <th className={tableHeadClass}>
                                            Montant affecté
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {lines.map((line) => (
                                        <tr key={line.sale_uuid}>
                                            <td className={tableCellClass}>
                                                {line.point_name}
                                                <span className="ml-1 text-xs text-muted-foreground">
                                                    ({line.point_code})
                                                </span>
                                            </td>
                                            <td
                                                className={`${tableCellClass} font-mono text-xs`}
                                            >
                                                {line.sale_uuid
                                                    .slice(0, 8)
                                                    .toUpperCase()}
                                            </td>
                                            <td className={tableCellClass}>
                                                <Money amount={line.amount} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </PartnerCard>

                {can_submit ? (
                    <PartnerCard title="Justificatif de paiement">
                        {has_proof ? (
                            <div className="flex flex-wrap items-center justify-between gap-3 p-5">
                                <p className="flex items-center gap-2 text-sm font-medium">
                                    <FileText className="size-4 text-primary" />{' '}
                                    Justificatif enregistré
                                </p>
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.post(
                                            `/kora-points/remittances/${remittance.uuid}/submit`,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                    className="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:opacity-90"
                                >
                                    Soumettre à Finance KORA
                                </button>
                            </div>
                        ) : (
                            <form
                                onSubmit={uploadProof}
                                className="grid gap-4 p-5 sm:grid-cols-2"
                            >
                                <label className="block text-sm font-medium">
                                    Fichier justificatif{' '}
                                    <span className="text-destructive">*</span>
                                    <input
                                        className="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
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
                                    {form.errors.file ? (
                                        <span className="mt-1 block text-xs text-destructive">
                                            {form.errors.file}
                                        </span>
                                    ) : null}
                                </label>
                                <label className="block text-sm font-medium">
                                    Référence complémentaire
                                    <input
                                        className="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
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
                                <p className="text-xs text-muted-foreground sm:col-span-2">
                                    Le bordereau ne sera transmis à KORA
                                    qu’après ajout d’une preuve de paiement.
                                </p>
                                <div className="flex justify-end sm:col-span-2">
                                    <button
                                        type="submit"
                                        disabled={form.processing}
                                        className="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                                    >
                                        <Upload className="size-4" />
                                        {form.processing
                                            ? 'Envoi…'
                                            : 'Ajouter la preuve'}
                                    </button>
                                </div>
                            </form>
                        )}
                    </PartnerCard>
                ) : null}

                {remittance.note ? (
                    <PartnerCard title="Note de rapprochement">
                        <p className="p-5 text-sm">{remittance.note}</p>
                    </PartnerCard>
                ) : null}
                <p className="text-xs text-muted-foreground">
                    Déclaré le <DateLabel value={remittance.submitted_at} />
                    {remittance.validated_at ? (
                        <>
                            {' '}
                            · Contrôlé le{' '}
                            <DateLabel value={remittance.validated_at} />
                        </>
                    ) : null}
                </p>
            </PartnerPage>
        </>
    );
}
