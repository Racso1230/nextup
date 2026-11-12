import { gsap } from 'gsap';
import { onBeforeUnmount, onMounted } from 'vue';
import type { Ref } from 'vue';

/**
 * The landing hero sequence (CLAUDE.md section 6), about three seconds.
 *
 * The server-rendered HTML is already the final state, so crawlers, no-JS visitors and reduced-motion
 * visitors see it as it is. Every tween here is a `from`, created in onMounted. Only x, y, rotation and
 * opacity move, plus scaleX on the origin-left bars.
 *
 * Avoiding a flash of the final state: hydration takes a moment, and in that gap the server HTML would show
 * the finished hero before the `from` tweens snap it hidden. So app.blade.php adds a `js-motion` class to
 * <html> (only when JavaScript runs AND motion is allowed) and app.css hides the animated hero parts under
 * it. This composable removes the class first, so GSAP reads the real end values, and its `from` tweens hide
 * the same parts again in the same frame, then reveal them. A 1.5s timer in app.blade.php removes the class
 * too, so a slow or failed load never leaves the hero hidden.
 *
 * Reveal order: headline words rise; the four covers (the card's and the three ladder rows') start
 * shuffled out of order and settle into rank order; the card, with its explanation, fades in; the glow
 * fades in; the bars fill.
 */

const COVER_SELECTOR = '[data-hero-cover]';

export function useHeroAnimation(root: Ref<HTMLElement | null>) {
    let ctx: gsap.Context | undefined;
    let mm: gsap.MatchMedia | undefined;

    onMounted(() => {
        const el = root.value;

        if (!el) {
            document.documentElement.classList.remove('js-motion');

            return;
        }

        ctx = gsap.context(() => {
            mm = gsap.matchMedia();
            mm.add('(prefers-reduced-motion: no-preference)', () => {
                // Reveal first so the cover positions below are measured in their final places.
                document.documentElement.classList.remove('js-motion');

                const covers = Array.from(
                    el.querySelectorAll<HTMLElement>(COVER_SELECTOR),
                );
                const rects = covers.map((cover) =>
                    cover.getBoundingClientRect(),
                );
                // Cover i starts in the place of cover SHUFFLE[i]: out of rank order, then it settles.
                const shuffle = [2, 0, 3, 1];
                const offset = (i: number, axis: 'left' | 'top'): number => {
                    const from = rects[shuffle[i] % rects.length];
                    const own = rects[i];

                    return from && own ? from[axis] - own[axis] : 0;
                };

                const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });

                // 1. Headline words rise out of their masks.
                tl.from('[data-hero-word]', {
                    yPercent: 110,
                    opacity: 0,
                    duration: 0.8,
                    stagger: 0.08,
                });

                // 2. The covers shuffle and decelerate into rank order. The card and rows fade in
                // quickly around them (a child cannot show before its parent does).
                tl.from(
                    covers,
                    {
                        x: (i: number) => offset(i, 'left'),
                        y: (i: number) => offset(i, 'top'),
                        rotation: (i: number) => (i % 2 === 0 ? -24 : 28),
                        duration: 1.4,
                        ease: 'power4.out',
                        stagger: 0.1,
                    },
                    0.4,
                );
                // The explanation is part of the card, so it arrives with it rather than last.
                tl.from(
                    '[data-hero-card]',
                    { opacity: 0, y: 24, duration: 0.7 },
                    0.5,
                );
                tl.from(
                    '[data-hero-row]',
                    { opacity: 0, y: 12, duration: 0.6, stagger: 0.1 },
                    0.8,
                );
                // The glow is its own layer, so only its opacity moves.
                tl.from(
                    '[data-hero-glow]',
                    { opacity: 0, duration: 0.8, ease: 'power2.inOut' },
                    1.3,
                );

                // 3. The bars fill to their values.
                tl.from(
                    '[data-hero-bar]',
                    { scaleX: 0, duration: 0.9, stagger: 0.1 },
                    1.2,
                );
            });
        }, el);

        // Reduced motion (or no match): nothing was animated, so make sure nothing stays hidden.
        document.documentElement.classList.remove('js-motion');
    });

    onBeforeUnmount(() => {
        mm?.revert();
        ctx?.revert();
    });
}
