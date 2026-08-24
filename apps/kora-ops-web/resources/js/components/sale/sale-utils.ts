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

export function formatShortDate(value: string | null): string {
    if (!value) {
        return 'Date non définie';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value}T12:00:00`));
}

export function formatTime(value: string | null): string {
    return value ? value.slice(0, 5) : '—';
}

export function getDepartureTimestamp(
    departureDate: string,
    departureTime: string,
): number | null {
    const timestamp = new Date(
        `${departureDate}T${departureTime.slice(0, 8)}`,
    ).getTime();

    return Number.isNaN(timestamp) ? null : timestamp;
}

export function isToday(value: string): boolean {
    const today = new Date();
    const todayValue = [
        today.getFullYear(),
        String(today.getMonth() + 1).padStart(2, '0'),
        String(today.getDate()).padStart(2, '0'),
    ].join('-');

    return value === todayValue;
}

export function formatDepartureCountdown(
    departureDate: string,
    departureTime: string,
    now = Date.now(),
): string | null {
    if (!isToday(departureDate)) {
        return null;
    }

    const departureTimestamp = getDepartureTimestamp(
        departureDate,
        departureTime,
    );

    if (departureTimestamp === null || departureTimestamp <= now) {
        return null;
    }

    const remainingMinutes = Math.ceil((departureTimestamp - now) / 60000);

    if (remainingMinutes < 60) {
        return `dans ${remainingMinutes} min`;
    }

    const hours = Math.floor(remainingMinutes / 60);
    const minutes = remainingMinutes % 60;

    return minutes > 0
        ? `dans ${hours} h ${minutes.toString().padStart(2, '0')}`
        : `dans ${hours} h`;
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
