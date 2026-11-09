<script setup lang="ts">
import { computed } from 'vue';
import { formatDate, formatRelative } from '@/lib/format';

const props = defineProps<{ at: string | null }>();

const relative = computed(() => formatRelative(props.at));
</script>

<template>
    <time v-if="at" :datetime="at" :title="formatDate(at)">{{ relative }}</time>
    <span v-else>Never</span>
</template>
