<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { importMethod } from '@/routes/library';

withDefaults(defineProps<{ variant?: 'secondary' | 'ghost' }>(), {
    variant: 'secondary',
});
</script>

<template>
    <!-- Posts to library.import; the server queues the job and the page starts polling. -->
    <Form :action="importMethod()" #default="{ processing }">
        <button
            type="submit"
            :disabled="processing"
            class="h-9 rounded-md px-4 text-sm font-medium transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:opacity-60"
            :class="
                variant === 'secondary'
                    ? 'border border-border-strong bg-surface-2 text-text hover:bg-border'
                    : 'text-text-muted hover:text-text'
            "
        >
            <slot />
        </button>
    </Form>
</template>
