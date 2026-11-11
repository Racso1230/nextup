<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ label: string; value: number }>();

const percent = computed(() => Math.round(props.value * 100));
</script>

<template>
    <div class="flex items-center gap-3">
        <span class="w-28 shrink-0 text-sm text-text-muted sm:w-32">{{
            label
        }}</span>
        <div
            role="meter"
            :aria-label="label"
            aria-valuemin="0"
            aria-valuemax="1"
            :aria-valuenow="value"
            :aria-valuetext="value.toFixed(2)"
            class="h-2 min-w-0 flex-1 overflow-hidden rounded-[2px] bg-surface-2"
        >
            <div
                class="h-full bg-accent"
                :style="{ width: `${percent}%` }"
            ></div>
        </div>
        <span
            class="w-10 shrink-0 text-right font-mono text-sm text-text tabular-nums"
            aria-hidden="true"
            >{{ value.toFixed(2) }}</span
        >
    </div>
</template>
