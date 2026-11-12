<script setup lang="ts">
import { nextTick, ref } from 'vue';

const props = defineProps<{
    name: string;
    busy: boolean;
    /** "card" for the top pick, "row" for a compact ladder row. */
    variant: 'card' | 'row';
}>();

const emit = defineEmits<{ notTonight: []; never: [] }>();

const confirming = ref(false);
const neverButton = ref<HTMLButtonElement | null>(null);
const cancelButton = ref<HTMLButtonElement | null>(null);

const focusRing =
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';

async function ask(): Promise<void> {
    confirming.value = true;
    await nextTick();
    // Cancel is the safe default for a destructive prompt.
    cancelButton.value?.focus();
}

async function cancel(): Promise<void> {
    confirming.value = false;
    await nextTick();
    neverButton.value?.focus();
}

function confirm(): void {
    confirming.value = false;
    emit('never');
}

const textButton =
    'rounded-[2px] text-sm text-text-muted underline underline-offset-4 hover:text-text disabled:opacity-50';
const secondary =
    props.variant === 'card'
        ? 'h-10 rounded-md border border-border-strong bg-surface-2 px-4 font-medium text-text transition-colors duration-150 hover:bg-border disabled:opacity-50'
        : textButton;
const ghost =
    props.variant === 'card'
        ? 'h-10 rounded-md px-3 text-text-muted underline underline-offset-4 hover:text-text disabled:opacity-50'
        : textButton;
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-x-4 gap-y-2"
        @keydown.esc="confirming && cancel()"
    >
        <template v-if="!confirming">
            <button
                type="button"
                :disabled="busy"
                :aria-label="`Not tonight: ${name}`"
                :class="[secondary, focusRing]"
                @click="emit('notTonight')"
            >
                Not tonight
            </button>
            <button
                ref="neverButton"
                type="button"
                :disabled="busy"
                :aria-label="`${variant === 'card' ? 'Never suggest' : 'Never'}: ${name}`"
                :class="[ghost, focusRing]"
                @click="ask"
            >
                {{ variant === 'card' ? 'Never suggest' : 'Never' }}
            </button>
        </template>
        <div
            v-else
            role="group"
            :aria-label="`Hide ${name} for good?`"
            class="flex flex-wrap items-center gap-x-4 gap-y-2"
        >
            <span class="text-sm text-text">Hide it for good?</span>
            <button
                type="button"
                :disabled="busy"
                :aria-label="`Yes, hide ${name}`"
                :class="[secondary, focusRing]"
                @click="confirm"
            >
                Yes, hide it
            </button>
            <button
                ref="cancelButton"
                type="button"
                :disabled="busy"
                :class="[ghost, focusRing]"
                @click="cancel"
            >
                Cancel
            </button>
        </div>
    </div>
</template>
