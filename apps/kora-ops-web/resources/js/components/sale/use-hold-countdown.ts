import { useEffect, useMemo, useState } from 'react';

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
    const [totalMs, setTotalMs] = useState(0);

    useEffect(() => {
        const update = () => setNow(Date.now());

        update();
        const interval = window.setInterval(update, 1000);

        return () => window.clearInterval(interval);
    }, [expiresAt]);

    useEffect(() => {
        if (!expiresAt) {
            setTotalMs(0);

            return;
        }

        const expirationTimestamp = new Date(expiresAt).getTime();

        if (Number.isNaN(expirationTimestamp)) {
            setTotalMs(0);

            return;
        }

        const startedTimestamp = startedAt
            ? new Date(startedAt).getTime()
            : NaN;

        setTotalMs(
            Number.isNaN(startedTimestamp)
                ? Math.max(1, expirationTimestamp - Date.now())
                : Math.max(1, expirationTimestamp - startedTimestamp),
        );
    }, [expiresAt, startedAt]);

    return useMemo(() => {
        const empty = {
            label: '—',
            percentage: 0,
            isUrgent: false,
            isExpired: false,
        };

        if (!expiresAt || now === null) {
            return empty;
        }

        const expirationTimestamp = new Date(expiresAt).getTime();

        if (Number.isNaN(expirationTimestamp)) {
            return empty;
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
