<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { start } from '@/routes/demo';

withDefaults(
    defineProps<{
        class?: string;
        /** "primary" is the one accent button of a view; "secondary" is the neutral button. */
        variant?: 'primary' | 'secondary';
    }>(),
    { class: '', variant: 'secondary' },
);

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
            :aria-busy="busy"
            :class="[
                'inline-flex h-10 w-full items-center justify-center rounded-md px-6 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:opacity-60',
                variant === 'primary'
                    ? 'bg-accent text-accent-ink hover:opacity-90'
                    : 'border border-border-strong bg-surface-2 text-text hover:bg-border',
                $props.class,
            ]"
        >
            {{ busy ? 'Setting up your demo…' : 'Try the demo' }}
        </button>
    </form>
</template>
