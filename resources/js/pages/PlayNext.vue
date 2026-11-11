<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import PrivateLibrary from '@/components/library/PrivateLibrary.vue';
import LadderRow from '@/components/play-next/LadderRow.vue';
import TopPick from '@/components/play-next/TopPick.vue';
import { formatRelative } from '@/lib/format';
import { library } from '@/routes';
import type { PlayNextProps } from '@/types/play-next';

const props = defineProps<PlayNextProps>();

const focusRing =
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';

// Poll while the import runs; the server flips the state to ready once picks exist.
const importing = computed(() => props.state === 'importing');
const { start, stop } = usePoll(
    3000,
    { only: ['picks', 'ranked_at', 'state', 'privacy', 'import'] },
    { autoStart: false },
);
watch(
    importing,
    (value) => {
        if (value) {
            start();
        } else {
            stop();
        }
    },
    { immediate: true },
);

const top = computed(() => props.picks[0] ?? null);
const rest = computed(() => props.picks.slice(1));
</script>

<template>
    <Head title="Play next" />

    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-1">
            <h1 class="font-display text-title font-bold text-text">
                Play next
            </h1>
            <p v-if="state === 'ready' && ranked_at" class="text-text-muted">
                Ranked from your library,
                <time :datetime="ranked_at">{{
                    formatRelative(ranked_at)
                }}</time
                >.
            </p>
        </div>

        <ol v-if="state === 'ready' && top" class="flex flex-col gap-6">
            <TopPick :pick="top" />
            <li v-if="rest.length > 0" class="list-none">
                <ol class="divide-y divide-border border-y border-border">
                    <LadderRow
                        v-for="pick in rest"
                        :key="pick.entry_id"
                        :pick="pick"
                    />
                </ol>
            </li>
        </ol>

        <div
            v-else-if="state === 'importing'"
            role="status"
            class="flex max-w-[65ch] flex-col gap-1 rounded-md border border-border bg-surface px-4 py-3"
        >
            <p class="text-text">
                Importing your library. Your picks will appear here when it's
                done.
            </p>
        </div>

        <PrivateLibrary
            v-else-if="state === 'private' && privacy"
            :privacy="privacy"
        />

        <p v-else-if="state === 'empty'" class="max-w-[65ch] text-text">
            Your Steam library is empty, so there's nothing to rank yet.
        </p>

        <section
            v-else-if="state === 'nothing'"
            class="flex max-w-[65ch] flex-col items-start gap-4"
        >
            <p class="text-text">
                Nothing to suggest right now. Everything we can see is finished
                or has no details yet.
            </p>
            <Link
                :href="library()"
                class="rounded-[2px] text-text underline underline-offset-4 hover:text-text-muted"
                :class="focusRing"
            >
                Browse your library
            </Link>
        </section>
    </div>
</template>
