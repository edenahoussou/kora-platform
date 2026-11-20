import { ArrowRight, CheckCircle2, Printer, RotateCcw } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { ParcelButton } from '@/components/parcels/parcel-button';

type HandoffProof = {
    kind: 'handoff_proof';
    outcome: 'withdrawn' | 'returned';
    reference: string;
    delivered_at: string | null;
    delivered_by: string | null;
    received_at: string | null;
    received_by: string | null;
    hub: { uuid: string; name: string | null; city: string | null };
    route: { origin: string | null; destination: string | null };
    parties: { sender_name: string; recipient_name: string };
    parcel: { weight_grams: number | null; contents: string | null };
    verification: {
        method: 'code' | 'identity';
        identity_type: string | null;
    };
    qr_payload: string;
    generated_at: string;
};

type Props = { proof: HandoffProof };

const identityLabels: Record<string, string> = {
    national_id: "Carte d'identité nationale",
    passport: 'Passeport',
    driving_licence: 'Permis de conduire',
};

function formatDate(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatGrams(value: number | null): string {
    return value === null
        ? '—'
        : `${new Intl.NumberFormat('fr-FR').format(value)} g`;
}

export default function ParcelHandoffProofPage({ proof }: Props) {
    const returned = proof.outcome === 'returned';

    return (
        <div className="parcel-proof-page min-h-screen bg-[#F3F4F7] p-4 text-[#122033] sm:p-8">
            <style>{`
                @media print {
                    @page { size: A4 portrait; margin: 14mm; }
                    body { background: #fff !important; visibility: hidden; }
                    .parcel-proof-page,
                    .parcel-proof-page * { visibility: visible; }
                    .parcel-proof-page {
                        position: absolute;
                        inset: 0 auto auto 0;
                        width: 100%;
                        padding: 0 !important;
                        background: #fff !important;
                    }
                    .parcel-proof-actions { display: none !important; }
                    .parcel-proof-sheet { border: 0 !important; box-shadow: none !important; }
                }
            `}</style>

            <div className="parcel-proof-actions mx-auto mb-3 flex max-w-[210mm] justify-end">
                <ParcelButton variant="navy" onClick={() => window.print()}>
                    <Printer className="size-4" />
                    Imprimer la preuve
                </ParcelButton>
            </div>

            <article className="parcel-proof-sheet mx-auto max-w-[210mm] border border-black/10 bg-white p-8 shadow-xl sm:p-11">
                <header className="flex items-start justify-between gap-6 border-b-2 border-[#001D4E] pb-5">
                    <div>
                        <div className="text-[0.65rem] font-black tracking-[0.22em] text-[#B48A26] uppercase">
                            KORA OPS · Colis
                        </div>
                        <h1 className="mt-1 text-2xl font-black text-[#001D4E]">
                            Preuve de remise
                        </h1>
                        <p className="mt-1 max-w-md text-xs leading-5 text-slate-500">
                            Attestation de sortie du colis sous contrôle KORA,
                            remise au destinataire autorisé ou retour à
                            l’expéditeur.
                        </p>
                    </div>
                    <div className="shrink-0 border-2 border-[#001D4E] px-3 py-2 text-right">
                        <span className="block text-[0.6rem] font-bold tracking-[0.16em] text-slate-500 uppercase">
                            Référence
                        </span>
                        <strong className="mt-1 block font-mono text-sm text-[#001D4E]">
                            {proof.reference}
                        </strong>
                    </div>
                </header>

                <div
                    className={`mt-6 flex items-center gap-3 border px-4 py-3 ${
                        returned
                            ? 'border-amber-300 bg-amber-50'
                            : 'border-emerald-300 bg-emerald-50'
                    }`}
                >
                    {returned ? (
                        <RotateCcw className="size-5 text-amber-700" />
                    ) : (
                        <CheckCircle2 className="size-5 text-emerald-700" />
                    )}
                    <div>
                        <strong className="block text-sm text-[#122033]">
                            {returned
                                ? 'Colis retourné à l’expéditeur'
                                : 'Colis remis au destinataire'}
                        </strong>
                        <span className="text-xs text-slate-600">
                            Remise enregistrée le{' '}
                            {formatDate(proof.delivered_at)}
                        </span>
                    </div>
                </div>

                <div className="mt-6 grid gap-6 sm:grid-cols-[1fr_145px]">
                    <div className="grid gap-4">
                        <section>
                            <h2 className="text-[0.65rem] font-black tracking-[0.16em] text-slate-500 uppercase">
                                Trajet
                            </h2>
                            <p className="mt-2 flex items-center gap-2 text-lg font-black text-[#001D4E]">
                                {proof.route.origin ?? '—'}
                                <ArrowRight className="size-4 text-[#B48A26]" />
                                {proof.route.destination ?? '—'}
                            </p>
                            <div className="mt-2 grid grid-cols-2 gap-4 text-xs">
                                <div>
                                    <span className="block text-slate-500">
                                        Poids
                                    </span>
                                    <strong>
                                        {formatGrams(proof.parcel.weight_grams)}
                                    </strong>
                                </div>
                                <div>
                                    <span className="block text-slate-500">
                                        Lieu de remise
                                    </span>
                                    <strong>
                                        {proof.hub.name ??
                                            proof.hub.city ??
                                            '—'}
                                    </strong>
                                </div>
                            </div>
                        </section>

                        <section className="grid gap-2 border-t border-black/10 pt-4 text-sm">
                            <ProofLine
                                label="Expéditeur"
                                value={proof.parties.sender_name}
                            />
                            <ProofLine
                                label="Destinataire"
                                value={proof.parties.recipient_name}
                            />
                            <ProofLine
                                label="Contenu déclaré"
                                value={proof.parcel.contents ?? '—'}
                            />
                            <ProofLine
                                label="Réception physique"
                                value={`${formatDate(proof.received_at)} · ${proof.received_by ?? '—'}`}
                            />
                            <ProofLine
                                label="Remise par"
                                value={proof.delivered_by ?? '—'}
                            />
                            <ProofLine
                                label="Vérification d’identité"
                                value={
                                    proof.verification.method === 'identity'
                                        ? (identityLabels[
                                              proof.verification
                                                  .identity_type ?? ''
                                          ] ?? 'Pièce d’identité')
                                        : 'Code de retrait à usage unique'
                                }
                            />
                        </section>
                    </div>

                    <div className="grid content-start justify-items-center border border-black/10 bg-slate-50 p-3">
                        <QRCodeSVG
                            value={proof.qr_payload}
                            size={120}
                            bgColor="#ffffff"
                            fgColor="#001D4E"
                            level="M"
                            includeMargin
                        />
                        <p className="mt-2 text-center text-[0.6rem] leading-4 font-bold text-slate-500 uppercase">
                            Vérification publique KORA
                        </p>
                    </div>
                </div>

                <div className="mt-8 grid grid-cols-2 gap-8 border-t border-black/10 pt-6 text-xs">
                    <div>
                        <div className="h-16 border-b border-dashed border-slate-400" />
                        <p className="mt-1 font-bold text-slate-700">
                            Signature du remettant
                        </p>
                    </div>
                    <div>
                        <div className="h-16 border-b border-dashed border-slate-400" />
                        <p className="mt-1 font-bold text-slate-700">
                            Signature du destinataire
                        </p>
                    </div>
                </div>

                <footer className="mt-8 flex items-end justify-between gap-4 border-t border-black/10 pt-4 text-[0.65rem] text-slate-500">
                    <p className="max-w-lg leading-5">
                        Document généré par KORA OPS. Le code de retrait n’est
                        ni reproduit ni conservé sur cette preuve. Toute
                        contestation doit être adressée à l’agence émettrice
                        avec la référence du colis.
                    </p>
                    <span className="shrink-0 text-right">
                        Généré le {formatDate(proof.generated_at)}
                    </span>
                </footer>
            </article>
        </div>
    );
}

function ProofLine({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-start justify-between gap-4 border-b border-black/5 pb-1.5">
            <span className="text-slate-500">{label}</span>
            <strong className="max-w-[60%] text-right text-[#122033]">
                {value}
            </strong>
        </div>
    );
}
