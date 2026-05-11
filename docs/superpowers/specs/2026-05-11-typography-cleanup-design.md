# Typography hierarchy + stray folder cleanup

**Date:** 2026-05-11
**Status:** Approved, ready for implementation plan

## Goal

Two-part housekeeping pass on the Champions League fixture frontend:

1. Delete the dead `@champions-league-fixture/` directory sitting at the repo root.
2. Fix the typography hierarchy across `LandingPage`, `LeaguePage`, and the dumb components in `libs/ui` so visual levels read correctly and treatments are consistent.

The current scaffold flattens the visual tree: section headings and card titles share the exact same Tailwind classes (`text-sm font-semibold uppercase tracking-wide text-muted-foreground`), and the same page title renders at two different sizes across the two pages. This makes nesting unreadable.

## Non-goals

- Full visual redesign. The aesthetic stays close to what is on screen today.
- Changing colors, spacing scale, dark mode, or component layouts.
- Touching backend, machines, services, or test infrastructure.
- Refactoring components that already use body-level typography correctly (`FixtureRow`, `WeekProgressBar`, `GameActionsBar`, `ErrorBanner`, `LoadingSpinner`, `TeamBadge`).

## Part 1 — Stray folder cleanup

### What is there

`/<repo>/@champions-league-fixture/ui/components/ui/{button,card,input,label,table}/` — a literal directory created at the repo root, mirroring the shadcn-vue primitives that live in `libs/ui/src/components/ui/`.

### Why it is safe to delete

- `libs/ui/package.json` declares `"name": "@champions-league-fixture/ui"`. Workspace name resolution sends every `@champions-league-fixture/ui/*` import to `libs/ui`, not to this folder.
- The stray folder contains only the shadcn primitives — no `TeamCard`, `StandingsTable`, `FixtureWeekCard`, etc. So the high-level imports in `LandingPage` and `LeaguePage` could never resolve to it anyway.
- `tsconfig.base.json` has no path alias pointing at it.

### Action

```
rm -rf @champions-league-fixture
```

Then verify: `nx build web` and `nx test web` stay green.

## Part 2 — Typography hierarchy

### Approach

Introduce a small set of semantic typography components in `libs/ui` and route every page/component title-like element through them. Domain components stop overriding `CardTitle` classes; the primitive carries a sensible default so consumers do not need to repeat it.

This is the "semantic wrapper components" approach (chosen over inline-Tailwind tokens because the same treatments appear across enough call sites that a wrapper pays off and makes future changes single-touch).

### Token set

New components in `libs/ui/src/components/typography/`, re-exported from `libs/ui/src/index.ts`:

| Component       | Tag    | Classes                                                                         | Purpose                                                    |
| --------------- | ------ | ------------------------------------------------------------------------------- | ---------------------------------------------------------- |
| `PageTitle`     | `h1`   | `text-2xl md:text-3xl font-bold tracking-tight`                                 | Page header. Responsive so it fits on mobile.              |
| `PageSubtitle`  | `p`    | `text-sm text-muted-foreground`                                                 | The supporting line under `PageTitle`.                     |
| `SectionHeading`| `h2`   | `text-sm font-semibold uppercase tracking-wide text-muted-foreground`           | Page-level section dividers ("Played Matches", etc.).      |
| `MicroLabel`    | `span` | `text-xs uppercase tracking-wide text-muted-foreground`                         | In-card inline labels ("Attack", "After Week 3", etc.).    |

Each accepts an optional `class` prop and merges via `cn()` for consumer overrides (following the existing shadcn pattern).

### Primitive change

`libs/ui/src/components/ui/card/CardTitle.vue` currently renders an `<h3>` with `leading-none font-semibold` and no size. Add `text-base`:

```diff
- :class="cn('leading-none font-semibold', props.class)"
+ :class="cn('text-base font-semibold leading-none', props.class)"
```

This is the only primitive touched. With a real default size, domain components stop overriding the `CardTitle` class and the visual treatment converges.

### Resulting visual hierarchy

```
PageTitle        — 24/30px bold              (page-level, one per page)
  PageSubtitle   — 14px muted                (page support copy)
  SectionHeading — 14px uppercase muted      (section divider)
    CardTitle    — 16px semibold foreground  (card-level, normal case)
      body       — 14px
      MicroLabel — 12px uppercase muted      (inline in-card label)
```

`SectionHeading` and `CardTitle` are now visually distinct (uppercase-muted-small vs. base-semibold-foreground), so the eye can tell what nests under what.

### Migration mapping

**Pages**

- `apps/web/src/pages/LandingPage.vue`
  - `<h1 class="text-3xl font-bold tracking-tight">` → `<PageTitle>`
  - `<p class="text-sm text-muted-foreground">` → `<PageSubtitle>`
  - `<h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Teams in this league</h2>` → `<SectionHeading>`
- `apps/web/src/pages/LeaguePage.vue`
  - `<h1 class="text-2xl font-bold tracking-tight">` → `<PageTitle>` (now matches Landing)
  - `<p class="text-sm text-muted-foreground">PSG · Bayern · ...</p>` → `<PageSubtitle>`
  - Both `<h2>` "Played Matches" / "Upcoming Weeks" → `<SectionHeading>`

**Components in `libs/ui/src/components/`**

- `FixtureWeekCard.vue` — drop the `text-sm font-semibold uppercase tracking-wide text-muted-foreground` override; `<CardTitle>Week {{ week }}</CardTitle>` uses the primitive default.
- `PredictionPanel.vue`
  - `<CardTitle>` override dropped — same as above.
  - `<p class="text-xs text-muted-foreground">{{ week ... }}</p>` → `<MicroLabel class="block">{{ week ... }}</MicroLabel>`. `MicroLabel` renders a `<span>`; the explicit `block` class is needed so it sits on its own line under `CardTitle` and the parent `CardHeader space-y-1` still applies. Note this also changes the visual from lowercase to uppercase, which is the intended treatment (in-card meta label, consistent with "Attack" / "Defense" / standings week strip).
- `TeamCard.vue`
  - `<CardTitle class="text-base font-bold">{{ team.name }}</CardTitle>` → `<CardTitle>{{ team.name }}</CardTitle>` (bold → semibold; consistency over emphasis).
  - `<span class="w-16 text-xs uppercase tracking-wide text-muted-foreground">Attack</span>` (and Defense) → `<MicroLabel class="w-16">Attack</MicroLabel>`.
- `StandingsTable.vue` — the week-label strip currently `<div class="border-b px-4 py-2 text-xs uppercase tracking-wide text-muted-foreground">` becomes `<div class="border-b px-4 py-2"><MicroLabel>{{ weekLabel }}</MicroLabel></div>` (structural classes stay on the wrapper, typography delegated).

**Components left alone**

`FixtureRow`, `WeekProgressBar`, `GameActionsBar`, `ErrorBanner`, `LoadingSpinner`, `TeamBadge` — body-level text only, no title/heading work to migrate.

### Header alignment

Intentionally kept different between pages:

- `LandingPage` header stays `text-center` (standalone entry page).
- `LeaguePage` header stays left-aligned with `GameActionsBar` on the right (dashboard layout).

No change here.

## Verification

After implementation:

- `nx build web` — green, no new warnings.
- `nx test web` and `nx test ui` — green; existing `FixtureRow.spec.ts` and `fixtureMachine.spec.ts` test behavior, not class names, so they should not need edits.
- Smoke browser pass: Landing renders title at same size as League; section headings on League are visibly smaller / muted-er than the card titles below them.

## Out of scope (explicitly punted)

- Extracting a runtime theme / Tailwind preset.
- A `<Heading level="1|2|3">` polymorphic component. The four-token set is closed.
- Storybook entries for the typography components. Worth doing later if a real Storybook lands; not blocking.
