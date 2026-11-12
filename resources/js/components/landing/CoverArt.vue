<script setup lang="ts">
import { ref } from 'vue';
import { initialsOf } from '@/lib/format';

defineProps<{ name: string; url: string | null }>();

const failed = ref(false);
</script>

<template>
    <img
        v-if="url && !failed"
        :src="url"
        :alt="name"
        width="264"
        height="352"
        class="aspect-[2/3] w-full rounded-md bg-surface-2 object-cover"
        @error="failed = true"
    />
    <div
        v-else
        role="img"
        :aria-label="name"
        class="flex aspect-[2/3] w-full items-center justify-center rounded-md bg-surface-2 text-heading font-semibold text-text-muted"
    >
        {{ initialsOf(name) }}
    </div>
</template>
