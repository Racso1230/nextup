<script setup lang="ts">
import { computed } from 'vue';
import type { LibraryStatus } from '@/types/library';

const props = defineProps<{ status: LibraryStatus }>();

const shapes: Record<LibraryStatus, { shape: string; word: string }> = {
    unplayed: { shape: '○', word: 'Unplayed' },
    played: { shape: '◐', word: 'Played' },
    recent: { shape: '●', word: 'Recent' },
};

const label = computed(() => shapes[props.status]);
</script>

<template>
    <span class="inline-flex items-center gap-2 text-text-muted">
        <span aria-hidden="true">{{ label.shape }}</span>
        {{ label.word }}
    </span>
</template>
