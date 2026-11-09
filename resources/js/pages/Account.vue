<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { destroy } from '@/routes/account';

defineProps<{
    confirmation: string;
}>();

const typed = ref('');

const focusRing =
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
</script>

<template>
    <Head title="Account" />

    <div class="flex flex-col gap-8 py-8">
        <h1 class="font-display text-title font-bold text-text">Account</h1>

        <section
            aria-labelledby="delete-heading"
            class="flex max-w-[65ch] flex-col gap-4 rounded-md border border-border bg-surface p-6"
        >
            <h2
                id="delete-heading"
                class="text-heading font-semibold text-text"
            >
                Delete your account
            </h2>
            <p class="text-text-muted">
                This removes your nextup account, your imported library and your
                playtime data, and signs you out. It can't be undone. Your Steam
                account isn't affected, and you can sign in again later to start
                fresh.
            </p>

            <Form
                :action="destroy()"
                class="flex flex-col gap-3"
                #default="{ errors, processing }"
            >
                <label for="confirmation" class="text-sm text-text">
                    Type {{ confirmation }} to confirm
                </label>
                <input
                    id="confirmation"
                    v-model="typed"
                    name="confirmation"
                    type="text"
                    autocomplete="off"
                    spellcheck="false"
                    :aria-invalid="errors.confirmation ? 'true' : undefined"
                    :aria-describedby="
                        errors.confirmation ? 'confirmation-error' : undefined
                    "
                    class="h-10 max-w-xs rounded-[2px] border border-border-strong bg-bg px-3 text-text placeholder:text-text-muted"
                    :class="focusRing"
                />
                <p
                    v-if="errors.confirmation"
                    id="confirmation-error"
                    class="text-sm text-danger"
                >
                    {{ errors.confirmation }}
                </p>
                <div>
                    <button
                        type="submit"
                        :disabled="processing || typed !== confirmation"
                        class="h-10 rounded-md border border-danger bg-surface-2 px-4 text-sm font-semibold text-danger transition-colors duration-150 hover:bg-border disabled:cursor-not-allowed disabled:opacity-50"
                        :class="focusRing"
                    >
                        Delete account
                    </button>
                </div>
            </Form>
        </section>
    </div>
</template>
