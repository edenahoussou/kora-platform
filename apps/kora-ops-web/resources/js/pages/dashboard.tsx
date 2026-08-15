import { Head } from '@inertiajs/react';
import { dashboard } from '@/routes';

export default function Dashboard() {
    return (
        <>
            <Head title="Centre de pilotage" />

            <div className="kora-page-head mb-6 flex items-end justify-between gap-4">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA OPS / Centre de pilotage
                    </div>
                    <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Centre de pilotage
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] text-muted-foreground">
                        Les indicateurs essentiels de votre périmètre, actualisés
                        aujourd'hui.
                    </p>
                </div>
            </div>

            <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                {[1, 2, 3].map((i) => (
                    <div
                        key={i}
                        className="kora-panel relative aspect-video overflow-hidden rounded-[var(--kora-radius)] border border-border bg-card p-5 shadow-[0_7px_24px_rgba(0,29,78,0.04)]"
                    >
                        <div className="flex items-center justify-between text-[0.75rem] text-muted-foreground">
                            <span>Indicateur {i}</span>
                            <span className="grid size-8 place-items-center rounded-[0.625rem] bg-gold/20 text-[0.75rem] font-black text-[var(--kora)]">
                                ◇
                            </span>
                        </div>
                        <strong className="mt-3 block text-[1.5625rem] font-extrabold tracking-[-0.03em] text-foreground">
                            —
                        </strong>
                        <span className="mt-2 block text-[0.6875rem] font-bold text-digital">
                            En attente de données
                        </span>
                    </div>
                ))}
            </div>

            <div className="kora-panel mt-4 min-h-[40vh] rounded-[var(--kora-radius)] border border-border bg-card p-5 shadow-[0_8px_30px_rgba(0,29,78,0.045)]">
                <div className="mb-4 flex items-center justify-between">
                    <div>
                        <h2 className="text-[0.9375rem] font-bold text-foreground">
                            Activité sur les 7 derniers jours
                        </h2>
                        <p className="mt-1 text-[0.6875rem] text-muted-foreground">
                            Ventes et objectifs consolidés
                        </p>
                    </div>
                </div>
                <div className="flex h-[14.375rem] items-end gap-3 border-b border-border bg-[repeating-linear-gradient(to_top,transparent_0,transparent_54px,var(--border)_55px)]">
                    {['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'].map(
                        (day, i) => (
                            <div
                                key={day}
                                className="relative flex min-w-6 flex-1 items-end"
                            >
                                <div
                                    className="w-full rounded-t-[7px] bg-gradient-to-t from-[var(--kora)] to-digital opacity-20"
                                    style={{ height: `${[58, 73, 62, 84, 77, 91, 68][i]}%` }}
                                >
                                    <span className="absolute bottom-[-1.4375rem] left-1/2 -translate-x-1/2 text-[0.5625rem] text-muted-foreground">
                                        {day}
                                    </span>
                                </div>
                            </div>
                        ),
                    )}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
