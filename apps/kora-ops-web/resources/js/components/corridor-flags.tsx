import { cn } from '@/lib/utils';

export type CorridorCountryCode = 'BJ' | 'TG' | 'GH' | 'CI';

interface FlagProps {
    className?: string;
    title?: string;
}

/**
 * 🇧🇯 Drapeau du Bénin sous forme d'avatar circulaire (viewBox carrée 0 0 100 100).
 * Masqué dans un disque (r=50), avec bande verte au guindant (40%) et bicolore jaune/rouge au battant.
 */
export function BeninCircleFlag({ className, title = 'Bénin' }: FlagProps) {
    return (
        <svg
            viewBox="0 0 100 100"
            className={cn('size-full shrink-0', className)}
            aria-label={title}
            role="img"
        >
            <title>{title}</title>
            <defs>
                <clipPath id="benin-circle">
                    <circle cx="50" cy="50" r="50" />
                </clipPath>
            </defs>
            <g clipPath="url(#benin-circle)">
                {/* Moitié supérieure jaune */}
                <rect x="0" y="0" width="100" height="50" fill="#FCD20F" />
                {/* Moitié inférieure rouge */}
                <rect x="0" y="50" width="100" height="50" fill="#E90929" />
                {/* Bande verticale verte au guindant (40% de largeur) */}
                <rect x="0" y="0" width="40" height="100" fill="#008850" />
            </g>
        </svg>
    );
}

/**
 * 🇹🇬 Drapeau du Togo sous forme d'avatar circulaire (viewBox carrée 0 0 100 100).
 * 5 bandes horizontales alternées vert (#006A4D) et jaune (#FFCF00).
 * Canton rouge (#D30731) au guindant avec étoile blanche à 5 branches.
 */
export function TogoCircleFlag({ className, title = 'Togo' }: FlagProps) {
    return (
        <svg
            viewBox="0 0 100 100"
            className={cn('size-full shrink-0', className)}
            aria-label={title}
            role="img"
        >
            <title>{title}</title>
            <defs>
                <clipPath id="togo-circle">
                    <circle cx="50" cy="50" r="50" />
                </clipPath>
            </defs>
            <g clipPath="url(#togo-circle)">
                {/* 5 bandes horizontales (20px chacune) : vert, jaune, vert, jaune, vert */}
                <rect width="100" height="100" fill="#006A4D" />
                <rect y="20" width="100" height="20" fill="#FFCF00" />
                <rect y="60" width="100" height="20" fill="#FFCF00" />
                {/* Canton rouge supérieur gauche (60x60 = 3 bandes de haut) */}
                <rect width="60" height="60" fill="#D30731" />
                {/* Étoile blanche centrée en (30, 30), rayon ~18 */}
                <polygon
                    fill="#FFFFFF"
                    points="30,13 34,24 46,24 36,32 40,44 30,36 20,44 24,32 14,24 26,24"
                />
            </g>
        </svg>
    );
}

/**
 * 🇬🇭 Drapeau du Ghana sous forme d'avatar circulaire (viewBox carrée 0 0 100 100).
 * 3 bandes horizontales : rouge (#CF0921), jaune (#FCD20F), vert (#006B3D).
 * Étoile noire à 5 branches au centre (50, 50).
 */
export function GhanaCircleFlag({ className, title = 'Ghana' }: FlagProps) {
    return (
        <svg
            viewBox="0 0 100 100"
            className={cn('size-full shrink-0', className)}
            aria-label={title}
            role="img"
        >
            <title>{title}</title>
            <defs>
                <clipPath id="ghana-circle">
                    <circle cx="50" cy="50" r="50" />
                </clipPath>
            </defs>
            <g clipPath="url(#ghana-circle)">
                {/* 3 bandes horizontales */}
                <rect width="100" height="33.4" fill="#CF0921" />
                <rect y="33.3" width="100" height="33.4" fill="#FCD20F" />
                <rect y="66.6" width="100" height="33.4" fill="#006B3D" />
                {/* Étoile noire centrée en (50, 50), rayon ~17 */}
                <polygon
                    fill="#000000"
                    points="50,33 54,44 66,44 56,52 60,64 50,56 40,64 44,52 34,44 46,44"
                />
            </g>
        </svg>
    );
}

/**
 * 🇨🇮 Drapeau de la Côte d'Ivoire sous forme d'avatar circulaire (viewBox carrée 0 0 100 100).
 * 3 bandes verticales : orange (#F77F00), blanc (#FFFFFF), vert (#009E60).
 */
export function IvoryCoastCircleFlag({
    className,
    title = "Côte d'Ivoire",
}: FlagProps) {
    return (
        <svg
            viewBox="0 0 100 100"
            className={cn('size-full shrink-0', className)}
            aria-label={title}
            role="img"
        >
            <title>{title}</title>
            <defs>
                <clipPath id="ci-circle">
                    <circle cx="50" cy="50" r="50" />
                </clipPath>
            </defs>
            <g clipPath="url(#ci-circle)">
                <rect width="33.4" height="100" fill="#F77F00" />
                <rect x="33.3" width="33.4" height="100" fill="#FFFFFF" />
                <rect x="66.6" width="33.4" height="100" fill="#009E60" />
            </g>
        </svg>
    );
}

export const CORRIDOR_COUNTRIES: Array<{
    code: CorridorCountryCode;
    name: string;
    capital: string;
    currency: string;
    timezone: string;
    hub: string;
    flag: (props: FlagProps) => React.JSX.Element;
}> = [
        {
            code: 'BJ',
            name: 'Bénin',
            capital: 'Porto-Novo / Cotonou',
            currency: 'FCFA (XOF)',
            timezone: 'UTC+1',
            hub: 'Siège Réseau KORA',
            flag: BeninCircleFlag,
        },
        {
            code: 'TG',
            name: 'Togo',
            capital: 'Lomé',
            currency: 'FCFA (XOF)',
            timezone: 'UTC+0',
            hub: 'Gare Internationale Lomé',
            flag: TogoCircleFlag,
        },
        {
            code: 'GH',
            name: 'Ghana',
            capital: 'Accra',
            currency: 'Cedi (GHS)',
            timezone: 'UTC+0',
            hub: 'Hub Central Accra',
            flag: GhanaCircleFlag,
        },
        {
            code: 'CI',
            name: "Côte d'Ivoire",
            capital: 'Yamoussoukro / Abidjan',
            currency: 'FCFA (XOF)',
            timezone: 'UTC+0',
            hub: 'Gare Sud Abidjan',
            flag: IvoryCoastCircleFlag,
        },
    ];
