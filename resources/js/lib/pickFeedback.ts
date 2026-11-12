import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { feedback } from '@/routes/picks';

export type FeedbackKind = 'playing' | 'not_tonight' | 'never';

/**
 * Posts feedback for a library entry. The server redirects back to the current URL, so the query string
 * (hours, mood, with) is kept, and the toast arrives as a flash.
 */
export function usePickFeedback(entryId: () => number) {
    const busy = ref(false);

    function send(kind: FeedbackKind): void {
        if (busy.value) {
            return;
        }

        busy.value = true;
        router.post(
            feedback.url(entryId()),
            { kind },
            {
                preserveScroll: true,
                onFinish: () => {
                    busy.value = false;
                },
            },
        );
    }

    return { busy, send };
}
