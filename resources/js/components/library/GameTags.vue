<script setup lang="ts">
import { computed } from 'vue';
import type { LibraryRow } from '@/types/library';

const props = defineProps<{ row: LibraryRow }>();

const overflowLabel = computed(
    () =>
        `and ${props.row.tag_overflow} more ${props.row.tag_overflow === 1 ? 'tag' : 'tags'}`,
);
const lookingUp = computed(
    () =>
        props.row.match_status === 'pending' ||
        props.row.match_status === 'retrying',
);
</script>

<template>
    <!-- Tag names come from IGDB and are untrusted: interpolation only, never v-html. -->
    <ul
        v-if="row.tags.length > 0"
        aria-label="Tags"
        class="flex flex-wrap gap-1 text-sm"
    >
        <li
            v-for="tag in row.tags"
            :key="`${tag.type}-${tag.name}`"
            class="rounded-[2px] bg-surface-2 px-1.5 text-text-muted"
        >
            {{ tag.name }}
        </li>
        <li
            v-if="row.tag_overflow > 0"
            :aria-label="overflowLabel"
            class="rounded-[2px] bg-surface-2 px-1.5 text-text-muted"
        >
            <span aria-hidden="true">+{{ row.tag_overflow }}</span>
        </li>
    </ul>
    <p v-else-if="lookingUp" class="text-xs text-text-muted">
        Looking up details…
    </p>
    <p
        v-else-if="row.match_status === 'unmatched'"
        class="text-xs text-text-muted"
        title="IGDB has no match for this Steam app, so it won't be recommended."
    >
        No details found
    </p>
</template>
