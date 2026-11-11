<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { steam } from '@/routes/auth';
</script>

<template>
    <Head title="Sign in" />

    <main
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-bg p-6 text-text"
    >
        <div
            class="flex w-full max-w-sm flex-col items-start gap-6 rounded-md border border-border-strong bg-surface p-8 text-left"
        >
            <div class="flex flex-col gap-2">
                <p
                    class="font-display text-title leading-none font-bold text-text"
                >
                    nextup
                </p>
                <h1 class="text-heading font-semibold text-text">Sign in</h1>
            </div>
            <p class="text-text-muted">
                nextup reads your public Steam library and never sees your
                password, so signing in with Steam is the only way in.
            </p>
            <!-- A plain link: this is a full-page redirect off-site, not an Inertia visit. -->
            <a
                :href="steam.url()"
                class="inline-flex h-10 w-full items-center justify-center rounded-md bg-accent px-6 text-sm font-semibold text-accent-ink hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
            >
                Sign in with Steam
            </a>
        </div>
    </main>
</template>
