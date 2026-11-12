import type { ImportInfo, PrivacyInfo } from '@/types/library';

export type SignalKey =
    | 'taste'
    | 'momentum'
    | 'fit'
    | 'mood'
    | 'social'
    | 'freshness';

export type PickSignal = {
    key: SignalKey;
    label: string;
    /** 0..1 */
    value: number;
};

export type Pick = {
    rank: number;
    entry_id: number;
    name: string;
    app_id: number;
    icon_url: string | null;
    /** IGDB cover_big for rank 1, cover_small for the rest. */
    cover_url: string | null;
    explanation: string;
    /** Whole number, 0..100. */
    score: number;
    playtime_minutes: number;
    /** Rank 1 only. */
    signals?: PickSignal[];
};

export type PlayNextState =
    | 'ready'
    | 'importing'
    | 'private'
    | 'empty'
    | 'nothing';

export type PlayNextHours = 1 | 2 | 4 | 6;
export type PlayNextMood = 'chill' | 'intense';
export type PlayNextCompany = 'solo' | 'friends';

/** The session controls as the server read them from the query string. */
export type PlayNextFilters = {
    hours: PlayNextHours;
    mood: PlayNextMood | null;
    with: PlayNextCompany | null;
};

export type PlayNextProps = {
    picks: Pick[];
    filters: PlayNextFilters;
    ranked_at: string | null;
    state: PlayNextState;
    privacy: PrivacyInfo | null;
    import: ImportInfo;
};
