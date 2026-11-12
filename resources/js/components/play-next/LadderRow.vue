<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import GameCover from '@/components/library/GameCover.vue';
import NotThisOneMenu from '@/components/play-next/NotThisOneMenu.vue';
import { usePickFeedback } from '@/lib/pickFeedback';
import type { Pick } from '@/types/play-next';

const props = defineProps<{ pick: Pick }>();

const steamUrl = computed(() => `steam://run/${props.pick.app_id}`);
const { busy, send } = usePickFeedback(() => props.pick.entry_id);

// On mobile the reason is clamped to 3 lines. The full text is always in the page; when the clamp actually
// cuts something off, a "Show more" button reveals it. From sm up there is no clamp.
const reason = ref<HTMLElement | null>(null);
const expanded = ref(false);
const truncated = ref(false);

onMounted(() => {
    const el = reason.value;

    if (el && window.matchMedia('(max-width: 639px)').matches) {
        truncated.value = el.scrollHeight > el.clientHeight + 1;
    }
});
</script>

<template>
    <li class="flex items-start gap-3 py-4 sm:gap-4">
        <span
            class="w-10 shrink-0 text-right font-display text-rank font-bold text-text-muted sm:w-16"
            ><span class="sr-only">Rank </span>{{ pick.rank }}</span
        >
        <GameCover
            :name="pick.name"
            :cover-url="pick.cover_url"
            :icon-url="pick.icon_url"
            size="md"
        />
        <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-3">
                <h3 class="font-semibold break-words text-text">
                    {{ pick.name }}
                </h3>
                <p class="shrink-0 font-mono text-text tabular-nums">
                    <span aria-hidden="true"
                        >{{ pick.score
                        }}<span class="text-xs text-text-muted"
                            >/100</span
                        ></span
                    >
                    <span class="sr-only"
                        >score {{ pick.score }} out of 100</span
                    >
                </p>
            </div>
            <p
                ref="reason"
                class="mt-1 text-sm text-text-muted sm:line-clamp-none"
                :class="expanded ? '' : 'line-clamp-3'"
            >
                {{ pick.explanation }}
            </p>
            <button
                v-if="truncated"
                type="button"
                :aria-expanded="expanded"
                class="mt-1 rounded-[2px] text-sm text-text-muted underline underline-offset-4 hover:text-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent sm:hidden"
                @click="expanded = !expanded"
            >
                {{ expanded ? 'Show less' : 'Show more' }}
            </button>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <!-- Records "playing" and lets the steam:// link open as usual. -->
                <a
                    :href="steamUrl"
                    :aria-label="`Play ${pick.name} on Steam`"
                    class="inline-flex h-9 items-center rounded-md px-3 text-sm font-medium text-text transition-colors duration-150 hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                    @click="send('playing')"
                    >Play</a
                >
                <NotThisOneMenu
                    :name="pick.name"
                    :busy="busy"
                    @not-tonight="send('not_tonight')"
                    @never="send('never')"
                />
            </div>
        </div>
    </li>
</template>
