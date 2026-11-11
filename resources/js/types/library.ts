export type LibraryStatus = 'unplayed' | 'recent' | 'played';
export type SortColumn = 'name' | 'playtime' | 'last_played';
export type SortDirection = 'asc' | 'desc';

export type MatchStatus = 'pending' | 'matched' | 'unmatched' | 'retrying';
/** Keywords are never sent to the page. */
export type TagType = 'genre' | 'theme' | 'game_mode' | 'perspective';

export type LibraryRow = {
    id: number;
    name: string;
    app_id: number;
    icon_url: string | null;
    /** IGDB cover_small (90x128), or null when the game has no cover. */
    cover_url: string | null;
    /** At most 4 display tags; keywords are never sent. */
    tags: { name: string; type: TagType }[];
    /** Displayable tags beyond the 4 shown. */
    tag_overflow: number;
    match_status: MatchStatus;
    playtime_minutes: number;
    /** ISO 8601, or null when the game was never played. */
    last_played_at: string | null;
    status: LibraryStatus;
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

export type LibraryFilters = {
    sort: SortColumn;
    direction: SortDirection;
    status: LibraryStatus | null;
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
