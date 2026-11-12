<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppHeader from '@/components/AppHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import { logout } from '@/routes';

const page = usePage();
const isDemo = computed(() => page.props.auth.user?.is_demo === true);
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <a
            href="#content"
            class="sr-only rounded-md border border-border-strong bg-surface-2 px-4 py-2 text-text focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50"
        >
            Skip to content
        </a>
        <div
            v-if="isDemo"
            class="border-b border-border bg-surface-2 text-sm text-text"
        >
            <div
                class="mx-auto flex w-full max-w-[72rem] flex-wrap items-center justify-between gap-x-6 gap-y-1 px-4 py-2 md:px-8"
            >
                <p>
                    You're using the demo with a sample library. It's deleted
                    after 24 hours.
                </p>
                <!-- Ends the demo session (and deletes it), then lands on the page with the Steam button. -->
                <Link
                    :href="logout()"
                    as="button"
                    class="rounded-sm underline underline-offset-4 hover:text-text-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                >
                    Sign in with Steam
                </Link>
            </div>
        </div>
        <AppHeader />
        <main
            id="content"
            tabindex="-1"
            class="mx-auto w-full max-w-[72rem] flex-1 px-4 py-8 focus:outline-none md:px-8"
        >
            <slot />
        </main>
        <Toaster />
    </div>
</template>
