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
            class="w-full max-w-sm space-y-6 rounded-lg border border-border-strong bg-surface p-8 text-center"
        >
            <h1 class="text-xl font-medium">Sign in to nextup</h1>
            <p class="text-sm text-text-muted">
                nextup reads your Steam library, so Steam is the only way to
                sign in.
            </p>
            <!-- A plain link: this is a full-page redirect off-site, not an Inertia visit. -->
            <a
                :href="steam.url()"
                class="inline-flex h-10 w-full items-center justify-center rounded-md bg-accent px-6 text-sm font-medium text-accent-ink outline-offset-2 hover:opacity-90 focus-visible:outline-2 focus-visible:outline-accent"
            >
                Sign in with Steam
            </a>
            <p class="text-sm text-text-muted">
                nextup only reads your public Steam profile and library. It
                never sees your password.
            </p>
        </div>
    </main>
</template>
