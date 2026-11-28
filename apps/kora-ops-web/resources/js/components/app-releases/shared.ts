/**
 * Types et helpers partagés de l'écran « Versions de l'application »
 * (MEZ-150) et du badge de version de l'écran Terminaux.
 */

export type AppVersionStatus =
    | 'up_to_date'
    | 'update_available'
    | 'update_required'
    | 'unknown'
    | 'not_tracked';

export type VersionStatusSummary = {
    status: AppVersionStatus;
    label: string;
    latest_version: string | null;
    latest_build: number | null;
    mandatory: boolean;
};

export type ReleasedAppTab = {
    value: string;
    label: string;
    slug: string;
    releases_count: number;
};

export type PushCampaignSummary = {
    uuid: string;
    topic: string;
    status: 'pending' | 'accepted' | 'failed' | 'uncertain';
    title: string;
    body: string;
    attempts: number;
    error: string | null;
    fcm_message_id: string | null;
    accepted_at: string | null;
    created_at: string | null;
    retry_decision_at: string | null;
    retry_decision_note: string | null;
    author: { uuid: string; name: string } | null;
    retry_decision_by: { uuid: string; name: string } | null;
};

export type AppRelease = {
    uuid: string;
    version: string;
    build_number: number;
    full_version: string;
    release_notes: string | null;
    is_mandatory: boolean;
    is_latest: boolean;
    is_minimum: boolean;
    state: 'published' | 'archived';
    channel?: 'ops_apk' | 'store';
    platform?: 'android' | 'ios' | null;
    environment?: 'staging' | 'production' | null;
    store_url?: string | null;
    publicly_confirmed_at?: string | null;
    rollout_completed_at?: string | null;
    size_bytes: number | null;
    sha256: string | null;
    original_filename: string | null;
    download_url: string | null;
    published_at: string | null;
    archived_at: string | null;
    created_at: string | null;
    author: { uuid: string; name: string } | null;
    push_campaign?: PushCampaignSummary | null;
};

export type VersionDistributionRow = {
    version: string | null;
    terminals: number;
    status: AppVersionStatus;
    status_label: string;
};

export type VersionDistribution = {
    total: number;
    by_status: Record<AppVersionStatus, number>;
    versions: VersionDistributionRow[];
};

export const versionStatusLabels: Record<AppVersionStatus, string> = {
    up_to_date: 'À jour',
    update_available: 'MAJ disponible',
    update_required: 'MAJ requise',
    unknown: 'Version inconnue',
    not_tracked: 'Non suivi',
};

export const versionStatusTones: Record<
    AppVersionStatus,
    'navy' | 'gold' | 'blue' | 'muted' | 'destructive'
> = {
    up_to_date: 'navy',
    update_available: 'gold',
    update_required: 'destructive',
    unknown: 'muted',
    not_tracked: 'muted',
};

export function formatBytes(bytes: number): string {
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '—';
    }

    const units = ['o', 'Ko', 'Mo', 'Go'];
    const exponent = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length - 1,
    );

    return `${new Intl.NumberFormat('fr-FR', {
        maximumFractionDigits: exponent >= 2 ? 1 : 0,
    }).format(bytes / 1024 ** exponent)} ${units[exponent]}`;
}

export function formatReleaseDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

/**
 * Copie dans le presse-papiers, avec repli `execCommand` pour les accès en
 * HTTP simple (intranet), où `navigator.clipboard` est indisponible.
 */
export async function copyToClipboard(text: string): Promise<boolean> {
    try {
        if (window.isSecureContext && navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);

            return true;
        }
    } catch {
        // Repli ci-dessous.
    }

    try {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        const copied = document.execCommand('copy');
        document.body.removeChild(textarea);

        return copied;
    } catch {
        return false;
    }
}
