<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { initialsOf } from '@/lib/format';

const props = withDefaults(
    defineProps<{
        name: string;
        coverUrl: string | null;
        iconUrl: string | null;
        size?: 'sm' | 'md';
    }>(),
    { size: 'sm' },
);

// Fallback chain: IGDB cover, then the Steam icon on a tile, then the initials.
const coverFailed = ref(false);
const iconFailed = ref(false);
watch(
    () => props.coverUrl,
    () => {
        coverFailed.value = false;
    },
);
watch(
    () => props.iconUrl,
    () => {
        iconFailed.value = false;
    },
);

const stage = computed<'cover' | 'icon' | 'initials'>(() => {
    if (props.coverUrl !== null && !coverFailed.value) {
        return 'cover';
    }

    if (props.iconUrl !== null && !iconFailed.value) {
        return 'icon';
    }

    return 'initials';
});

// Portrait 2:3, 32x48 on desktop and 40x60 on mobile.
const box = computed(() =>
    props.size === 'md' ? 'h-[60px] w-10' : 'h-12 w-8',
);
const iconBox = computed(() => (props.size === 'md' ? 'size-8' : 'size-6'));
</script>

<template>
    <!-- alt is empty because the game name is always rendered right next to the art. -->
    <img
        v-if="stage === 'cover'"
        :src="coverUrl ?? undefined"
        alt=""
        :width="size === 'md' ? 40 : 32"
        :height="size === 'md' ? 60 : 48"
        loading="lazy"
        class="shrink-0 rounded-[2px] bg-surface-2 object-cover"
        :class="box"
        @error="coverFailed = true"
    />
    <span
        v-else-if="stage === 'icon'"
        class="flex shrink-0 items-center justify-center rounded-[2px] bg-surface-2"
        :class="box"
    >
        <img
            :src="iconUrl ?? undefined"
            alt=""
            width="32"
            height="32"
            loading="lazy"
            class="rounded-[2px] object-cover"
            :class="iconBox"
            @error="iconFailed = true"
        />
    </span>
    <span
        v-else
        aria-hidden="true"
        class="flex shrink-0 items-center justify-center rounded-[2px] bg-surface-2 text-sm font-semibold text-text-muted"
        :class="box"
    >
        {{ initialsOf(name) }}
    </span>
</template>
