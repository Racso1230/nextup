<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import PickActions from '@/components/play-next/PickActions.vue';
import SignalBar from '@/components/play-next/SignalBar.vue';
import { initialsOf } from '@/lib/format';
import { usePickFeedback } from '@/lib/pickFeedback';
import type { Pick } from '@/types/play-next';

const props = defineProps<{ pick: Pick }>();

const coverFailed = ref(false);
watch(
    () => props.pick.cover_url,
    () => {
        coverFailed.value = false;
    },
);

const showCover = computed(
    () => props.pick.cover_url !== null && !coverFailed.value,
);
const steamUrl = computed(() => `steam://run/${props.pick.app_id}`);
const { busy, send } = usePickFeedback(() => props.pick.entry_id);
</script>

<template>
    <li
        class="grid grid-cols-[96px_1fr] gap-x-4 gap-y-4 rounded-[10px] border border-accent bg-surface p-4 shadow-[0_0_32px_-8px_var(--color-accent)] sm:grid-cols-[200px_1fr] sm:gap-x-6 sm:p-6 md:gap-x-8"
    >
        <!-- Below sm the cover is small and sits beside the rank and name, so the reason and the Play button show without scrolling. -->
        <div class="sm:row-span-2">
            <img
                v-if="showCover"
                :src="pick.cover_url ?? undefined"
                :alt="pick.name"
                width="200"
                height="300"
                loading="eager"
                class="aspect-[2/3] w-full rounded-md bg-surface-2 object-cover sm:max-h-[300px]"
                @error="coverFailed = true"
            />
            <div
                v-else
                role="img"
                :aria-label="pick.name"
                class="flex aspect-[2/3] w-full items-center justify-center rounded-md bg-surface-2 text-heading font-semibold text-text-muted sm:max-h-[300px]"
            >
                {{ initialsOf(pick.name) }}
            </div>
        </div>

        <div
            class="flex min-w-0 flex-col gap-1 sm:flex-row sm:items-end sm:gap-4"
        >
            <span
                class="font-display text-rank leading-none font-extrabold text-text sm:text-rank-xl sm:leading-[0.85]"
                ><span class="sr-only">Rank </span>1</span
            >
            <h2
                class="min-w-0 font-display text-title font-bold break-words text-text sm:pb-2"
            >
                {{ pick.name }}
            </h2>
        </div>

        <div
            class="col-span-2 flex min-w-0 flex-col gap-4 sm:col-span-1 sm:col-start-2"
        >
            <p class="max-w-[65ch] text-text">{{ pick.explanation }}</p>

            <div
                v-if="pick.signals && pick.signals.length > 0"
                class="flex max-w-[28rem] flex-col gap-2"
            >
                <SignalBar
                    v-for="signal in pick.signals"
                    :key="signal.key"
                    :label="signal.label"
                    :value="signal.value"
                />
            </div>

            <div class="flex flex-col items-start gap-1">
                <!-- No preventDefault: the click records "playing" and the steam:// link opens as usual. -->
                <a
                    :href="steamUrl"
                    @click="send('playing')"
                    :aria-label="`Play ${pick.name} on Steam`"
                    class="inline-flex h-10 items-center rounded-md bg-accent px-5 font-semibold text-accent-ink transition-colors duration-150 hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                >
                    Play on Steam
                </a>
                <p class="text-sm text-text-muted">Opens the Steam app</p>
            </div>

            <PickActions
                :name="pick.name"
                :busy="busy"
                @not-tonight="send('not_tonight')"
                @never="send('never')"
            />
        </div>
    </li>
</template>
