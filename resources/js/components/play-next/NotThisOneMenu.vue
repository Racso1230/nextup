<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { nextTick, ref } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

defineProps<{ name: string; busy: boolean }>();

const emit = defineEmits<{ notTonight: []; never: [] }>();

// "Never suggest" is destructive, so it asks again inside the menu rather than closing it.
const confirming = ref(false);
const cancelItem = ref<{ $el: HTMLElement } | null>(null);

async function ask(event: Event): Promise<void> {
    // Keep the menu open and swap its contents for the confirmation step.
    event.preventDefault();
    confirming.value = true;
    await nextTick();
    // Cancel is the safe default for a destructive prompt.
    cancelItem.value?.$el.focus();
}

function onOpenChange(open: boolean): void {
    if (!open) {
        confirming.value = false;
    }
}

const itemClass = 'min-h-9 cursor-pointer text-text';
</script>

<template>
    <DropdownMenu @update:open="onOpenChange">
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                :disabled="busy"
                :aria-label="`Not this one: ${name}`"
                class="inline-flex h-9 items-center gap-1 rounded-md px-3 text-sm text-text-muted transition-colors duration-150 hover:bg-surface-2 hover:text-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:opacity-50"
            >
                Not this one
                <ChevronDown class="size-4" aria-hidden="true" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            class="min-w-44 border-border-strong shadow-none"
        >
            <template v-if="!confirming">
                <DropdownMenuItem
                    :class="itemClass"
                    @select="emit('notTonight')"
                >
                    Not tonight
                </DropdownMenuItem>
                <DropdownMenuItem :class="itemClass" @select="ask">
                    Never suggest
                </DropdownMenuItem>
            </template>
            <template v-else>
                <DropdownMenuLabel class="px-2 py-1.5 text-sm text-text">
                    Hide {{ name }} for good?
                </DropdownMenuLabel>
                <DropdownMenuItem
                    ref="cancelItem"
                    :class="itemClass"
                    @select="confirming = false"
                >
                    Cancel
                </DropdownMenuItem>
                <DropdownMenuItem :class="itemClass" @select="emit('never')">
                    Yes, hide it
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
