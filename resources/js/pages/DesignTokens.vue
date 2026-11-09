<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

// Standalone page: render without the app shell.
defineOptions({ layout: [] });

interface Swatch {
    name: string;
    hex: string;
    role: string;
    swatch: string;
}

const swatches: Swatch[] = [
    { name: 'bg', hex: '#0B0F14', role: '60% canvas', swatch: 'bg-bg' },
    {
        name: 'surface',
        hex: '#151C26',
        role: '30% structure',
        swatch: 'bg-surface',
    },
    {
        name: 'surface-2',
        hex: '#1F2937',
        role: '30% structure, raised or hovered',
        swatch: 'bg-surface-2',
    },
    {
        name: 'border',
        hex: '#2B3646',
        role: '30% structure, decorative dividers only',
        swatch: 'bg-border',
    },
    {
        name: 'border-strong',
        hex: '#6B7A90',
        role: '30% structure, controls that must be seen',
        swatch: 'bg-border-strong',
    },
    { name: 'text', hex: '#E8EDF3', role: 'text', swatch: 'bg-text' },
    {
        name: 'text-muted',
        hex: '#9AA7B8',
        role: 'text, secondary',
        swatch: 'bg-text-muted',
    },
    {
        name: 'accent',
        hex: '#C6F432',
        role: '10% accent, actions and meaning',
        swatch: 'bg-accent',
    },
    {
        name: 'accent-ink',
        hex: '#0B0F14',
        role: '10% accent, text on accent',
        swatch: 'bg-accent-ink',
    },
    { name: 'success', hex: '#4ADE80', role: 'status', swatch: 'bg-success' },
    { name: 'warning', hex: '#FBBF24', role: 'status', swatch: 'bg-warning' },
    { name: 'danger', hex: '#F87171', role: 'status', swatch: 'bg-danger' },
];

interface Pair {
    label: string;
    ratio: string;
    classes: string;
}

const pairs: Pair[] = [
    { label: 'text on bg', ratio: '16.3', classes: 'bg-bg text-text' },
    {
        label: 'text on surface',
        ratio: '14.6',
        classes: 'bg-surface text-text',
    },
    { label: 'muted on bg', ratio: '7.9', classes: 'bg-bg text-text-muted' },
    {
        label: 'muted on surface',
        ratio: '7.0',
        classes: 'bg-surface text-text-muted',
    },
    {
        label: 'muted on surface-2',
        ratio: '6.0',
        classes: 'bg-surface-2 text-text-muted',
    },
    { label: 'accent on bg', ratio: '15.0', classes: 'bg-bg text-accent' },
    {
        label: 'accent-ink on accent',
        ratio: '15.0',
        classes: 'bg-accent text-accent-ink',
    },
    { label: 'success on bg', ratio: '11.0', classes: 'bg-bg text-success' },
    { label: 'warning on bg', ratio: '11.5', classes: 'bg-bg text-warning' },
    { label: 'danger on bg', ratio: '6.9', classes: 'bg-bg text-danger' },
];

interface Face {
    name: string;
    token: string;
    classes: string;
    role: string;
    sample: string;
}

const faces: Face[] = [
    {
        name: 'Big Shoulders Display',
        token: 'font-display',
        classes: 'font-display font-bold',
        role: 'Rank numerals, page titles, the top pick title',
        sample: '1 Hades II',
    },
    {
        name: 'Atkinson Hyperlegible Next',
        token: 'font-sans',
        classes: 'font-sans',
        role: 'Everything else: reasons, tables, controls, help text',
        sample: 'Short runs, and two friends played it this week.',
    },
    {
        name: 'Atkinson Hyperlegible Mono',
        token: 'font-mono',
        classes: 'font-mono tabular-nums',
        role: 'Numeric values only: scores, hours, counts',
        sample: '0.82  142  312',
    },
];

interface Step {
    token: string;
    spec: string;
    classes: string;
    sample: string;
}

const steps: Step[] = [
    {
        token: 'text-rank-xl',
        spec: '7.5rem / 0.85, display 800',
        classes: 'font-display text-rank-xl font-extrabold',
        sample: '1',
    },
    {
        token: 'text-rank',
        spec: '3rem / 0.9, display 700',
        classes: 'font-display text-rank font-bold text-text-muted',
        sample: '2',
    },
    {
        token: 'text-title',
        spec: '2.25rem / 1.05, display 700',
        classes: 'font-display text-title font-bold',
        sample: 'Hades II',
    },
    {
        token: 'text-heading',
        spec: '1.25rem / 1.3, body 600',
        classes: 'text-heading font-semibold',
        sample: 'Why this ranks first',
    },
    {
        token: 'text-base',
        spec: '1rem / 1.6, body 400',
        classes: 'text-base',
        sample: 'Because you put 140 hours into Hades and Dead Cells.',
    },
    {
        token: 'text-sm',
        spec: '0.875rem / 1.5, body 400',
        classes: 'text-sm',
        sample: 'Roguelike, short runs, co-op',
    },
];
</script>

<template>
    <Head title="Design tokens" />

    <main class="min-h-screen bg-bg px-6 py-10 text-text">
        <div class="mx-auto flex max-w-4xl flex-col gap-12">
            <header class="flex flex-col gap-2">
                <h1 class="text-3xl font-semibold">Design tokens</h1>
                <p class="text-text-muted">
                    The 60-30-10 colour system. Dark is the only theme.
                </p>
            </header>

            <section
                aria-labelledby="swatches-heading"
                class="flex flex-col gap-4"
            >
                <h2 id="swatches-heading" class="text-xl font-semibold">
                    Swatches
                </h2>
                <ul class="grid gap-4 sm:grid-cols-2">
                    <li
                        v-for="s in swatches"
                        :key="s.name"
                        class="flex items-center gap-4 rounded-lg border border-border bg-surface p-4"
                    >
                        <span
                            :class="s.swatch"
                            class="size-14 shrink-0 rounded-md border border-border-strong"
                            aria-hidden="true"
                        />
                        <div class="flex flex-col">
                            <span class="font-medium">{{ s.name }}</span>
                            <span class="font-mono text-sm text-text-muted">{{
                                s.hex
                            }}</span>
                            <span class="text-sm text-text-muted">{{
                                s.role
                            }}</span>
                        </div>
                    </li>
                </ul>
            </section>

            <section
                aria-labelledby="pairs-heading"
                class="flex flex-col gap-4"
            >
                <h2 id="pairs-heading" class="text-xl font-semibold">
                    Text pairs
                </h2>
                <ul class="grid gap-4 sm:grid-cols-2">
                    <li
                        v-for="p in pairs"
                        :key="p.label"
                        :class="p.classes"
                        class="flex items-baseline justify-between gap-4 rounded-lg border border-border p-4"
                    >
                        <span>{{ p.label }}</span>
                        <span class="font-mono text-sm">{{ p.ratio }} : 1</span>
                    </li>
                </ul>
            </section>

            <section aria-labelledby="type-heading" class="flex flex-col gap-6">
                <h2 id="type-heading" class="text-xl font-semibold">Type</h2>

                <ul class="flex flex-col gap-4">
                    <li
                        v-for="f in faces"
                        :key="f.token"
                        class="flex flex-col gap-1 rounded-lg border border-border bg-surface p-4"
                    >
                        <span class="text-sm text-text-muted"
                            >{{ f.name }} (<span class="font-mono">{{
                                f.token
                            }}</span
                            >)</span
                        >
                        <span :class="f.classes" class="text-title">{{
                            f.sample
                        }}</span>
                        <span class="text-sm text-text-muted">{{
                            f.role
                        }}</span>
                    </li>
                </ul>

                <ul class="flex flex-col">
                    <li
                        v-for="t in steps"
                        :key="t.token"
                        class="flex flex-col gap-1 border-b border-border py-4"
                    >
                        <span class="text-sm text-text-muted"
                            ><span class="font-mono">{{ t.token }}</span
                            >, {{ t.spec }}</span
                        >
                        <span :class="t.classes">{{ t.sample }}</span>
                    </li>
                </ul>

                <div class="flex items-end gap-4">
                    <span
                        class="font-display text-rank-xl font-extrabold"
                        aria-hidden="true"
                        >1</span
                    >
                    <p class="pb-2 text-base">
                        Playtime
                        <span class="font-mono tabular-nums">142</span>
                        <span class="text-text-muted">h</span>
                    </p>
                </div>
            </section>

            <section
                aria-labelledby="buttons-heading"
                class="flex flex-col gap-4"
            >
                <h2 id="buttons-heading" class="text-xl font-semibold">
                    Buttons
                </h2>
                <div class="flex flex-wrap gap-4">
                    <button
                        type="button"
                        class="rounded-md bg-accent px-4 py-2 font-medium text-accent-ink focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-bg focus-visible:outline-none"
                    >
                        Primary action
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-border-strong bg-surface-2 px-4 py-2 font-medium text-text focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-bg focus-visible:outline-none"
                    >
                        Secondary action
                    </button>
                </div>
            </section>
        </div>
    </main>
</template>
