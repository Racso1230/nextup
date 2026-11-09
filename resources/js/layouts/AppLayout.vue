<script setup lang="ts">
import AppHeader from '@/components/AppHeader.vue';
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <a
            href="#content"
            class="sr-only rounded-md border border-border-strong bg-surface-2 px-4 py-2 text-text focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50"
        >
            Skip to content
        </a>
        <AppHeader />
        <main
            id="content"
            tabindex="-1"
            class="w-full max-w-[72rem] flex-1 px-4 py-8 focus:outline-none md:px-8"
        >
            <slot />
        </main>
        <Toaster />
    </div>
</template>
