# Design direction

The colours and accent rules are fixed in `CLAUDE.md` section 5. This file covers everything else: type, spacing,
layout and component conventions. If the two disagree, `CLAUDE.md` wins.

Proposed with the `frontend-design` plugin at the start of Phase 1. The `impeccable` critique runs at the end of
each UI phase.

## The idea: a leaderboard for your own shelf

nextup answers one question, "what should I play next?", with a ranked list. The visual language comes from
leaderboards and stat sheets: big rank numerals, a ladder of rows, and a stat block that shows why each pick ranks
where it does. That gives the product one memorable element, **the rank numerals**, and everything around them
stays quiet: plain rows, thin dividers, readable body text.

Spend boldness in that one place. If a screen gets a second loud element, remove one.

## Type

All three faces use the SIL Open Font License. They are self-hosted as subset (Latin) `woff2` files with
`font-display: swap`, and no font CDN is used.

| Role    | Face                                             | Used for                                              |
| ------- | ------------------------------------------------ | ----------------------------------------------------- |
| Display | **Big Shoulders Display** (variable, 600 to 800) | Rank numerals, page titles, the top pick's game title |
| Body    | **Atkinson Hyperlegible Next** (400, 600)        | Everything else: reasons, tables, controls, help text |
| Numbers | **Atkinson Hyperlegible Mono** (400)             | Numeric values only: scores, hours, counts            |

Why these: Big Shoulders is a condensed face drawn from stadium and scoreboard signage, which matches the
leaderboard idea and lets large numerals sit beside a title without taking the whole row. Atkinson Hyperlegible
was designed for low-vision readers, and the explanations are the product, so they have to be easy to read. The
mono comes from the same family as the body, so numbers line up in columns without looking like a different
voice.

Rules:

- Mono is for **values**, never labels. "Playtime" is body text; "142 h" is mono.
- Sentence case everywhere. No all-caps labels, and no eyebrow text above headings.
- Units are separated by a space and set in `text-muted`: `142` `h`.
- Body text measure is 65ch at most. Reasons wrap; they are never truncated on the top pick.

### Scale (rem, about a 1.25 ratio)

| Token          | Size / line height | Face        | Use                              |
| -------------- | ------------------ | ----------- | -------------------------------- |
| `text-rank-xl` | 7.5 / 0.85         | Display 800 | Top pick rank numeral            |
| `text-rank`    | 3 / 0.9            | Display 700 | Ranked list numerals             |
| `text-title`   | 2.25 / 1.05        | Display 700 | Page titles, top pick game title |
| `text-heading` | 1.25 / 1.3         | Body 600    | Section headings                 |
| `text-base`    | 1 / 1.6            | Body 400    | Body, reasons                    |
| `text-sm`      | 0.875 / 1.5        | Body 400    | Table cells, tags, help text     |

Define these in the Tailwind `@theme` as `--text-*` and `--font-display`, `--font-sans`, `--font-mono`.

## Spacing and shape

- A 4px base, using the Tailwind default scale. Stick to the steps 1, 2, 3, 4, 6, 8, 12 and 16.
- Page gutter: 16px on mobile, 32px from `md`. Content max width is 72rem, left aligned. Nothing is centred
  except empty states.
- Radius follows hierarchy rather than one value for everything:
    - 2px for tags and inputs.
    - 6px for buttons and panels.
    - 10px for the top pick card only.
- No drop shadows on structure. Depth comes from `surface` and `surface-2`. The only glow is the accent glow on the
  top pick card, as CLAUDE.md allows.
- Dividers use `border` (decorative). Anything you can operate uses `border-strong`.

## Layout

### App shell

The starter kit's sidebar is replaced with a top bar. The app has two main views, so a sidebar wastes width
the library table needs.

```
┌──────────────────────────────────────────────────────────────┐
│ nextup      Play next   Library                    (avatar ▾)│  surface, border-b border
└──────────────────────────────────────────────────────────────┘
```

The active nav item is marked with a 2px accent underline and `aria-current="page"`. That is a selected state,
so the accent is allowed.

### Play next (recommendations)

The top pick is the hero of the app. The rest is a ladder of rows, not a grid of cards.

```
┌────────────────────────────────────────────────────────────────┐
│  ┌──────────┐   1                                               │  surface, radius 10,
│  │  cover   │   Hades II                                        │  accent border + glow
│  │  art     │   Because you put 140 hours into Hades and Dead   │
│  │ 600x900  │   Cells: roguelike, short runs, and two friends   │
│  └──────────┘   played it this week.                            │
│                 Taste match   ██████████░░  0.82                 │  bars: accent fill here only
│                 Momentum      ██████░░░░░░  0.51                 │
│                 Fits tonight  ████████░░░░  0.67                 │
│                 Friends       ███░░░░░░░░░  0.25                 │
│                 [ Play on Steam ]  [ Not tonight ]               │  one primary, one secondary
└────────────────────────────────────────────────────────────────┘

 2   [cov] Slay the Spire       Short runs and you played Balatro…     78
 3   [cov] Outer Wilds          Owned 3 years, never started; puzzle…  74
 4   [cov] Disco Elysium        Story-heavy, like your top three…      71
─────────────────────────────────────────────────────────────────  border dividers
```

- Rank numerals sit in a fixed-width column so the titles align. In the list they are `text-text-muted`. Only the top
  pick's numeral is `text-text` at full size.
- On the top pick, signal bars are `accent` on a `surface-2` track. In the list there are no bars, just one score
  in mono. Every bar shows its numeric value, so meaning never depends on colour alone.
- Mobile: the top pick stacks (cover above, full width at 2:3 capped at 320px), and list rows drop the reason to a
  second line.

### Library

A dense, sortable table, the one place with real data density.

```
 Search your library [__________]   Status: (All) Unplayed  Recent  Played     312 games

 Game ▲                         Playtime     Last played      Status
 ───────────────────────────────────────────────────────────────────
 [ic] Hades                       140 h      3 days ago       ● Recent
 [ic] Stardew Valley               86 h      8 months ago     ◐ Played
 [ic] Outer Wilds                   0 h      Never            ○ Unplayed
```

- Sorting uses real `<button>`s in the `<th>`, with `aria-sort`. The default sort is last played, newest first.
- Numbers are right-aligned mono. Last played is relative, with the absolute date in a `<time datetime>` and a
  `title`.
- Status uses a shape and a word (○ ◐ ●) in `text-muted`. It is never colour alone and never accent.
- The status filter is a segmented control: buttons with `aria-pressed`, and the selected one is `surface-2` with a
  `border-strong` outline. It is a filter, not an action, so no accent.
- The header row is sticky. Row hover is `surface-2`.
- Mobile: the table becomes a list. Each row shows the name on line one, and playtime, last played and status on
  line two.

### Empty, loading and error states

These states give direction, not mood:

- Importing: "Importing your library. This usually takes under a minute." Show a progress line in `text-muted`
  and the count found so far.
- Private library: "Your Steam game details are private, so nextup can't see your games." Follow with the steps
  to change it and a link to Steam's privacy settings, plus a "Check again" secondary button.
- Empty search: "No games match "zelda"." and a "Clear search" link.

## Components

| Component        | Convention                                                                                                                                                                       |
| ---------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Primary button   | `bg-accent text-accent-ink`, radius 6, body 600. One per view. Label says what happens ("Sign in with Steam", "Play on Steam"). No trailing arrows.                              |
| Secondary button | `bg-surface-2 text-text border border-border-strong`.                                                                                                                            |
| Ghost button     | Text only, `text-text-muted`, with `hover:text-text`. For tertiary actions in rows.                                                                                              |
| Focus            | `outline-2 outline-offset-2 outline-accent` on every interactive element, via `:focus-visible`.                                                                                  |
| Input            | `bg-bg border border-border-strong`, radius 2, placeholder `text-text-muted`.                                                                                                    |
| Tag              | `bg-surface-2 text-text-muted text-sm`, radius 2, sentence case. Maximum of 4 shown, then "+3".                                                                                  |
| Cover art        | Always has `alt` set to the game name, `loading="lazy"` except the top pick, explicit width and height to prevent CLS, and a `surface-2` placeholder with the initials on error. |
| Numbers          | `font-mono tabular-nums`, with the unit in `text-text-muted`.                                                                                                                    |

## Motion

- The GSAP hero (CLAUDE.md section 6) is the single orchestrated moment.
- Everywhere else, motion only answers an action: 150ms colour transitions on hover and focus, and a 200ms
  height and opacity reveal when expanding a row. No entrance animations on sections or cards.
- `prefers-reduced-motion: reduce` turns off all transitions except instant state changes.

## Checklist for each UI task

- Only token colours, no hex in components.
- Accent covers about a tenth of the view or less (squint test). One primary button.
- Mono only on numbers. No all-caps, no eyebrows, no middle-dot meta strings.
- Keyboard walkthrough: every control reachable, visible focus, and sort and filter state announced.
- Works at 360px wide with no horizontal page scroll (the library table becomes a list).
