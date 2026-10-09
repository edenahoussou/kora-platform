import { useEffect, useMemo, useState } from 'react';

const HOLD_DURATION_MS = 15 * 60 * 1000;
const URGENCY_THRESHOLD_MS = 5 * 60 * 1000;

export type HoldCountdown = {
    label: string;
    percentage: number;
    isUrgent: boolean;
    isExpired: boolean;
};

export function useHoldCountdown(
    expiresAt: string | null,
    startedAt: string | null = null,
): HoldCountdown {
    const [now, setNow] = useState<number | null>(null);

    useEffect(() => {
        const update = () => setNow(Date.now());

        update();
        const interval = window.setInterval(update, 1000);

        return () => window.clearInterval(interval);
    }, [expiresAt]);

    const totalMs = useMemo(() => {
        const expirationTimestamp = expiresAt
            ? new Date(expiresAt).getTime()
            : NaN;
        const startedTimestamp = startedAt
            ? new Date(startedAt).getTime()
            : NaN;

        if (
            Number.isNaN(expirationTimestamp) ||
            Number.isNaN(startedTimestamp)
        ) {
            return HOLD_DURATION_MS;
        }

        return Math.max(1, expirationTimestamp - startedTimestamp);
    }, [expiresAt, startedAt]);

    return useMemo(() => {
        if (!expiresAt || now === null) {
            return {
                label: '—',
                percentage: 0,
                isUrgent: false,
                isExpired: false,
            };
        }

        const expirationTimestamp = new Date(expiresAt).getTime();

        if (Number.isNaN(expirationTimestamp)) {
            return {
                label: '—',
                percentage: 0,
                isUrgent: false,
                isExpired: false,
            };
        }

        const remainingMs = Math.max(0, expirationTimestamp - now);
        const remainingMinutes = Math.floor(remainingMs / 60000);
        const remainingSeconds = Math.floor((remainingMs % 60000) / 1000);

        return {
            label: `${remainingMinutes}:${remainingSeconds.toString().padStart(2, '0')}`,
            percentage: Math.min(
                100,
                Math.max(0, (remainingMs / totalMs) * 100),
            ),
            isUrgent: remainingMs > 0 && remainingMs <= URGENCY_THRESHOLD_MS,
            isExpired: remainingMs === 0,
        };
    }, [expiresAt, now, totalMs]);
}
