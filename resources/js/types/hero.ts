/** One cover in the landing page hero's shuffling stack. */
export type HeroCover = {
    name: string;
    cover_url: string | null;
};

/** The sample pick on the landing page, from the demo library (the `hero` prop of Welcome). */
export type HeroPick = {
    name: string;
    cover_url: string | null;
    explanation: string;
    signals: { key: string; label: string; value: number }[];
    /** The next few picks, for the cover stack. */
    next: HeroCover[];
};
