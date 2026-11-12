<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { useDebounceFn, useEventListener } from '@vueuse/core';
import { computed, nextTick, ref, watch } from 'vue';
import ImportButton from '@/components/library/ImportButton.vue';
import LibraryTable from '@/components/library/LibraryTable.vue';
import PrivateLibrary from '@/components/library/PrivateLibrary.vue';
import { formatDate, formatRelative } from '@/lib/format';
import { library } from '@/routes';
import type {
    ImportInfo,
    LibraryFilters,
    LibraryPage,
    LibraryStatusFilter,
    PrivacyInfo,
    SortColumn,
    StatusCounts,
} from '@/types/library';

const props = defineProps<{
    entries: LibraryPage;
    filters: LibraryFilters;
    total: number;
    status_counts: StatusCounts;
    hidden_count: number;
    import: ImportInfo;
    privacy: PrivacyInfo | null;
}>();

// "import" is a reserved word in templates, so the template reads it through this alias.
const importInfo = computed(() => props.import);

const focusRing =
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
const secondaryButton =
    'h-9 rounded-md border border-border-strong bg-surface-2 px-4 text-sm font-medium text-text transition-colors duration-150 hover:bg-border';
const pageLink =
    'rounded-md border border-border-strong bg-surface-2 px-4 py-2 text-text transition-colors duration-150 hover:bg-border';

// --- Importing: poll until the job leaves queued/running. ---
const importing = computed(
    () => props.import.status === 'queued' || props.import.status === 'running',
);
// `privacy` is reloaded too, because an import can end as "private" while this page is open.
const { start, stop } = usePoll(
    3000,
    { only: ['entries', 'total', 'import', 'privacy'] },
    { autoStart: false },
);
watch(
    importing,
    (value) => {
        if (value) {
            start();
        } else {
            stop();
        }
    },
    { immediate: true },
);

const showPrivate = computed(() => !importing.value && props.privacy !== null);
const showFailed = computed(
    () => !importing.value && props.import.status === 'failed',
);
const hasEntries = computed(() => props.total > 0);
const showEmpty = computed(
    () =>
        !hasEntries.value &&
        !importing.value &&
        !showPrivate.value &&
        !showFailed.value,
);
const gamesLabel = computed(() => (props.total === 1 ? 'game' : 'games'));
const importedAt = computed(() => props.import.imported_at);

// --- Filters: every change is an Inertia GET back to this page, from page 1. ---
const search = ref(props.filters.q ?? '');

// Only user-initiated visits (search, filter, sort) go through here, so only they are announced.
function visit(overrides: Partial<LibraryFilters>): void {
    const next = { ...props.filters, ...overrides };
    const query: Record<string, string> = {
        sort: next.sort,
        direction: next.direction,
    };

    if (next.status) {
        query.status = next.status;
    }

    if (next.q) {
        query.q = next.q;
    }

    router.get(library().url, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: announce,
    });
}

// Live region: announces the result of a user-initiated change, never the initial load or a poll.
const announcement = ref('');

function announce(): void {
    void nextTick(() => {
        const count = props.entries.total;
        announcement.value = `${count} ${count === 1 ? 'game' : 'games'} shown, sorted by ${props.filters.sort.replace('_', ' ')}, ${props.filters.direction === 'asc' ? 'ascending' : 'descending'}.`;
    });
}

function visitSearch(): void {
    const q = search.value.trim();

    if (q !== (props.filters.q ?? '')) {
        visit({ q: q === '' ? null : q });
    }
}

const debouncedSearch = useDebounceFn(visitSearch, 300);
watch(search, () => {
    void debouncedSearch();
});

// Follow the server if the URL changed under us (back button).
watch(
    () => props.filters.q,
    (q) => {
        if (search.value.trim() !== (q ?? '')) {
            search.value = q ?? '';
        }
    },
);

const searchInput = ref<HTMLInputElement | null>(null);

function clearSearch(): void {
    search.value = '';
    visitSearch();
}

function clearSearchAndFocus(): void {
    clearSearch();
    searchInput.value?.focus();
}

// "/" jumps to the search, unless the user is already typing somewhere.
useEventListener(window, 'keydown', (event: KeyboardEvent) => {
    if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) {
        return;
    }

    const target = event.target;

    if (
        target instanceof HTMLElement &&
        (target.isContentEditable ||
            ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))
    ) {
        return;
    }

    event.preventDefault();
    searchInput.value?.focus();
});

function countLabel(count: number): string {
    return `${count} ${count === 1 ? 'game' : 'games'}`;
}

function setStatus(status: LibraryStatusFilter | null): void {
    visit({ status });
}

function sortBy(column: SortColumn): void {
    if (props.filters.sort === column) {
        visit({
            direction: props.filters.direction === 'asc' ? 'desc' : 'asc',
        });
    } else {
        visit({
            sort: column,
            direction:
                column === 'name' || column === 'status' ? 'asc' : 'desc',
        });
    }
}

const baseStatusOptions: {
    value: LibraryStatusFilter | null;
    label: string;
}[] = [
    { value: null, label: 'All' },
    { value: 'unplayed', label: 'Unplayed' },
    { value: 'recent', label: 'Recent' },
    { value: 'played', label: 'Played' },
];
// Hidden only appears once something is hidden. Stay visible while filtered to it, so it can be left.
const statusOptions = computed(() =>
    props.hidden_count > 0 || props.filters.status === 'hidden'
        ? [...baseStatusOptions, { value: 'hidden' as const, label: 'Hidden' }]
        : baseStatusOptions,
);

function countFor(value: LibraryStatusFilter | null): number {
    return value === 'hidden'
        ? props.hidden_count
        : props.status_counts[value ?? 'all'];
}
</script>

<template>
    <Head title="Library" />

    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
            <h1 class="font-display text-title font-bold text-text">Library</h1>
            <p v-if="hasEntries" class="text-text-muted">
                <span class="font-mono tabular-nums">{{ total }}</span>
                {{ gamesLabel }}
            </p>
        </div>

        <div
            v-if="importing"
            role="status"
            class="flex max-w-[65ch] flex-col gap-1 rounded-md border border-border bg-surface px-4 py-3"
        >
            <p class="text-text">
                Importing your library. This usually takes under a minute.
            </p>
            <p class="text-sm text-text-muted">
                <template v-if="total > 0">
                    <span class="font-mono tabular-nums">{{ total }}</span>
                    {{ gamesLabel }} found so far.
                </template>
                <template v-else>Contacting Steam.</template>
            </p>
        </div>

        <PrivateLibrary v-if="showPrivate && privacy" :privacy="privacy" />

        <section
            v-else-if="showFailed"
            class="flex max-w-[65ch] flex-col items-start gap-4"
        >
            <p class="text-text">
                We couldn't import your library from Steam. This is usually
                temporary.
            </p>
            <ImportButton>Try again</ImportButton>
        </section>

        <section
            v-else-if="showEmpty"
            class="flex max-w-[65ch] flex-col items-start gap-4"
        >
            <p v-if="importInfo.status === 'done'" class="text-text">
                Your Steam library is empty, so there's nothing to rank yet.
            </p>
            <p v-else class="text-text">
                Your library hasn't been imported yet.
            </p>
            <ImportButton>
                {{
                    importInfo.status === 'done'
                        ? 'Refresh library'
                        : 'Import library'
                }}
            </ImportButton>
        </section>

        <template v-if="hasEntries">
            <div class="flex flex-col gap-2">
                <div
                    class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3"
                >
                    <div class="relative w-full sm:w-72">
                        <label for="library-search" class="sr-only">
                            Search your library
                        </label>
                        <input
                            id="library-search"
                            ref="searchInput"
                            v-model="search"
                            type="search"
                            placeholder="Search your library"
                            autocomplete="off"
                            maxlength="100"
                            aria-keyshortcuts="/"
                            class="h-9 w-full rounded-[2px] border border-border-strong bg-bg pr-9 pl-3 text-sm text-text placeholder:text-text-muted [&::-webkit-search-cancel-button]:appearance-none"
                            :class="focusRing"
                        />
                        <button
                            v-if="search !== ''"
                            type="button"
                            aria-label="Clear search"
                            class="absolute inset-y-0 right-0 flex w-9 items-center justify-center rounded-[2px] text-text-muted hover:text-text"
                            :class="focusRing"
                            @click="clearSearchAndFocus"
                        >
                            <X class="size-4" aria-hidden="true" />
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                        <div
                            role="group"
                            aria-label="Filter by status"
                            class="flex flex-wrap gap-1"
                        >
                            <button
                                v-for="option in statusOptions"
                                :key="option.label"
                                type="button"
                                :aria-pressed="filters.status === option.value"
                                :aria-label="`${option.label}, ${countLabel(countFor(option.value))}`"
                                class="flex h-9 items-center gap-2 rounded-md border px-3 text-sm transition-colors duration-150"
                                :class="[
                                    focusRing,
                                    filters.status === option.value
                                        ? 'border-border-strong bg-surface-2 text-text'
                                        : 'border-transparent text-text-muted hover:text-text',
                                ]"
                                @click="setStatus(option.value)"
                            >
                                {{ option.label }}
                                <span
                                    class="font-mono text-text-muted tabular-nums"
                                    aria-hidden="true"
                                >
                                    {{ countFor(option.value) }}
                                </span>
                            </button>
                        </div>

                        <div
                            v-if="!importing && importInfo.status === 'done'"
                            class="flex items-center gap-3"
                        >
                            <ImportButton variant="ghost">
                                Refresh library
                            </ImportButton>
                            <p
                                v-if="importedAt"
                                class="text-sm text-text-muted"
                            >
                                Updated
                                <time
                                    :datetime="importedAt"
                                    :title="formatDate(importedAt)"
                                    >{{ formatRelative(importedAt) }}</time
                                >
                            </p>
                        </div>
                    </div>
                </div>

                <p class="text-sm text-text-muted">
                    Recent means played in the last two weeks.
                </p>
            </div>

            <!-- Announces the result of a search, sort, filter or page change to screen readers. -->
            <p class="sr-only" role="status" aria-live="polite">
                {{ announcement }}
            </p>

            <template v-if="entries.data.length > 0">
                <LibraryTable
                    :rows="entries.data"
                    :filters="filters"
                    @sort="sortBy"
                />

                <nav
                    v-if="entries.last_page > 1"
                    aria-label="Pagination"
                    class="flex items-center justify-between gap-4 text-sm"
                >
                    <Link
                        v-if="entries.prev_page_url"
                        :href="entries.prev_page_url"
                        rel="prev"
                        @success="announce"
                        :class="[pageLink, focusRing]"
                    >
                        Previous
                    </Link>
                    <span v-else aria-hidden="true" class="px-4 py-2"></span>

                    <span class="text-text-muted">
                        Page
                        <span class="font-mono tabular-nums">{{
                            entries.current_page
                        }}</span>
                        of
                        <span class="font-mono tabular-nums">{{
                            entries.last_page
                        }}</span>
                    </span>

                    <Link
                        v-if="entries.next_page_url"
                        :href="entries.next_page_url"
                        rel="next"
                        @success="announce"
                        :class="[pageLink, focusRing]"
                    >
                        Next
                    </Link>
                    <span v-else aria-hidden="true" class="px-4 py-2"></span>
                </nav>
            </template>

            <section
                v-else
                class="flex max-w-[65ch] flex-col items-start gap-4"
            >
                <p v-if="filters.q" class="text-text">
                    No games match "{{ filters.q }}".
                </p>
                <p v-else class="text-text">No games match this filter.</p>
                <button
                    v-if="filters.q"
                    type="button"
                    :class="[secondaryButton, focusRing]"
                    @click="clearSearch"
                >
                    Clear search
                </button>
                <button
                    v-else
                    type="button"
                    :class="[secondaryButton, focusRing]"
                    @click="setStatus(null)"
                >
                    Show all games
                </button>
            </section>
        </template>
    </div>
</template>
