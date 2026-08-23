export function formatMoney(value: number, currency = 'XOF'): string {
    return `${new Intl.NumberFormat('fr-FR').format(value)} ${currency === 'XOF' ? 'FCFA' : currency}`;
}

export function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }).format(new Date(`${value}T12:00:00`));
}

export function formatTime(value: string | null): string {
    return value ? value.slice(0, 5) : '—';
}

export function formatDuration(minutes: number | null): string {
    if (!minutes) {
        return '—';
    }

    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;

    return `${hours} h${remainder ? ` ${remainder.toString().padStart(2, '0')}` : ''}`;
}

export function formatRemainingTime(expiresAt: string | null): string {
    if (!expiresAt) {
        return '—';
    }

    const remaining = Math.max(0, new Date(expiresAt).getTime() - Date.now());
    const minutes = Math.floor(remaining / 60000);
    const seconds = Math.floor((remaining % 60000) / 1000);

    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
}
