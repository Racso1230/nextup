<script setup lang="ts">
import { ref } from 'vue';
import { initialsOf } from '@/lib/format';

withDefaults(
    defineProps<{
        name: string;
        url: string | null;
        /** Hide the art from assistive tech when the game name is rendered right next to it. */
        decorative?: boolean;
    }>(),
    { decorative: false },
);

const failed = ref(false);
</script>

<template>
    <img
        v-if="url && !failed"
        :src="url"
        :alt="decorative ? '' : name"
        :aria-hidden="decorative ? 'true' : undefined"
        width="264"
        height="352"
        class="aspect-[2/3] w-full rounded-md bg-surface-2 object-cover"
        @error="failed = true"
    />
    <div
        v-else
        :role="decorative ? undefined : 'img'"
        :aria-label="decorative ? undefined : name"
        :aria-hidden="decorative ? 'true' : undefined"
        class="flex aspect-[2/3] w-full items-center justify-center rounded-md bg-surface-2 text-sm font-semibold text-text-muted"
    >
        {{ initialsOf(name) }}
    </div>
</template>
