export type LibraryStatus = 'unplayed' | 'recent' | 'played';
/** A status filter segment: a derived status, or the hidden (never suggest) games. */
export type LibraryStatusFilter = LibraryStatus | 'hidden';
export type SortColumn = 'name' | 'playtime' | 'last_played' | 'status';
export type SortDirection = 'asc' | 'desc';

export type MatchStatus = 'pending' | 'matched' | 'unmatched' | 'retrying';
/** Only genres and themes are sent to the page. */
export type TagType = 'genre' | 'theme';

export type LibraryRow = {
    id: number;
    name: string;
    app_id: number;
    icon_url: string | null;
    /** IGDB cover_small (90x128), or null when the game has no cover. */
    cover_url: string | null;
    /** At most 2 tags: genres first, then themes. */
    tags: { name: string; type: TagType }[];
    match_status: MatchStatus;
    playtime_minutes: number;
    /** ISO 8601, or null when the game was never played. */
    last_played_at: string | null;
    status: LibraryStatus;
    /** True when the latest feedback is "never suggest". */
    hidden: boolean;
};

export type LibraryPage = {
    data: LibraryRow[];
    /** Entries matching the current filters, across all pages. */
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

/** Entries per status for the current search, ignoring the status filter. */
export type StatusCounts = Record<LibraryStatus | 'all', number>;

export type LibraryFilters = {
    sort: SortColumn;
    direction: SortDirection;
    /** A derived status, `hidden` for never-suggested games, or null for all. */
    status: LibraryStatus | 'hidden' | null;
    q: string | null;
};

export type ImportStatus =
    | 'idle'
    | 'queued'
    | 'running'
    | 'done'
    | 'private'
    | 'failed';

export type ImportInfo = {
    status: ImportStatus;
    visibility: 'public' | 'private' | 'unknown';
    imported_at: string | null;
};

export type PrivacyInfo = {
    settings_url: string;
    steps: string[];
};
