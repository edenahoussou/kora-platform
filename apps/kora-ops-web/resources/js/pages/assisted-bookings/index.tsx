import { Head, Link, router } from '@inertiajs/react';
import { Clock3, HandHelping, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index, show } from '@/routes/assisted-bookings';

type QueueItem = {
    uuid: string;
    status: string;
    reference: string;
    contactName: string;
    contactPhone: string;
    agency: { uuid: string; name: string };
    assignee: { uuid: string; name: string } | null;
    originalAmount: number;
    currentAmount: number | null;
    currency: string;
    expiresAt: string;
};

type Props = {
    assistedBookings: {
        data: QueueItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { q?: string; status?: string; agency?: string; date?: string };
    statuses: Record<string, string>;
    agencies: { uuid: string; name: string; city: string }[];
};

const labels: Record<string, string> = {
    pending: 'En attente',
    in_progress: 'En cours',
    cancelled: 'Reprise en ligne',
    refused: 'Refusée',
    unavailable: 'Indisponible',
    expired: 'Expirée',
    finalized: 'Finalisée',
};

function money(amount: number, currency: string): string {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(amount);
}

export default function AssistedBookingsIndex({
    assistedBookings,
    filters,
    statuses,
    agencies,
}: Props) {
    const [query, setQuery] = useState(filters.q ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [agency, setAgency] = useState(filters.agency ?? '');
    const [date, setDate] = useState(filters.date ?? '');

    const filter = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            index.url(),
            { q: query, status, agency, date },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Réservations assistées" />
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="mb-2 text-[0.6875rem] font-bold tracking-[0.11em] text-muted-foreground uppercase">
                        KORA OPS / Vente / Assistance
                    </div>
                    <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em]">
                        Réservations assistées
                    </h1>
                    <p className="mt-1.5 text-sm text-muted-foreground">
                        Reprenez les réservations mobiles confiées au réseau
                        d’agences.
                    </p>
                </div>
                <div className="rounded-xl bg-gold/15 px-4 py-2 text-sm font-bold text-[var(--kora)]">
                    {assistedBookings.total} dossier
                    {assistedBookings.total > 1 ? 's' : ''}
                </div>
            </div>

            <form
                onSubmit={filter}
                className="mb-5 grid gap-3 rounded-2xl border border-border/70 bg-card p-4 shadow-sm md:grid-cols-[minmax(14rem,1fr)_12rem_14rem_11rem_auto]"
            >
                <div className="relative">
                    <Search className="absolute top-3.5 left-3 size-4 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Référence, contact…"
                        className="h-11 pl-9"
                    />
                </div>
                <select
                    value={status}
                    onChange={(event) => setStatus(event.target.value)}
                    className="h-11 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">Tous les états</option>
                    {Object.keys(statuses).map((value) => (
                        <option key={value} value={value}>
                            {labels[value] ?? value}
                        </option>
                    ))}
                </select>
                <select
                    value={agency}
                    onChange={(event) => setAgency(event.target.value)}
                    className="h-11 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">Toutes les agences</option>
                    {agencies.map((item) => (
                        <option key={item.uuid} value={item.uuid}>
                            {item.name} — {item.city}
                        </option>
                    ))}
                </select>
                <Input
                    type="date"
                    value={date}
                    onChange={(event) => setDate(event.target.value)}
                    className="h-11"
                />
                <Button type="submit" className="h-11 bg-[var(--kora)]">
                    Filtrer
                </Button>
            </form>

            <div className="overflow-hidden rounded-2xl border border-border/70 bg-card shadow-sm">
                <div className="flex items-center gap-3 border-b p-5">
                    <span className="grid size-10 place-items-center rounded-xl bg-gold/15 text-[var(--kora)]">
                        <HandHelping className="size-5" />
                    </span>
                    <div>
                        <h2 className="font-bold">File d’assistance</h2>
                        <p className="text-xs text-muted-foreground">
                            Un dossier n’occupe aucun siège tant qu’il reste
                            dans cette file.
                        </p>
                    </div>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/40 text-left text-xs tracking-wide text-muted-foreground uppercase">
                            <tr>
                                <th className="p-4">Réservation</th>
                                <th className="p-4">Contact</th>
                                <th className="p-4">Agence</th>
                                <th className="p-4">État</th>
                                <th className="p-4">Montant</th>
                                <th className="p-4">Validité</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {assistedBookings.data.map((item) => (
                                <tr
                                    key={item.uuid}
                                    className="transition-colors hover:bg-muted/35"
                                >
                                    <td className="p-4">
                                        <Link
                                            href={show.url(item.uuid)}
                                            className="font-extrabold text-[var(--kora)] hover:underline"
                                        >
                                            {item.reference}
                                        </Link>
                                        <span className="mt-1 block text-xs text-muted-foreground">
                                            {item.assignee?.name ??
                                                'Non attribuée'}
                                        </span>
                                    </td>
                                    <td className="p-4">
                                        <strong className="block">
                                            {item.contactName}
                                        </strong>
                                        <span className="text-xs text-muted-foreground">
                                            {item.contactPhone}
                                        </span>
                                    </td>
                                    <td className="p-4">{item.agency.name}</td>
                                    <td className="p-4">
                                        <span className="rounded-full bg-muted px-2.5 py-1 text-xs font-bold">
                                            {labels[item.status] ?? item.status}
                                        </span>
                                    </td>
                                    <td className="p-4 font-semibold">
                                        {money(
                                            item.currentAmount ??
                                                item.originalAmount,
                                            item.currency,
                                        )}
                                    </td>
                                    <td className="p-4 text-xs text-muted-foreground">
                                        <span className="flex items-center gap-1">
                                            <Clock3 className="size-3.5" />
                                            {new Date(
                                                item.expiresAt,
                                            ).toLocaleString('fr-FR')}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                            {assistedBookings.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="p-12 text-center text-muted-foreground"
                                    >
                                        Aucun dossier ne correspond aux filtres.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                {assistedBookings.last_page > 1 && (
                    <div className="flex flex-wrap gap-2 border-t p-4">
                        {assistedBookings.links.map(
                            (link, i) =>
                                link.url && (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        className={`rounded-lg px-3 py-1.5 text-xs ${link.active ? 'bg-[var(--kora)] text-white' : 'bg-muted'}`}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ),
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
