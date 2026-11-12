<script setup lang="ts" generic="T extends string | number | null">
import { useId } from 'vue';

defineProps<{
    label: string;
    options: { value: T; label: string }[];
    modelValue: T;
}>();

defineEmits<{ select: [value: T] }>();

const labelId = useId();
</script>

<template>
    <div class="flex flex-col gap-2">
        <p :id="labelId" class="text-sm font-semibold text-text">{{ label }}</p>
        <div
            role="group"
            :aria-labelledby="labelId"
            class="flex flex-wrap gap-1"
        >
            <button
                v-for="option in options"
                :key="String(option.value)"
                type="button"
                :aria-pressed="modelValue === option.value"
                class="h-9 rounded-md border px-3 text-sm transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                :class="
                    modelValue === option.value
                        ? 'border-border-strong bg-surface-2 text-text'
                        : 'border-transparent text-text-muted hover:text-text'
                "
                @click="$emit('select', option.value)"
            >
                {{ option.label }}
            </button>
        </div>
    </div>
</template>
