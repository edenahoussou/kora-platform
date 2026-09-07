import { useCallback, useState } from 'react';

type OnboardingStorage = {
    completed: string[];
    dismissed: string[];
};

const STORAGE_KEY = 'kora-onboarding';

function readStorage(): OnboardingStorage {
    if (typeof window === 'undefined') {
        return { completed: [], dismissed: [] };
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return { completed: [], dismissed: [] };
        }

        const parsed = JSON.parse(raw) as OnboardingStorage;

        return {
            completed: Array.isArray(parsed.completed) ? parsed.completed : [],
            dismissed: Array.isArray(parsed.dismissed) ? parsed.dismissed : [],
        };
    } catch {
        return { completed: [], dismissed: [] };
    }
}

function writeStorage(data: OnboardingStorage): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    } catch {
        // Silently ignore quota or serialization errors.
    }
}

/**
 * Hook to manage onboarding state for a named tour.
 *
 * The tour key must be unique per tour (e.g. "dynamic-pricing"). Once a
 * tour is completed or dismissed, it won't show again unless `reset` is
 * called.
 */
export function useOnboarding(tourKey: string) {
    const [isActive, setIsActive] = useState(false);
    const [shouldAutoStart, setShouldAutoStart] = useState(() => {
        const storage = readStorage();
        const alreadyDone =
            storage.completed.includes(tourKey) ||
            storage.dismissed.includes(tourKey);

        return !alreadyDone;
    });

    const start = useCallback(() => {
        setIsActive(true);
    }, []);

    const complete = useCallback(() => {
        const storage = readStorage();

        if (!storage.completed.includes(tourKey)) {
            storage.completed.push(tourKey);
            writeStorage(storage);
        }

        setIsActive(false);
        setShouldAutoStart(false);
    }, [tourKey]);

    const dismiss = useCallback(() => {
        const storage = readStorage();

        if (!storage.dismissed.includes(tourKey)) {
            storage.dismissed.push(tourKey);
            writeStorage(storage);
        }

        setIsActive(false);
        setShouldAutoStart(false);
    }, [tourKey]);

    const reset = useCallback(() => {
        const storage = readStorage();
        storage.completed = storage.completed.filter((k) => k !== tourKey);
        storage.dismissed = storage.dismissed.filter((k) => k !== tourKey);
        writeStorage(storage);
        setShouldAutoStart(true);
    }, [tourKey]);

    return {
        isActive,
        shouldAutoStart,
        start,
        complete,
        dismiss,
        reset,
    };
}
