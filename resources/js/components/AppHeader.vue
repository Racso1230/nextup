<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useInitials } from '@/composables/useInitials';
import { library } from '@/routes';

type NavLink = {
    title: string;
    /** Absent while the page does not exist yet. */
    href?: string;
};

const navItems: NavLink[] = [
    { title: 'Play next (soon)' },
    { title: 'Library', href: library().url },
];

const page = usePage();
const user = computed(() => page.props.auth.user);
const { isCurrentOrParentUrl } = useCurrentUrl();
const { getInitials } = useInitials();

const focusRing =
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
</script>

<template>
    <header class="border-b border-border bg-surface">
        <div
            class="flex flex-wrap items-center justify-between gap-x-6 px-4 md:px-8"
        >
            <Link
                :href="library()"
                class="rounded-md py-3 font-display text-title leading-none font-bold text-text"
                :class="focusRing"
            >
                nextup
            </Link>

            <!-- Narrow screens: the nav wraps onto a second row, no extra JS. -->
            <nav
                aria-label="Main"
                class="order-last flex w-full gap-6 sm:order-none sm:w-auto sm:flex-1"
            >
                <template v-for="item in navItems" :key="item.title">
                    <Link
                        v-if="item.href"
                        :href="item.href"
                        :aria-current="
                            isCurrentOrParentUrl(item.href) ? 'page' : undefined
                        "
                        class="relative flex h-10 items-center rounded-md text-text sm:h-14"
                        :class="focusRing"
                    >
                        {{ item.title }}
                        <span
                            v-if="isCurrentOrParentUrl(item.href)"
                            class="absolute inset-x-0 bottom-0 h-0.5 bg-accent"
                            aria-hidden="true"
                        />
                    </Link>
                    <span
                        v-else
                        aria-disabled="true"
                        class="flex h-10 items-center text-text-muted sm:h-14"
                    >
                        {{ item.title }}
                    </span>
                </template>
            </nav>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-md p-1 text-text hover:bg-surface-2"
                        :class="focusRing"
                    >
                        <Avatar class="size-8">
                            <AvatarImage
                                v-if="user.avatar_url"
                                :src="user.avatar_url"
                                alt=""
                            />
                            <AvatarFallback class="bg-surface-2 text-sm">
                                {{ getInitials(user.persona_name) }}
                            </AvatarFallback>
                        </Avatar>
                        <span class="sr-only sm:not-sr-only">{{
                            user.persona_name
                        }}</span>
                        <ChevronDown
                            class="size-4 text-text-muted"
                            aria-hidden="true"
                        />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
