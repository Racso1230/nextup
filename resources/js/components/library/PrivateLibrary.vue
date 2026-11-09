<script setup lang="ts">
import ImportButton from '@/components/library/ImportButton.vue';
import type { PrivacyInfo } from '@/types/library';

defineProps<{ privacy: PrivacyInfo }>();
</script>

<template>
    <section class="flex max-w-[65ch] flex-col gap-4">
        <h2 class="text-heading font-semibold text-text">
            Your Steam game details are private, so nextup can't see your games.
        </h2>
        <p class="text-text-muted">To change that on Steam:</p>
        <ol
            class="list-decimal space-y-2 pl-6 text-text marker:text-text-muted"
        >
            <li v-for="step in privacy.steps" :key="step">{{ step }}</li>
        </ol>
        <div class="flex flex-wrap items-center gap-4">
            <a
                :href="privacy.settings_url"
                target="_blank"
                rel="noopener noreferrer"
                class="rounded-[2px] text-text underline underline-offset-4 hover:text-text-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
            >
                Open Steam privacy settings<span class="sr-only">
                    (opens in a new tab)</span
                >
            </a>
            <ImportButton>Check again</ImportButton>
        </div>
    </section>
</template>
