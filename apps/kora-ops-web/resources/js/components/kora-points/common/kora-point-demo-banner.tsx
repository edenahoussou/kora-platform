import { HelpCircle, RotateCcw, Sparkles } from 'lucide-react';
import type { DemoScenarioId, KoraPointUserRole } from '../mock/kora-point-mock-data';

type Props = {
    currentScenario: DemoScenarioId;
    onScenarioChange: (scenario: DemoScenarioId) => void;
    currentRole?: KoraPointUserRole;
    onRoleChange?: (role: KoraPointUserRole) => void;
    onReset: () => void;
    onRestartTour?: () => void;
};

const SCENARIOS: { id: DemoScenarioId; label: string; desc: string }[] = [
    {
        id: 'autonomous_healthy',
        label: 'Point autonome — Normal (Sain)',
        desc: '64 % du plafond · Prêt à vendre · Échéance lundi',
    },
    {
        id: 'autonomous_warning_80',
        label: 'Point autonome — Seuil 80 % (Attention)',
        desc: '86 % du plafond · Ventes autorisées · Alerte active',
    },
    {
        id: 'autonomous_limit_reached',
        label: 'Point autonome — Plafond 100 % (Bloqué)',
        desc: '100 % du plafond atteint · Ventes bloquées · Reversement requis',
    },
    {
        id: 'autonomous_overdue',
        label: 'Point autonome — Échéance en retard (Suspendu)',
        desc: 'Grâce dépassée · Retard de 5 jours · Ventes bloquées',
    },
    {
        id: 'org_attached_healthy',
        label: 'Organisation — La Poste (Rattaché)',
        desc: 'Bureau de poste · Reversement géré par le trésorier',
    },
];

export function KoraPointDemoBanner({
    currentScenario,
    onScenarioChange,
    currentRole = 'AGENT_POINT',
    onRoleChange,
    onReset,
    onRestartTour,
}: Props) {
    return (
        <aside
            aria-label="Mode Démonstration KORA POINT VOYAGE"
            className="sticky top-0 z-40 border-b border-amber-300/40 bg-gradient-to-r from-amber-500/15 via-amber-400/20 to-amber-500/15 px-4 py-2 text-amber-950 backdrop-blur-md dark:border-amber-400/30 dark:from-amber-950/40 dark:via-amber-900/30 dark:to-amber-950/40 dark:text-amber-200"
        >
            <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 text-xs">
                {/* Left: Indicator */}
                <div className="flex items-center gap-2">
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-500/25 px-2.5 py-0.5 font-black uppercase tracking-wider text-[0.625rem] text-amber-900 dark:bg-amber-400/20 dark:text-amber-200">
                        <Sparkles className="size-3 text-amber-600 dark:text-amber-400" />
                        Mode Démonstration UI
                    </span>
                    <span className="hidden font-medium text-amber-900/80 sm:inline dark:text-amber-200/80">
                        Recette MEZ-157
                    </span>
                </div>

                {/* Right: Controls */}
                <div className="flex flex-wrap items-center gap-2">
                    {/* Role / Persona Switcher */}
                    {onRoleChange && (
                        <label className="flex items-center gap-1.5 font-medium">
                            <span className="text-[0.6875rem] font-bold text-amber-900/70 dark:text-amber-300/70">
                                Profil :
                            </span>
                            <select
                                value={currentRole}
                                onChange={(e) => onRoleChange(e.target.value as KoraPointUserRole)}
                                className="h-8 rounded-lg border border-amber-300/60 bg-white/90 px-2 py-1 text-xs font-black text-amber-950 shadow-sm transition hover:bg-white focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-amber-500/40 dark:bg-slate-900/90 dark:text-amber-100"
                            >
                                <option value="AGENT_POINT">Agent Point (Jean Dossou)</option>
                                <option value="RESPONSABLE_ORG">Resp. Organisation (Paul H.)</option>
                                <option value="TRESORIER_ORG">Trésorier Org. (Adolphe G.)</option>
                                <option value="ADMIN_KORA">Admin Réseau (Éric L.)</option>
                                <option value="FINANCE_KORA">Finance KORA (Clarisse M.)</option>
                            </select>
                        </label>
                    )}

                    <label className="flex items-center gap-1.5 font-medium">
                        <span className="sr-only">Scénario de test :</span>
                        <select
                            value={currentScenario}
                            onChange={(e) => onScenarioChange(e.target.value as DemoScenarioId)}
                            className="h-8 rounded-lg border border-amber-300/60 bg-white/90 px-2.5 py-1 text-xs font-semibold text-amber-950 shadow-sm transition hover:bg-white focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-amber-500/40 dark:bg-slate-900/90 dark:text-amber-100"
                        >
                            {SCENARIOS.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.label}
                                </option>
                            ))}
                        </select>
                    </label>

                    <button
                        type="button"
                        onClick={onReset}
                        title="Réinitialiser l'état fictif de ce scénario"
                        className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-amber-300/50 bg-white/80 px-2.5 text-xs font-semibold text-amber-950 transition hover:bg-white dark:border-amber-500/30 dark:bg-slate-900/80 dark:text-amber-100 dark:hover:bg-slate-900"
                    >
                        <RotateCcw className="size-3 text-amber-700 dark:text-amber-400" />
                        <span className="hidden sm:inline">Réinitialiser</span>
                    </button>

                    {onRestartTour && (
                        <button
                            type="button"
                            onClick={onRestartTour}
                            title="Revoir le guide d'accueil interactif"
                            className="inline-flex h-8 items-center gap-1.5 rounded-lg bg-amber-600 px-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-amber-700 dark:bg-amber-500 dark:text-amber-950 dark:hover:bg-amber-400"
                        >
                            <HelpCircle className="size-3" />
                            <span>Guide</span>
                        </button>
                    )}
                </div>
            </div>
        </aside>
    );
}
