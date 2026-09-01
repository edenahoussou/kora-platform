import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * Generate a RFC 4122 v4 UUID.
 *
 * `crypto.randomUUID()` is only available in secure contexts (HTTPS or
 * localhost). KORA OPS is served over plain HTTP on the VPS staging
 * (`:8080`) and local `.local` domains, so we fall back to a manual
 * v4 UUID built from `crypto.getRandomValues` (or `Math.random` as a
 * last resort) to avoid `crypto.randomUUID is not a function` errors.
 */
export function uuid(): string {
    const c = globalThis.crypto;

    if (typeof c?.randomUUID === 'function') {
        return c.randomUUID();
    }

    const bytes = new Uint8Array(16);

    if (typeof c?.getRandomValues === 'function') {
        c.getRandomValues(bytes);
    } else {
        for (let i = 0; i < 16; i++) {
            bytes[i] = Math.floor(Math.random() * 256);
        }
    }

    // Set version (4) and variant (RFC 4122).
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0'));

    return `${hex.slice(0, 4).join('')}-${hex.slice(4, 6).join('')}-${hex.slice(6, 8).join('')}-${hex.slice(8, 10).join('')}-${hex.slice(10, 16).join('')}`;
}
