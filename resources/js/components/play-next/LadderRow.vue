<script setup lang="ts">
import { computed } from 'vue';
import GameCover from '@/components/library/GameCover.vue';
import type { Pick } from '@/types/play-next';

const props = defineProps<{ pick: Pick }>();

const steamUrl = computed(() => `steam://run/${props.pick.app_id}`);
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
            <h3 class="font-semibold break-words text-text">{{ pick.name }}</h3>
            <p
                class="mt-1 line-clamp-2 text-sm text-text-muted sm:line-clamp-none"
            >
                {{ pick.explanation }}
            </p>
        </div>
        <div class="flex shrink-0 flex-col items-end gap-2">
            <p class="font-mono text-text tabular-nums">
                <span aria-hidden="true">{{ pick.score }}</span>
                <span class="sr-only">score {{ pick.score }} out of 100</span>
            </p>
            <a
                :href="steamUrl"
                :aria-label="`Play ${pick.name} on Steam`"
                class="rounded-[2px] text-sm text-text-muted underline underline-offset-4 hover:text-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                >Play</a
            >
        </div>
    </li>
</template>
