export type User = {
    id: number;
    /** SteamID64 as a string: it does not fit in a JS number. */
    steam_id: string;
    persona_name: string;
    avatar_url: string | null;
    profile_url: string | null;
    library_visibility: 'public' | 'private' | 'unknown';
    import_status:
        | 'idle'
        | 'queued'
        | 'running'
        | 'done'
        | 'private'
        | 'failed';
    imported_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
