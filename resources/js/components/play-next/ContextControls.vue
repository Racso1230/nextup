<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import SegmentedControl from '@/components/play-next/SegmentedControl.vue';
import { playNext } from '@/routes';
import type {
    PlayNextCompany,
    PlayNextFilters,
    PlayNextHours,
    PlayNextMood,
} from '@/types/play-next';

const props = defineProps<{ filters: PlayNextFilters }>();

const STORAGE_KEY = 'nextup.play-next.context';
const DEFAULT_HOURS: PlayNextHours = 2;

const hourOptions: { value: PlayNextHours; label: string }[] = [
    { value: 1, label: '1 hour' },
    { value: 2, label: '2 hours' },
    { value: 4, label: '4 hours' },
    { value: 6, label: 'All evening' },
];
const moodOptions: { value: PlayNextMood | null; label: string }[] = [
    { value: null, label: 'Any' },
    { value: 'chill', label: 'Chill' },
    { value: 'intense', label: 'Intense' },
];
const companyOptions: { value: PlayNextCompany | null; label: string }[] = [
    { value: null, label: 'Either' },
    { value: 'solo', label: 'Solo' },
    { value: 'friends', label: 'With friends' },
];

function visit(next: PlayNextFilters): void {
    const query: Record<string, string | number> = {};

    if (next.hours !== DEFAULT_HOURS) {
        query.hours = next.hours;
    }

    if (next.mood) {
        query.mood = next.mood;
    }

    if (next.with) {
        query.with = next.with;
    }

    router.get(playNext().url, query, {
        preserveScroll: true,
        replace: true,
    });
}

function remember(next: PlayNextFilters): void {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
    } catch {
        // Storage can be blocked; the page works without remembering.
    }
}

function change(overrides: Partial<PlayNextFilters>): void {
    const next = { ...props.filters, ...overrides };
    remember(next);
    visit(next);
}

function readStored(): PlayNextFilters | null {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        const data: unknown = raw === null ? null : JSON.parse(raw);

        if (typeof data !== 'object' || data === null) {
            return null;
        }

        const stored = data as Record<string, unknown>;
        const hours = hourOptions.find((o) => o.value === stored.hours);
        const mood = moodOptions.find((o) => o.value === stored.mood);
        const company = companyOptions.find((o) => o.value === stored.with);

        return {
            hours: hours?.value ?? DEFAULT_HOURS,
            mood: mood?.value ?? null,
            with: company?.value ?? null,
        };
    } catch {
        return null;
    }
}

// On a first visit with no choices in the URL, apply the remembered ones once.
onMounted(() => {
    const params = new URLSearchParams(window.location.search);

    if (['hours', 'mood', 'with'].some((key) => params.has(key))) {
        return;
    }

    const stored = readStored();

    if (
        stored &&
        (stored.hours !== props.filters.hours ||
            stored.mood !== props.filters.mood ||
            stored.with !== props.filters.with)
    ) {
        visit(stored);
    }
});
</script>

<template>
    <div class="flex flex-wrap gap-x-8 gap-y-4">
        <SegmentedControl
            label="How long have you got?"
            :options="hourOptions"
            :model-value="filters.hours"
            @select="(value) => change({ hours: value })"
        />
        <SegmentedControl
            label="Mood"
            :options="moodOptions"
            :model-value="filters.mood"
            @select="(value) => change({ mood: value })"
        />
        <SegmentedControl
            label="Playing"
            :options="companyOptions"
            :model-value="filters.with"
            @select="(value) => change({ with: value })"
        />
    </div>
</template>
