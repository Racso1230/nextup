import { gsap } from 'gsap';
import { onBeforeUnmount, onMounted } from 'vue';
import type { Ref } from 'vue';

/**
 * The landing hero sequence (CLAUDE.md section 6), about three seconds.
 *
 * The server-rendered HTML is already the final state. Every tween here is a `from`, created in
 * onMounted, so nothing is hidden before JavaScript runs. Only x, y, scale, rotation and opacity
 * move; the bars use scaleX on an origin-left element. Reduced motion skips it all.
 */
export function useHeroAnimation(root: Ref<HTMLElement | null>) {
    let ctx: gsap.Context | undefined;
    let mm: gsap.MatchMedia | undefined;

    onMounted(() => {
        if (!root.value) {
            return;
        }

        ctx = gsap.context(() => {
            mm = gsap.matchMedia();
            mm.add('(prefers-reduced-motion: no-preference)', () => {
                const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });

                // 1. Headline words rise out of their masks.
                tl.from('[data-hero-word]', {
                    yPercent: 110,
                    opacity: 0,
                    duration: 0.8,
                    stagger: 0.08,
                });

                // 2. The stack shuffles in and decelerates, top pick in front.
                tl.from(
                    '[data-hero-next]',
                    {
                        x: (i: number) => (i % 2 === 0 ? -120 : 160),
                        y: (i: number) => 60 - i * 30,
                        rotation: (i: number) => (i % 2 === 0 ? -40 : 40),
                        opacity: 0,
                        duration: 1.3,
                        ease: 'power4.out',
                        stagger: 0.12,
                    },
                    0.5,
                );
                tl.from(
                    '[data-hero-top]',
                    {
                        scale: 0.85,
                        y: 40,
                        opacity: 0,
                        duration: 1,
                        ease: 'power4.out',
                    },
                    1.1,
                );
                // The glow is its own layer, so only its opacity moves.
                tl.from(
                    '[data-hero-glow]',
                    { opacity: 0, duration: 0.8, ease: 'power2.inOut' },
                    1.8,
                );
                tl.from(
                    '[data-hero-card]',
                    { opacity: 0, y: 24, duration: 0.7 },
                    1.4,
                );

                // 3. The bars fill to their values, then the reason fades in.
                tl.from(
                    '[data-hero-bar]',
                    { scaleX: 0, duration: 0.9, stagger: 0.1 },
                    1.9,
                );
                tl.from(
                    '[data-hero-explanation]',
                    { opacity: 0, y: 8, duration: 0.7 },
                    2.3,
                );
            });
        }, root.value);
    });

    onBeforeUnmount(() => {
        mm?.revert();
        ctx?.revert();
    });
}
