<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CoverArt from '@/components/landing/CoverArt.vue';
import TryDemoButton from '@/components/TryDemoButton.vue';
import { useHeroAnimation } from '@/composables/useHeroAnimation';
import { home, login, playNext } from '@/routes';
import { steam } from '@/routes/auth';
import type { HeroPick } from '@/types/hero';

defineProps<{ hero: HeroPick }>();

const page = usePage();
const signedIn = computed(() => Boolean(page.props.auth.user));

const headline = 'What should you play tonight?'.split(' ');

const heroRoot = ref<HTMLElement | null>(null);
useHeroAnimation(heroRoot);

// Final resting places for the cover stack. The animation starts elsewhere and lands here.
const nextStyle = (index: number) => ({
    transform: `translate(${64 + index * 36}px, ${index % 2 === 0 ? 0 : 14}px) rotate(${(index + 1) * 4}deg)`,
    zIndex: 4 - index,
});

const ranking = [
    {
        title: 'Taste',
        text: 'Games like the ones you put hours into rank higher. Hours say more about what you enjoy than a star rating does.',
    },
    {
        title: 'Momentum',
        text: 'Something you started recently and left half-played beats something you gave up on years ago.',
    },
    {
        title: 'Fits tonight',
        text: 'Say whether you have an hour or an evening and the list favours games that fit.',
    },
    {
        title: 'Friends',
        text: 'Games your friends are playing get a small nudge. They are counted, never named.',
    },
];

const focusRing =
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
const link = `rounded-sm text-text underline underline-offset-4 hover:text-text-muted ${focusRing}`;

const topBarLink = computed(() =>
    signedIn.value
        ? { href: playNext().url, label: 'Play next' }
        : { href: login().url, label: 'Sign in' },
);
</script>

<template>
    <Head title="What should you play tonight?" />

    <div class="flex min-h-svh flex-col bg-bg text-text">
        <a
            href="#content"
            class="sr-only rounded-md border border-border-strong bg-surface-2 px-4 py-2 text-text focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50"
        >
            Skip to content
        </a>

        <header class="border-b border-border">
            <div
                class="mx-auto flex w-full max-w-[72rem] items-center justify-between px-4 md:px-8"
            >
                <Link
                    :href="home()"
                    class="rounded-md py-3 font-display text-2xl leading-none font-bold text-text"
                    :class="focusRing"
                >
                    nextup
                </Link>
                <Link :href="topBarLink.href" :class="link">
                    {{ topBarLink.label }}
                </Link>
            </div>
        </header>

        <main
            id="content"
            tabindex="-1"
            class="mx-auto w-full max-w-[72rem] flex-1 px-4 focus:outline-none md:px-8"
        >
            <section
                ref="heroRoot"
                class="grid gap-12 py-12 md:py-20 lg:grid-cols-[1fr_28rem] lg:items-center lg:gap-16"
            >
                <div class="flex flex-col items-start gap-6">
                    <h1
                        class="font-display text-[3.5rem] leading-[0.95] font-extrabold sm:text-[5rem]"
                    >
                        <template v-for="(word, i) in headline" :key="i">
                            <span
                                class="-mb-[0.1em] inline-block overflow-hidden pb-[0.1em] align-bottom"
                                ><span data-hero-word class="inline-block">{{
                                    word
                                }}</span></span
                            >{{ ' ' }}
                        </template>
                    </h1>
                    <p class="max-w-[48ch] text-heading text-text-muted">
                        nextup ranks the games you already own and explains
                        every pick, so choosing tonight's game takes a minute
                        instead of an hour of scrolling.
                    </p>
                    <div
                        class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row"
                    >
                        <!-- A plain link: this is a full-page redirect off-site, not an Inertia visit. -->
                        <a
                            :href="steam.url()"
                            class="inline-flex h-10 items-center justify-center rounded-md bg-accent px-6 text-sm font-semibold text-accent-ink hover:opacity-90"
                            :class="focusRing"
                        >
                            Sign in with Steam
                        </a>
                        <TryDemoButton class="sm:w-auto" />
                    </div>
                    <p class="text-sm text-text-muted">
                        The demo uses a sample library and needs no account.
                    </p>
                </div>

                <div class="flex flex-col gap-6">
                    <div class="relative h-72 sm:h-80">
                        <div
                            v-for="(next, i) in hero.next"
                            :key="next.name"
                            data-hero-next
                            class="absolute top-6 left-0 w-28 sm:w-32"
                            :style="nextStyle(i)"
                        >
                            <CoverArt :name="next.name" :url="next.cover_url" />
                        </div>
                        <div
                            data-hero-top
                            class="absolute top-0 left-0 z-10 w-36 sm:w-44"
                        >
                            <CoverArt :name="hero.name" :url="hero.cover_url" />
                        </div>
                    </div>

                    <div class="relative">
                        <!-- The glow lives on its own layer so only opacity animates. -->
                        <div
                            data-hero-glow
                            aria-hidden="true"
                            class="pointer-events-none absolute inset-0 rounded-[10px] shadow-[0_0_48px_-8px_var(--color-accent)]"
                        ></div>
                        <div
                            data-hero-card
                            class="relative flex flex-col gap-4 rounded-[10px] border border-accent bg-surface p-4 sm:p-6"
                        >
                            <div class="flex items-end gap-4">
                                <span
                                    class="font-display text-rank-xl font-extrabold text-text"
                                    ><span class="sr-only">Rank </span>1</span
                                >
                                <h2
                                    class="min-w-0 pb-2 font-display text-title font-bold break-words text-text"
                                >
                                    {{ hero.name }}
                                </h2>
                            </div>
                            <p data-hero-explanation class="text-text">
                                {{ hero.explanation }}
                            </p>
                            <ul class="flex flex-col gap-2">
                                <li
                                    v-for="signal in hero.signals"
                                    :key="signal.key"
                                    class="flex items-center gap-3"
                                >
                                    <span
                                        class="w-28 shrink-0 text-sm text-text-muted"
                                        >{{ signal.label }}</span
                                    >
                                    <div
                                        class="h-2 min-w-0 flex-1 overflow-hidden rounded-[2px] bg-surface-2"
                                    >
                                        <div
                                            data-hero-bar
                                            class="h-full w-full origin-left bg-accent"
                                            :style="{
                                                transform: `scaleX(${signal.value})`,
                                            }"
                                        ></div>
                                    </div>
                                    <span
                                        class="w-10 shrink-0 text-right font-mono text-sm text-text tabular-nums"
                                        >{{ signal.value.toFixed(2) }}</span
                                    >
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <section
                aria-labelledby="ranking-heading"
                class="border-t border-border py-12 md:py-16"
            >
                <h2
                    id="ranking-heading"
                    class="mb-8 font-display text-title font-bold"
                >
                    How nextup ranks your games
                </h2>
                <dl class="grid gap-8 sm:grid-cols-2">
                    <div v-for="item in ranking" :key="item.title">
                        <dt class="text-heading font-semibold">
                            {{ item.title }}
                        </dt>
                        <dd class="mt-1 max-w-[50ch] text-text-muted">
                            {{ item.text }}
                        </dd>
                    </div>
                </dl>
                <p class="mt-8 max-w-[65ch] text-text-muted">
                    Every pick comes with its reason in plain English and the
                    numbers behind it, like the sample above.
                </p>
            </section>

            <section
                aria-labelledby="demo-heading"
                class="border-t border-border py-12 md:py-16"
            >
                <h2
                    id="demo-heading"
                    class="mb-4 font-display text-title font-bold"
                >
                    Try it without Steam
                </h2>
                <p class="mb-6 max-w-[65ch] text-text-muted">
                    The demo gives you a sample library, ranked and explained by
                    the same engine. Browse it, mark picks as not tonight, and
                    see how the list changes. It needs no account and is deleted
                    after 24 hours.
                </p>
                <TryDemoButton class="sm:w-auto" />
            </section>

            <section
                aria-labelledby="privacy-heading"
                class="border-t border-border py-12 md:py-16"
            >
                <h2
                    id="privacy-heading"
                    class="mb-4 font-display text-title font-bold"
                >
                    What nextup sees
                </h2>
                <ul
                    class="flex max-w-[65ch] list-disc flex-col gap-2 pl-5 text-text-muted"
                >
                    <li>
                        It reads only your public Steam profile and game
                        library, and never your password. Steam handles the sign
                        in.
                    </li>
                    <li>
                        Friends are counted, never named. Nobody sees who played
                        what.
                    </li>
                    <li>
                        You can delete everything nextup holds about you in one
                        click.
                    </li>
                </ul>
            </section>
        </main>

        <footer class="border-t border-border">
            <div
                class="mx-auto flex w-full max-w-[72rem] flex-col gap-3 px-4 py-8 text-sm text-text-muted md:px-8"
            >
                <p>
                    Game data and cover art from
                    <a href="https://www.igdb.com" rel="noopener" :class="link"
                        >IGDB</a
                    >. Library and friend data from the Steam Web API. nextup is
                    not affiliated with Valve or Twitch.
                </p>
                <p class="flex flex-wrap gap-x-6 gap-y-2">
                    <a
                        href="https://github.com/Racso1230/nextup"
                        rel="noopener"
                        :class="link"
                        >Source on GitHub</a
                    >
                    <a
                        href="https://ojfdigital.co.uk"
                        rel="noopener"
                        :class="link"
                        >Built by Oscar at OJF Digital</a
                    >
                </p>
            </div>
        </footer>
    </div>
</template>
