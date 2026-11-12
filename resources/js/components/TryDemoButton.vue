<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { start } from '@/routes/demo';

withDefaults(defineProps<{ class?: string }>(), { class: '' });

const busy = ref(false);

// A POST creates the throwaway account, so this is a form submit rather than a link.
function submit() {
    router.post(
        start.url(),
        {},
        {
            onStart: () => {
                busy.value = true;
            },
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}
</script>

<template>
    <form method="post" :action="start.url()" @submit.prevent="submit">
        <button
            type="submit"
            :disabled="busy"
            :class="[
                'inline-flex h-10 w-full items-center justify-center rounded-md border border-border-strong bg-surface-2 px-6 text-sm font-semibold text-text hover:bg-border focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:opacity-60',
                $props.class,
            ]"
        >
            Try the demo
        </button>
    </form>
</template>
