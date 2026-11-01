import { useState, useEffect, useMemo, useRef } from 'react';
import {
    Search,
    X,
    Ticket,
    Store,
    Building2,
    Receipt,
    ArrowRight,
    CornerDownLeft,
    Clock,
    User,
    Check,
} from 'lucide-react';
import {
    PointSaleRecord,
    AdminNetworkPoint,
    AdminOrganization,
    PointSettlementRecord,
    KoraPointUserRole,
} from '../mock/kora-point-mock-data';
import { KoraPointActivePage } from '../common/kora-point-shell';
import { KoraStatusBadge } from '@/components/kora-status-badge';

type Props = {
    isOpen: boolean;
    onClose: () => void;
    sales: PointSaleRecord[];
    points: AdminNetworkPoint[];
    organizations: AdminOrganization[];
    settlements: PointSettlementRecord[];
    onNavigate: (page: KoraPointActivePage) => void;
    currentRole?: KoraPointUserRole;
};

type SearchResultItem = {
    id: string;
    category: 'TICKET' | 'POINT' | 'ORGANIZATION' | 'SETTLEMENT';
    categoryLabel: string;
    title: string;
    subtitle: string;
    meta?: string;
    badgeLabel: string;
    badgeTone: 'emerald' | 'amber' | 'blue' | 'destructive' | 'gold' | 'muted';
    targetPage: KoraPointActivePage;
};

export function KoraGlobalSearchDialog({
    isOpen,
    onClose,
    sales,
    points,
    organizations,
    settlements,
    onNavigate,
    currentRole = 'AGENT_POINT',
}: Props) {
    const [query, setQuery] = useState('');
    const [activeCategory, setActiveCategory] = useState<string>('ALL');
    const [selectedIndex, setSelectedIndex] = useState(0);
    const inputRef = useRef<HTMLInputElement>(null);

    // Focus input on open
    useEffect(() => {
        if (isOpen) {
            setTimeout(() => inputRef.current?.focus(), 50);
            setQuery('');
            setSelectedIndex(0);
        }
    }, [isOpen]);

    // Global keyboard listener for Ctrl+K / Cmd+K and Esc
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                if (isOpen) {
                    onClose();
                } else {
                    // Trigger parent or we handle inside
                }
            } else if (e.key === 'Escape' && isOpen) {
                e.preventDefault();
                onClose();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, onClose]);

    // Filter datasets according to active persona permissions
    const accessibleSales = useMemo(() => {
        if (currentRole === 'AGENT_POINT') {
            return sales.filter((s) => s.seller_name.includes('Jean Dossou') || s.ticket_number.includes('AKP'));
        }
        return sales;
    }, [sales, currentRole]);

    const accessiblePoints = useMemo(() => {
        if (currentRole === 'AGENT_POINT') {
            return points.filter((p) => p.code.includes('AKP'));
        }
        if (currentRole === 'RESPONSABLE_ORG' || currentRole === 'TRESORIER_ORG') {
            return points.filter((p) => p.organization_name?.includes('La Poste'));
        }
        return points;
    }, [points, currentRole]);

    const accessibleOrgs = useMemo(() => {
        if (currentRole === 'AGENT_POINT') return [];
        if (currentRole === 'RESPONSABLE_ORG' || currentRole === 'TRESORIER_ORG') {
            return organizations.filter((o) => o.code.includes('LAPOSTE'));
        }
        return organizations;
    }, [organizations, currentRole]);

    const accessibleSettlements = useMemo(() => {
        if (currentRole === 'AGENT_POINT') {
            return settlements.filter((s) => s.point_name.includes('Akpakpa'));
        }
        if (currentRole === 'RESPONSABLE_ORG' || currentRole === 'TRESORIER_ORG') {
            return settlements.filter((s) => s.reference.includes('ORG') || s.point_name.includes('Akpakpa') || s.point_name.includes('Poste'));
        }
        return settlements;
    }, [settlements, currentRole]);

    // Transform datasets to unified results
    const allResults = useMemo<SearchResultItem[]>(() => {
        const q = query.trim().toLowerCase();

        const ticketResults: SearchResultItem[] = accessibleSales.map((sale) => ({
            id: `ticket-${sale.uuid}`,
            category: 'TICKET',
            categoryLabel: 'Billet Bus',
            title: `${sale.passenger_name} — ${sale.ticket_number}`,
            subtitle: `${sale.route_label} (${sale.departure_ref}) • Siège ${sale.seat_number}`,
            meta: `${sale.amount_paid.toLocaleString('fr-FR')} FCFA (${sale.payment_method})`,
            badgeLabel:
                sale.settlement_status === 'settled'
                    ? 'Apuré'
                    : sale.settlement_status === 'in_settlement'
                    ? 'En versement'
                    : 'Non versé',
            badgeTone:
                sale.settlement_status === 'settled'
                    ? 'emerald'
                    : sale.settlement_status === 'in_settlement'
                    ? 'amber'
                    : 'muted',
            targetPage: 'tickets',
        }));

        const pointResults: SearchResultItem[] = accessiblePoints.map((p) => ({
            id: `point-${p.uuid}`,
            category: 'POINT',
            categoryLabel: 'Point de Vente',
            title: `${p.name} (${p.code})`,
            subtitle: `${p.city} • Gérant : ${p.manager_name} (${p.phone})`,
            meta: `Encours : ${p.current_exposure.toLocaleString('fr-FR')} FCFA`,
            badgeLabel:
                p.status === 'ACTIVE'
                    ? 'Actif'
                    : p.status === 'WARNING_THRESHOLD'
                    ? 'Seuil 80%'
                    : p.status === 'LIMIT_REACHED'
                    ? 'Plafond atteint'
                    : 'Suspendu',
            badgeTone:
                p.status === 'ACTIVE'
                    ? 'emerald'
                    : p.status === 'WARNING_THRESHOLD'
                    ? 'amber'
                    : 'destructive',
            targetPage: currentRole === 'RESPONSABLE_ORG' ? 'org-supervision' : 'admin-network',
        }));

        const orgResults: SearchResultItem[] = accessibleOrgs.map((org) => ({
            id: `org-${org.uuid}`,
            category: 'ORGANIZATION',
            categoryLabel: 'Organisation',
            title: `${org.name} (${org.code})`,
            subtitle: `${org.headquarters_city} • IFU : ${org.ifu} • ${org.points_count} bureaux`,
            meta: `Encours groupé : ${org.total_exposure.toLocaleString('fr-FR')} FCFA`,
            badgeLabel: 'Partenaire Réseau',
            badgeTone: 'gold',
            targetPage: 'admin-organizations',
        }));

        const settlementResults: SearchResultItem[] = accessibleSettlements.map((set) => ({
            id: `set-${set.uuid}`,
            category: 'SETTLEMENT',
            categoryLabel: 'Reversement',
            title: `${set.reference} — ${set.point_name}`,
            subtitle: `Déclaré par ${set.created_by_name} le ${set.declared_at || set.created_at}`,
            meta: `${set.amount_declared.toLocaleString('fr-FR')} FCFA (${set.sales_count} billets)`,
            badgeLabel:
                set.status === 'DECLARED'
                    ? 'Déclaré'
                    : set.status === 'VALIDATED'
                    ? 'Validé'
                    : set.status === 'REJECTED'
                    ? 'Rejeté'
                    : 'Brouillon',
            badgeTone:
                set.status === 'DECLARED'
                    ? 'amber'
                    : set.status === 'VALIDATED'
                    ? 'emerald'
                    : set.status === 'REJECTED'
                    ? 'destructive'
                    : 'muted',
            targetPage: currentRole === 'FINANCE_KORA' ? 'finance' : currentRole === 'TRESORIER_ORG' ? 'org-treasury' : 'settlements',
        }));

        const combined = [...ticketResults, ...pointResults, ...orgResults, ...settlementResults];

        if (!q) {
            // Return top 8 recent items
            return combined.slice(0, 8);
        }

        return combined.filter(
            (item) =>
                item.title.toLowerCase().includes(q) ||
                item.subtitle.toLowerCase().includes(q) ||
                (item.meta && item.meta.toLowerCase().includes(q)),
        );
    }, [accessibleSales, accessiblePoints, accessibleOrgs, accessibleSettlements, query, currentRole]);

    // Filter by selected category chip
    const filteredResults = useMemo(() => {
        if (activeCategory === 'ALL') return allResults;
        return allResults.filter((item) => item.category === activeCategory);
    }, [allResults, activeCategory]);

    // Handle selection and navigation
    const handleSelectResult = (item: SearchResultItem) => {
        onNavigate(item.targetPage);
        onClose();
    };

    // Keyboard navigation
    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setSelectedIndex((prev) => (prev < filteredResults.length - 1 ? prev + 1 : 0));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setSelectedIndex((prev) => (prev > 0 ? prev - 1 : filteredResults.length - 1));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (filteredResults[selectedIndex]) {
                handleSelectResult(filteredResults[selectedIndex]);
            }
        }
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center p-4 pt-16 sm:pt-24 bg-slate-950/60 backdrop-blur-sm animate-in fade-in">
            <div
                className="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-white text-slate-900 shadow-2xl ring-1 ring-slate-900/10 dark:bg-slate-900 dark:text-slate-100 dark:ring-white/10"
                onKeyDown={handleKeyDown}
            >
                {/* Search Bar Input */}
                <div className="flex items-center border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <Search className="size-5 text-gold shrink-0 mr-3" />
                    <input
                        ref={inputRef}
                        type="text"
                        value={query}
                        onChange={(e) => {
                            setQuery(e.target.value);
                            setSelectedIndex(0);
                        }}
                        placeholder="Rechercher passager, n° billet, code point, organisation, versement..."
                        className="flex-1 bg-transparent text-sm font-medium text-slate-900 placeholder-slate-400 focus:outline-none dark:text-white"
                    />

                    {query && (
                        <button
                            type="button"
                            onClick={() => {
                                setQuery('');
                                inputRef.current?.focus();
                            }}
                            className="mr-2 rounded-md p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                        >
                            <X className="size-4" />
                        </button>
                    )}

                    <kbd className="hidden sm:inline-block rounded bg-slate-100 px-2 py-1 font-mono text-[0.625rem] font-bold text-slate-500 border border-slate-200 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-400">
                        ESC
                    </kbd>
                </div>

                {/* Category Chips Bar */}
                <div className="flex items-center gap-1.5 overflow-x-auto border-b border-slate-100 bg-slate-50/75 px-4 py-2 text-xs dark:border-slate-800 dark:bg-slate-850">
                    <button
                        type="button"
                        onClick={() => {
                            setActiveCategory('ALL');
                            setSelectedIndex(0);
                        }}
                        className={`rounded-lg px-2.5 py-1 font-bold text-xs transition ${
                            activeCategory === 'ALL'
                                ? 'bg-gold text-kora font-black shadow-sm'
                                : 'text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800'
                        }`}
                    >
                        Tous ({allResults.length})
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            setActiveCategory('TICKET');
                            setSelectedIndex(0);
                        }}
                        className={`inline-flex items-center gap-1 rounded-lg px-2.5 py-1 font-bold text-xs transition ${
                            activeCategory === 'TICKET'
                                ? 'bg-gold text-kora font-black shadow-sm'
                                : 'text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800'
                        }`}
                    >
                        <Ticket className="size-3.5" />
                        <span>Billets</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            setActiveCategory('POINT');
                            setSelectedIndex(0);
                        }}
                        className={`inline-flex items-center gap-1 rounded-lg px-2.5 py-1 font-bold text-xs transition ${
                            activeCategory === 'POINT'
                                ? 'bg-gold text-kora font-black shadow-sm'
                                : 'text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800'
                        }`}
                    >
                        <Store className="size-3.5" />
                        <span>Points</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            setActiveCategory('ORGANIZATION');
                            setSelectedIndex(0);
                        }}
                        className={`inline-flex items-center gap-1 rounded-lg px-2.5 py-1 font-bold text-xs transition ${
                            activeCategory === 'ORGANIZATION'
                                ? 'bg-gold text-kora font-black shadow-sm'
                                : 'text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800'
                        }`}
                    >
                        <Building2 className="size-3.5" />
                        <span>Organisations</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            setActiveCategory('SETTLEMENT');
                            setSelectedIndex(0);
                        }}
                        className={`inline-flex items-center gap-1 rounded-lg px-2.5 py-1 font-bold text-xs transition ${
                            activeCategory === 'SETTLEMENT'
                                ? 'bg-gold text-kora font-black shadow-sm'
                                : 'text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800'
                        }`}
                    >
                        <Receipt className="size-3.5" />
                        <span>Reversements</span>
                    </button>
                </div>

                {/* Results List */}
                <div className="max-h-96 overflow-y-auto p-2">
                    {filteredResults.length === 0 ? (
                        <div className="p-8 text-center text-xs text-slate-500">
                            <Search className="mx-auto size-8 text-slate-300 dark:text-slate-600 mb-2" />
                            <p className="font-bold text-slate-700 dark:text-slate-300">
                                Aucun résultat pour « {query} »
                            </p>
                            <p className="mt-1 text-[0.6875rem] text-slate-400">
                                Essayez avec un numéro de billet (#TK-), le nom d'un voyageur ou d'une commune.
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-1">
                            {filteredResults.map((item, index) => {
                                const isSelected = index === selectedIndex;
                                return (
                                    <div
                                        key={item.id}
                                        onClick={() => handleSelectResult(item)}
                                        onMouseEnter={() => setSelectedIndex(index)}
                                        className={`flex cursor-pointer items-center justify-between rounded-xl px-3.5 py-2.5 transition ${
                                            isSelected
                                                ? 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-white'
                                                : 'text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/50'
                                        }`}
                                    >
                                        <div className="flex items-center gap-3 truncate">
                                            <div
                                                className={`grid size-8 shrink-0 place-items-center rounded-lg ${
                                                    item.category === 'TICKET'
                                                        ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
                                                        : item.category === 'POINT'
                                                        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                        : item.category === 'ORGANIZATION'
                                                        ? 'bg-gold/15 text-gold'
                                                        : 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                                }`}
                                            >
                                                {item.category === 'TICKET' ? (
                                                    <Ticket className="size-4" />
                                                ) : item.category === 'POINT' ? (
                                                    <Store className="size-4" />
                                                ) : item.category === 'ORGANIZATION' ? (
                                                    <Building2 className="size-4" />
                                                ) : (
                                                    <Receipt className="size-4" />
                                                )}
                                            </div>

                                            <div className="truncate">
                                                <div className="flex items-center gap-2">
                                                    <span className="truncate text-xs font-black text-slate-900 dark:text-white">
                                                        {item.title}
                                                    </span>
                                                    <span className="text-[0.625rem] font-bold text-slate-400 uppercase tracking-wider">
                                                        • {item.categoryLabel}
                                                    </span>
                                                </div>
                                                <div className="truncate text-[0.6875rem] text-slate-500 dark:text-slate-400">
                                                    {item.subtitle}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="ml-3 flex items-center gap-2 shrink-0">
                                            {item.meta && (
                                                <span className="hidden font-mono text-xs font-bold text-slate-700 sm:inline dark:text-slate-300">
                                                    {item.meta}
                                                </span>
                                            )}

                                            <KoraStatusBadge
                                                label={item.badgeLabel}
                                                tone={item.badgeTone}
                                                variant="solid"
                                            />

                                            {isSelected && (
                                                <CornerDownLeft className="size-3.5 text-slate-400 ml-1 hidden sm:inline" />
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* Footer Quick Nav Hints */}
                <div className="flex items-center justify-between border-t border-slate-100 bg-slate-50/80 px-4 py-2.5 text-[0.6875rem] text-slate-500 dark:border-slate-800 dark:bg-slate-850 dark:text-slate-400">
                    <div className="flex items-center gap-3">
                        <span className="inline-flex items-center gap-1">
                            <kbd className="rounded bg-white px-1.5 py-0.5 font-mono text-[0.5625rem] font-bold shadow-xs border border-slate-200 dark:bg-slate-800 dark:border-slate-700">
                                ↑
                            </kbd>
                            <kbd className="rounded bg-white px-1.5 py-0.5 font-mono text-[0.5625rem] font-bold shadow-xs border border-slate-200 dark:bg-slate-800 dark:border-slate-700">
                                ↓
                            </kbd>
                            <span>naviguer</span>
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <kbd className="rounded bg-white px-1.5 py-0.5 font-mono text-[0.5625rem] font-bold shadow-xs border border-slate-200 dark:bg-slate-800 dark:border-slate-700">
                                ↵
                            </kbd>
                            <span>accéder</span>
                        </span>
                    </div>

                    <span>{filteredResults.length} résultats indexés</span>
                </div>
            </div>
        </div>
    );
}
