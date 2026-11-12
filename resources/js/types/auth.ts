/** The slice of the user shared with every page (see HandleInertiaRequests). */
export type User = {
    id: number;
    persona_name: string;
    avatar_url: string | null;
    profile_url: string | null;
    /** A throwaway demo account: show the demo banner. */
    is_demo: boolean;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
