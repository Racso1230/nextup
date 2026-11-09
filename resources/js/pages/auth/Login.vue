<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
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
            <Button variant="secondary" class="w-full" disabled>
                Sign in with Steam
            </Button>
        </div>
    </main>
</template>
