export type Playtime = { value: string; unit: 'h' };

/** Hours with one decimal under 10 h, whole hours from 10 h. Zero is "0". */
export function formatPlaytime(minutes: number): Playtime {
    const hours = Math.max(0, minutes) / 60;

    if (hours === 0) {
        return { value: '0', unit: 'h' };
    }

    return {
        value: hours < 10 ? hours.toFixed(1) : String(Math.round(hours)),
        unit: 'h',
    };
}

const UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 365 * 24 * 60 * 60],
    ['month', 30 * 24 * 60 * 60],
    ['week', 7 * 24 * 60 * 60],
    ['day', 24 * 60 * 60],
    ['hour', 60 * 60],
    ['minute', 60],
];

/** "3 days ago", "last year", or "Never" when there is no date. */
export function formatRelative(
    iso: string | null,
    now: Date = new Date(),
): string {
    if (iso === null) {
        return 'Never';
    }

    const seconds = Math.round(
        (new Date(iso).getTime() - now.getTime()) / 1000,
    );
    const formatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

    for (const [unit, size] of UNITS) {
        if (Math.abs(seconds) >= size) {
            return formatter.format(Math.trunc(seconds / size), unit);
        }
    }

    return 'just now';
}

/** The absolute date for a tooltip, e.g. "2 January 2026". */
export function formatDate(iso: string): string {
    return new Intl.DateTimeFormat('en-GB', { dateStyle: 'long' }).format(
        new Date(iso),
    );
}

/** Up to two initials for the icon placeholder. */
export function initialsOf(name: string): string {
    const words = name.split(/\s+/).filter(Boolean);

    return words
        .slice(0, 2)
        .map((word) => Array.from(word)[0] ?? '')
        .join('')
        .toUpperCase();
}
