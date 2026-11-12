<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { destroy } from '@/routes/picks/feedback';

const props = defineProps<{ entryId: number; name: string }>();

const busy = ref(false);

// The server redirects back to this page, so the search, filter and sort in the URL are kept.
function undo(): void {
    if (busy.value) {
        return;
    }

    busy.value = true;
    router.delete(destroy.url(props.entryId), {
        preserveScroll: true,
        onFinish: () => {
            busy.value = false;
        },
    });
}
</script>

<template>
    <span class="flex flex-wrap items-center gap-x-3 text-sm text-text-muted">
        Hidden from suggestions
        <button
            type="button"
            :disabled="busy"
            :aria-label="`Undo: show ${name} in suggestions again`"
            class="rounded-[2px] underline underline-offset-4 hover:text-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:opacity-50"
            @click="undo"
        >
            Undo
        </button>
    </span>
</template>
