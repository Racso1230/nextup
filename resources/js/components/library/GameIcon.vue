<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { initialsOf } from '@/lib/format';

const props = defineProps<{ name: string; src: string | null }>();

const failed = ref(false);
watch(
    () => props.src,
    () => {
        failed.value = false;
    },
);

const showImage = computed(() => props.src !== null && !failed.value);
</script>

<template>
    <!-- alt is empty because the game name is always rendered right next to the icon. -->
    <img
        v-if="showImage"
        :src="src ?? undefined"
        alt=""
        width="32"
        height="32"
        loading="lazy"
        class="size-8 shrink-0 rounded-[2px] bg-surface-2 object-cover"
        @error="failed = true"
    />
    <span
        v-else
        aria-hidden="true"
        class="flex size-8 shrink-0 items-center justify-center rounded-[2px] bg-surface-2 text-sm font-semibold text-text-muted"
    >
        {{ initialsOf(name) }}
    </span>
</template>
