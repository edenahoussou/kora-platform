import { Head } from '@inertiajs/react';
import { Printer, QrCode } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useMemo } from 'react';
import { ParcelButton } from '@/components/parcels/parcel-button';
import { parcelStatusLabel } from '@/components/parcels/status';

type ManifestParcel = {
    sequence: number;
    reference: string | null;
    weight_grams: number | null;
    status: string | null;
    origin_stop_uuid: string | null;
    origin_stop: string | null;
    destination_stop_uuid: string | null;
    destination_stop: string | null;
    load_stop_uuid: string | null;
    load_stop: string | null;
    loaded_at: string | null;
    qr_payload: string | null;
};

type Manifest = {
    version: string;
    generated_at: string;
    departure: {
        uuid: string;
        line_code: string | null;
        line_name: string | null;
        bus_registration: string | null;
        departure_date: string;
        departure_time: string;
        status: string;
        origin_stop: string | null;
        destination_stop: string | null;
    };
    stops: Array<{
        sequence: number;
        uuid: string;
        name: string;
        city: string | null;
    }>;
    summary: {
        parcels: number;
        weight_grams: number;
        loaded: number;
    };
    parcels: ManifestParcel[];
};

type Props = {
    manifest: Manifest;
};

function formatGrams(value: number | null): string {
    if (value === null) {
        return '—';
    }

    return `${new Intl.NumberFormat('fr-FR').format(value)} g`;
}

function formatDate(value: string): string {
    const parsed = new Date(`${value}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'long',
    }).format(parsed);
}

export default function ParcelManifestPage({ manifest }: Props) {
    const groups = useMemo(() => {
        const map = new Map<
            string,
            { stop: string; stopUuid: string; parcels: ManifestParcel[] }
        >();

        manifest.parcels.forEach((parcel) => {
            const key = parcel.load_stop_uuid ?? 'unknown';
            const group = map.get(key) ?? {
                stop: parcel.load_stop ?? 'Arrêt non renseigné',
                stopUuid: key,
                parcels: [],
            };
            group.parcels.push(parcel);
            map.set(key, group);
        });

        return Array.from(map.values());
    }, [manifest.parcels]);

    return (
        <>
            <Head
                title={`Manifeste soute ${manifest.departure.line_code ?? ''}`}
            />
            <div className="cash-page parcel-manifest-page min-h-screen bg-white p-6 text-foreground print:p-0">
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between print:mb-3">
                    <div>
                        <div className="kora-breadcrumb mb-2 flex items-center gap-1.5 text-[0.6875rem] text-muted-foreground">
                            <span>KORA OPS</span>
                            <span>/</span>
                            <span>Colis</span>
                            <span>/</span>
                            <span className="font-bold text-foreground">
                                Manifeste de soute
                            </span>
                        </div>
                        <h1 className="text-2xl font-extrabold tracking-[-0.04em] text-foreground print:text-xl">
                            Manifeste de soute
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {manifest.departure.line_code ?? '—'} ·{' '}
                            {manifest.departure.origin_stop ?? '—'} →{' '}
                            {manifest.departure.destination_stop ?? '—'} ·{' '}
                            {formatDate(manifest.departure.departure_date)} à{' '}
                            {manifest.departure.departure_time}
                        </p>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            Bus {manifest.departure.bus_registration ?? '—'} ·
                            Version {manifest.version.slice(0, 12)}
                        </p>
                    </div>
                    <div className="flex items-center gap-2 print:hidden">
                        <ParcelButton
                            variant="primary"
                            onClick={() => window.print()}
                        >
                            <Printer className="size-3.5" />
                            Imprimer
                        </ParcelButton>
                        <ParcelButton
                            variant="ghost"
                            onClick={() => window.close()}
                        >
                            Fermer
                        </ParcelButton>
                    </div>
                </div>

                <div className="mb-4 grid grid-cols-3 gap-3 print:mb-3">
                    <div className="rounded-xl border border-black/[0.08] px-4 py-3">
                        <small className="block text-[0.625rem] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                            Colis
                        </small>
                        <strong className="mt-1 block text-lg font-black text-foreground">
                            {manifest.summary.parcels}
                        </strong>
                    </div>
                    <div className="rounded-xl border border-black/[0.08] px-4 py-3">
                        <small className="block text-[0.625rem] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                            Poids total
                        </small>
                        <strong className="mt-1 block text-lg font-black text-foreground">
                            {formatGrams(manifest.summary.weight_grams)}
                        </strong>
                    </div>
                    <div className="rounded-xl border border-black/[0.08] px-4 py-3">
                        <small className="block text-[0.625rem] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                            Chargés
                        </small>
                        <strong className="mt-1 block text-lg font-black text-foreground">
                            {manifest.summary.loaded}/{manifest.summary.parcels}
                        </strong>
                    </div>
                </div>

                {manifest.parcels.length === 0 ? (
                    <div className="rounded-xl border border-black/[0.08] px-4 py-8 text-center text-sm text-muted-foreground">
                        Aucun colis affecté à ce départ.
                    </div>
                ) : (
                    <div className="space-y-5">
                        {groups.map((group) => (
                            <section key={group.stopUuid}>
                                <div className="mb-2 flex items-baseline justify-between gap-3 border-b border-black/10 pb-1">
                                    <h2 className="text-sm font-black tracking-[0.06em] text-foreground uppercase">
                                        Arrêt · {group.stop}
                                    </h2>
                                    <span className="text-xs text-muted-foreground">
                                        {group.parcels.length} colis ·{' '}
                                        {formatGrams(
                                            group.parcels.reduce(
                                                (sum, parcel) =>
                                                    sum +
                                                    (parcel.weight_grams ?? 0),
                                                0,
                                            ),
                                        )}
                                    </span>
                                </div>
                                <table className="w-full table-fixed border-collapse text-left text-[0.6875rem]">
                                    <thead>
                                        <tr className="border-b border-black/10 text-[0.5625rem] font-black tracking-[0.1em] text-muted-foreground uppercase">
                                            <th className="w-[5%] py-2">#</th>
                                            <th className="w-[9%] py-2">QR</th>
                                            <th className="w-[13%] py-2">
                                                Référence
                                            </th>
                                            <th className="w-[15%] py-2">
                                                Trajet
                                            </th>
                                            <th className="w-[10%] py-2">
                                                Poids
                                            </th>
                                            <th className="w-[14%] py-2">
                                                Statut
                                            </th>
                                            <th className="w-[12%] py-2">
                                                Chargé le
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {group.parcels.map((parcel) => (
                                            <tr
                                                key={parcel.reference}
                                                className="border-b border-black/[0.06] align-top"
                                            >
                                                <td className="py-2 font-mono">
                                                    {parcel.sequence}
                                                </td>
                                                <td className="py-2">
                                                    {parcel.qr_payload ? (
                                                        <QRCodeSVG
                                                            value={
                                                                parcel.qr_payload
                                                            }
                                                            size={42}
                                                            level="M"
                                                        />
                                                    ) : (
                                                        <QrCode className="size-6 text-muted-foreground" />
                                                    )}
                                                </td>
                                                <td className="py-2 font-mono font-bold">
                                                    {parcel.reference ?? '—'}
                                                </td>
                                                <td className="py-2">
                                                    {parcel.origin_stop ?? '—'}{' '}
                                                    →{' '}
                                                    {parcel.destination_stop ??
                                                        '—'}
                                                </td>
                                                <td className="py-2 font-bold">
                                                    {formatGrams(
                                                        parcel.weight_grams,
                                                    )}
                                                </td>
                                                <td className="py-2">
                                                    {parcel.status
                                                        ? parcelStatusLabel(
                                                              parcel.status,
                                                          )
                                                        : '—'}
                                                    {parcel.loaded_at !==
                                                        null && (
                                                        <span className="ml-1 inline-block rounded bg-emerald-100 px-1 py-0.5 text-[0.5625rem] font-bold text-emerald-800">
                                                            chargé
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-2 text-[0.625rem]">
                                                    {parcel.loaded_at === null
                                                        ? '—'
                                                        : new Intl.DateTimeFormat(
                                                              'fr-FR',
                                                              {
                                                                  dateStyle:
                                                                      'short',
                                                                  timeStyle:
                                                                      'short',
                                                              },
                                                          ).format(
                                                              new Date(
                                                                  parcel.loaded_at,
                                                              ),
                                                          )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </section>
                        ))}
                    </div>
                )}

                <div className="mt-6 border-t border-black/10 pt-3 text-[0.625rem] text-muted-foreground print:mt-4">
                    Document généré le{' '}
                    {new Intl.DateTimeFormat('fr-FR', {
                        dateStyle: 'short',
                        timeStyle: 'short',
                    }).format(new Date(manifest.generated_at))}{' '}
                    · Les QR renvoient au suivi public des colis. Aucun
                    identifiant interne n’est imprimé.
                </div>
            </div>
            <style>{`
                @media print {
                    @page { size: A4 landscape; margin: 10mm; }
                    body { background: #fff !important; visibility: hidden; }
                    .parcel-manifest-page,
                    .parcel-manifest-page * { visibility: visible; }
                    .parcel-manifest-page {
                        position: absolute;
                        inset: 0 auto auto 0;
                        width: 100%;
                        padding: 0 !important;
                    }
                    .print\\:hidden { display: none !important; }
                    table { page-break-inside: auto; }
                    tr { page-break-inside: avoid; page-break-after: auto; }
                    section { page-break-inside: avoid; }
                }
            `}</style>
        </>
    );
}
