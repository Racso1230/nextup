<script setup lang="ts">
import { ChevronDown, ChevronUp } from '@lucide/vue';
import GameCover from '@/components/library/GameCover.vue';
import GameTags from '@/components/library/GameTags.vue';
import LastPlayed from '@/components/library/LastPlayed.vue';
import PlaytimeValue from '@/components/library/PlaytimeValue.vue';
import StatusLabel from '@/components/library/StatusLabel.vue';
import type { LibraryFilters, LibraryRow, SortColumn } from '@/types/library';

const props = defineProps<{
    rows: LibraryRow[];
    filters: LibraryFilters;
}>();

defineEmits<{ sort: [column: SortColumn] }>();

const focusRing =
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';

function ariaSort(column: SortColumn): 'ascending' | 'descending' | 'none' {
    if (props.filters.sort !== column) {
        return 'none';
    }

    return props.filters.direction === 'asc' ? 'ascending' : 'descending';
}
</script>

<template>
    <!-- md and up: a table. Below md: the same rows as a list. -->
    <table class="hidden w-full border-collapse text-sm md:table">
        <caption class="sr-only">
            Your Steam library
        </caption>
        <thead>
            <tr class="text-left">
                <th
                    v-for="column in [
                        { key: 'name', title: 'Game', align: 'left' },
                        { key: 'playtime', title: 'Playtime', align: 'right' },
                        {
                            key: 'last_played',
                            title: 'Last played',
                            align: 'left',
                        },
                        { key: 'status', title: 'Status', align: 'left' },
                    ] as const"
                    :key="column.key"
                    scope="col"
                    :aria-sort="ariaSort(column.key)"
                    class="sticky top-0 z-10 border-b border-border bg-bg px-3 py-2 font-semibold"
                    :class="column.align === 'right' ? 'text-right' : ''"
                >
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-[2px] py-1 hover:text-text"
                        :class="[
                            focusRing,
                            filters.sort === column.key
                                ? 'text-text'
                                : 'text-text-muted',
                        ]"
                        @click="$emit('sort', column.key)"
                    >
                        {{ column.title }}
                        <ChevronUp
                            v-if="
                                filters.sort === column.key &&
                                filters.direction === 'asc'
                            "
                            class="size-4"
                            aria-hidden="true"
                        />
                        <ChevronDown
                            v-else-if="filters.sort === column.key"
                            class="size-4"
                            aria-hidden="true"
                        />
                    </button>
                </th>
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="row in rows"
                :key="row.id"
                class="border-b border-border transition-colors duration-150 hover:bg-surface-2"
            >
                <td class="px-3 py-2">
                    <span class="flex items-center gap-3">
                        <GameCover
                            :name="row.name"
                            :cover-url="row.cover_url"
                            :icon-url="row.icon_url"
                        />
                        <span class="flex min-w-0 flex-col gap-1">
                            <span class="text-text">{{ row.name }}</span>
                            <GameTags :row="row" />
                        </span>
                    </span>
                </td>
                <td class="px-3 py-2 text-right">
                    <PlaytimeValue :minutes="row.playtime_minutes" />
                </td>
                <td class="px-3 py-2 text-text-muted">
                    <LastPlayed :at="row.last_played_at" />
                </td>
                <td class="px-3 py-2">
                    <StatusLabel :status="row.status" />
                </td>
            </tr>
        </tbody>
    </table>

    <ul class="md:hidden">
        <li
            v-for="row in rows"
            :key="row.id"
            class="flex gap-3 border-b border-border py-3"
        >
            <GameCover
                :name="row.name"
                :cover-url="row.cover_url"
                :icon-url="row.icon_url"
                size="md"
            />
            <div class="flex min-w-0 flex-1 flex-col gap-1">
                <span class="min-w-0 break-words text-text">{{
                    row.name
                }}</span>
                <GameTags :row="row" />
                <span
                    class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm"
                >
                    <PlaytimeValue :minutes="row.playtime_minutes" />
                    <span class="text-text-muted">
                        <LastPlayed :at="row.last_played_at" />
                    </span>
                    <StatusLabel :status="row.status" />
                </span>
            </div>
        </li>
    </ul>
</template>
