<script setup lang="ts">
import { computed } from 'vue';
import { formatPlaytime } from '@/lib/format';

const props = defineProps<{ minutes: number }>();
const playtime = computed(() => formatPlaytime(props.minutes));
</script>

<template>
    <span class="font-mono tabular-nums">
        {{ playtime.value }}
        <span class="text-text-muted">{{ playtime.unit }}</span>
    </span>
</template>
