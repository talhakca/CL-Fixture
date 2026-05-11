# Typography Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Delete the dead `@champions-league-fixture/` directory at the repo root and replace ad-hoc Tailwind heading classes across the Champions League fixture frontend with four semantic typography components.

**Architecture:** Add four new Vue components under `libs/ui/src/components/typography/` (`PageTitle`, `PageSubtitle`, `SectionHeading`, `MicroLabel`). Bump the existing `CardTitle` primitive default to include a size class so domain components stop overriding it. Migrate `LandingPage`, `LeaguePage`, and five `libs/ui` components onto the new components.

**Tech Stack:** Vue 3 (`<script setup lang="ts">`), Tailwind v4, shadcn-vue primitives, Vue Testing Library + Vitest (`globals: true`, jsdom), Nx monorepo (`npx nx test ui`, `npx nx test web`, `npx nx build web`).

Spec: `docs/superpowers/specs/2026-05-11-typography-cleanup-design.md`.

---

## Conventions

- All new components use `<script setup lang="ts">` and accept an optional `class` prop typed `HTMLAttributes["class"]`, merged with the component's base classes via `cn()` from `../../lib/utils`. This matches the shadcn primitives already in the repo (e.g., `libs/ui/src/components/ui/card/CardTitle.vue`).
- Tests live next to the component as `*.spec.ts`. `describe`, `it`, `expect` are global (see `libs/ui/vitest.config.mts`). Render with `@testing-library/vue`.
- Commit after every task with a focused message; no batching.
- Run `npx nx test ui` and `npx nx test web` from the repo root.

---

## Files Touched

**Created**

- `libs/ui/src/components/typography/PageTitle.vue`
- `libs/ui/src/components/typography/PageTitle.spec.ts`
- `libs/ui/src/components/typography/PageSubtitle.vue`
- `libs/ui/src/components/typography/PageSubtitle.spec.ts`
- `libs/ui/src/components/typography/SectionHeading.vue`
- `libs/ui/src/components/typography/SectionHeading.spec.ts`
- `libs/ui/src/components/typography/MicroLabel.vue`
- `libs/ui/src/components/typography/MicroLabel.spec.ts`

**Modified**

- `libs/ui/src/components/ui/card/CardTitle.vue` (add `text-base` to default)
- `libs/ui/src/index.ts` (re-export the four typography components)
- `libs/ui/src/components/FixtureWeekCard.vue` (drop CardTitle class override)
- `libs/ui/src/components/PredictionPanel.vue` (drop CardTitle override, replace `<p>` with `<MicroLabel class="block">`)
- `libs/ui/src/components/TeamCard.vue` (drop CardTitle override, replace `<span>` labels with `<MicroLabel>`)
- `libs/ui/src/components/StandingsTable.vue` (replace inline label classes with `<MicroLabel>`)
- `apps/web/src/pages/LandingPage.vue` (use `PageTitle`, `PageSubtitle`, `SectionHeading`)
- `apps/web/src/pages/LeaguePage.vue` (use `PageTitle`, `PageSubtitle`, `SectionHeading`)

**Deleted**

- `@champions-league-fixture/` (the entire stray repo-root directory)

---

## Task 1: Delete the stray `@champions-league-fixture/` directory

**Files:**
- Delete: `@champions-league-fixture/` (entire tree)

- [ ] **Step 1: Confirm the directory is truly dead**

Run: `grep -rE "from ['\"](\\./|\\.\\./)*@champions-league-fixture" apps libs --include="*.ts" --include="*.vue"`
Expected: no matches (the `@champions-league-fixture/ui` imports already in the code resolve through workspace package name → `libs/ui`, not through any relative path).

Run: `grep -rE "@champions-league-fixture/(ui|api-sdk)/" tsconfig.base.json tsconfig.json`
Expected: no matches in `paths` aliases pointing at the stray folder.

- [ ] **Step 2: Delete the directory**

Run: `rm -rf @champions-league-fixture`
Expected: command exits 0, `ls` no longer shows that directory at the repo root.

- [ ] **Step 3: Verify build is green**

Run: `npx nx build web`
Expected: build succeeds. (If it fails with "module not found", revert the deletion and stop — the assumption that the directory is dead is wrong.)

- [ ] **Step 4: Verify tests are green**

Run: `npx nx test ui && npx nx test web`
Expected: both passing.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: delete dead @champions-league-fixture/ folder at repo root"
```

---

## Task 2: Add `PageTitle` component (TDD)

**Files:**
- Create: `libs/ui/src/components/typography/PageTitle.vue`
- Test: `libs/ui/src/components/typography/PageTitle.spec.ts`

- [ ] **Step 1: Write the failing test**

Create `libs/ui/src/components/typography/PageTitle.spec.ts`:

```ts
import { render } from '@testing-library/vue';
import PageTitle from './PageTitle.vue';

describe('PageTitle', () => {
  it('renders an h1 with the slot content', () => {
    const { container, getByText } = render(PageTitle, {
      slots: { default: 'Champions League' },
    });
    const el = container.querySelector('h1');
    expect(el).not.toBeNull();
    expect(getByText('Champions League')).toBeTruthy();
  });

  it('applies the base typography classes', () => {
    const { container } = render(PageTitle, {
      slots: { default: 'Hello' },
    });
    const el = container.querySelector('h1')!;
    for (const c of ['text-2xl', 'md:text-3xl', 'font-bold', 'tracking-tight']) {
      expect(el.classList.contains(c)).toBe(true);
    }
  });

  it('merges a consumer-provided class', () => {
    const { container } = render(PageTitle, {
      props: { class: 'text-center' },
      slots: { default: 'Hello' },
    });
    const el = container.querySelector('h1')!;
    expect(el.classList.contains('text-center')).toBe(true);
    expect(el.classList.contains('font-bold')).toBe(true);
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx nx test ui`
Expected: FAIL on the new spec — `Failed to resolve import './PageTitle.vue'`. Existing specs (`FixtureRow`) still pass.

- [ ] **Step 3: Create the component**

Create `libs/ui/src/components/typography/PageTitle.vue`:

```vue
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '../../lib/utils';

const props = defineProps<{
  class?: HTMLAttributes['class'];
}>();
</script>

<template>
  <h1
    data-slot="page-title"
    :class="cn('text-2xl md:text-3xl font-bold tracking-tight', props.class)"
  >
    <slot />
  </h1>
</template>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npx nx test ui`
Expected: full suite green, including the 3 new `PageTitle` cases.

- [ ] **Step 5: Commit**

```bash
git add libs/ui/src/components/typography/PageTitle.vue libs/ui/src/components/typography/PageTitle.spec.ts
git commit -m "feat(ui): add PageTitle typography component"
```

---

## Task 3: Add `PageSubtitle` component (TDD)

**Files:**
- Create: `libs/ui/src/components/typography/PageSubtitle.vue`
- Test: `libs/ui/src/components/typography/PageSubtitle.spec.ts`

- [ ] **Step 1: Write the failing test**

Create `libs/ui/src/components/typography/PageSubtitle.spec.ts`:

```ts
import { render } from '@testing-library/vue';
import PageSubtitle from './PageSubtitle.vue';

describe('PageSubtitle', () => {
  it('renders a p with the slot content', () => {
    const { container, getByText } = render(PageSubtitle, {
      slots: { default: 'Four teams. Six weeks.' },
    });
    expect(container.querySelector('p')).not.toBeNull();
    expect(getByText('Four teams. Six weeks.')).toBeTruthy();
  });

  it('applies the base typography classes', () => {
    const { container } = render(PageSubtitle, {
      slots: { default: 'Hello' },
    });
    const el = container.querySelector('p')!;
    for (const c of ['text-sm', 'text-muted-foreground']) {
      expect(el.classList.contains(c)).toBe(true);
    }
  });

  it('merges a consumer-provided class', () => {
    const { container } = render(PageSubtitle, {
      props: { class: 'italic' },
      slots: { default: 'Hello' },
    });
    const el = container.querySelector('p')!;
    expect(el.classList.contains('italic')).toBe(true);
    expect(el.classList.contains('text-muted-foreground')).toBe(true);
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx nx test ui`
Expected: FAIL on the new spec — `Failed to resolve import './PageSubtitle.vue'`. Previously added typography specs still pass.

- [ ] **Step 3: Create the component**

Create `libs/ui/src/components/typography/PageSubtitle.vue`:

```vue
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '../../lib/utils';

const props = defineProps<{
  class?: HTMLAttributes['class'];
}>();
</script>

<template>
  <p
    data-slot="page-subtitle"
    :class="cn('text-sm text-muted-foreground', props.class)"
  >
    <slot />
  </p>
</template>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npx nx test ui`
Expected: full suite green, including the 3 new `PageSubtitle` cases.

- [ ] **Step 5: Commit**

```bash
git add libs/ui/src/components/typography/PageSubtitle.vue libs/ui/src/components/typography/PageSubtitle.spec.ts
git commit -m "feat(ui): add PageSubtitle typography component"
```

---

## Task 4: Add `SectionHeading` component (TDD)

**Files:**
- Create: `libs/ui/src/components/typography/SectionHeading.vue`
- Test: `libs/ui/src/components/typography/SectionHeading.spec.ts`

- [ ] **Step 1: Write the failing test**

Create `libs/ui/src/components/typography/SectionHeading.spec.ts`:

```ts
import { render } from '@testing-library/vue';
import SectionHeading from './SectionHeading.vue';

describe('SectionHeading', () => {
  it('renders an h2 with the slot content', () => {
    const { container, getByText } = render(SectionHeading, {
      slots: { default: 'Played Matches' },
    });
    expect(container.querySelector('h2')).not.toBeNull();
    expect(getByText('Played Matches')).toBeTruthy();
  });

  it('applies the base typography classes', () => {
    const { container } = render(SectionHeading, {
      slots: { default: 'Hello' },
    });
    const el = container.querySelector('h2')!;
    for (const c of [
      'text-sm',
      'font-semibold',
      'uppercase',
      'tracking-wide',
      'text-muted-foreground',
    ]) {
      expect(el.classList.contains(c)).toBe(true);
    }
  });

  it('merges a consumer-provided class', () => {
    const { container } = render(SectionHeading, {
      props: { class: 'mb-4' },
      slots: { default: 'Hello' },
    });
    const el = container.querySelector('h2')!;
    expect(el.classList.contains('mb-4')).toBe(true);
    expect(el.classList.contains('uppercase')).toBe(true);
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx nx test ui`
Expected: FAIL on the new spec — `Failed to resolve import './SectionHeading.vue'`. Previously added typography specs still pass.

- [ ] **Step 3: Create the component**

Create `libs/ui/src/components/typography/SectionHeading.vue`:

```vue
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '../../lib/utils';

const props = defineProps<{
  class?: HTMLAttributes['class'];
}>();
</script>

<template>
  <h2
    data-slot="section-heading"
    :class="
      cn(
        'text-sm font-semibold uppercase tracking-wide text-muted-foreground',
        props.class,
      )
    "
  >
    <slot />
  </h2>
</template>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npx nx test ui`
Expected: full suite green, including the 3 new `SectionHeading` cases.

- [ ] **Step 5: Commit**

```bash
git add libs/ui/src/components/typography/SectionHeading.vue libs/ui/src/components/typography/SectionHeading.spec.ts
git commit -m "feat(ui): add SectionHeading typography component"
```

---

## Task 5: Add `MicroLabel` component (TDD)

**Files:**
- Create: `libs/ui/src/components/typography/MicroLabel.vue`
- Test: `libs/ui/src/components/typography/MicroLabel.spec.ts`

- [ ] **Step 1: Write the failing test**

Create `libs/ui/src/components/typography/MicroLabel.spec.ts`:

```ts
import { render } from '@testing-library/vue';
import MicroLabel from './MicroLabel.vue';

describe('MicroLabel', () => {
  it('renders a span with the slot content', () => {
    const { container, getByText } = render(MicroLabel, {
      slots: { default: 'Attack' },
    });
    expect(container.querySelector('span')).not.toBeNull();
    expect(getByText('Attack')).toBeTruthy();
  });

  it('applies the base typography classes', () => {
    const { container } = render(MicroLabel, {
      slots: { default: 'Hello' },
    });
    const el = container.querySelector('span')!;
    for (const c of [
      'text-xs',
      'uppercase',
      'tracking-wide',
      'text-muted-foreground',
    ]) {
      expect(el.classList.contains(c)).toBe(true);
    }
  });

  it('merges a consumer-provided class (e.g., block, w-16)', () => {
    const { container } = render(MicroLabel, {
      props: { class: 'block w-16' },
      slots: { default: 'Hello' },
    });
    const el = container.querySelector('span')!;
    expect(el.classList.contains('block')).toBe(true);
    expect(el.classList.contains('w-16')).toBe(true);
    expect(el.classList.contains('uppercase')).toBe(true);
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx nx test ui`
Expected: FAIL on the new spec — `Failed to resolve import './MicroLabel.vue'`. Previously added typography specs still pass.

- [ ] **Step 3: Create the component**

Create `libs/ui/src/components/typography/MicroLabel.vue`:

```vue
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '../../lib/utils';

const props = defineProps<{
  class?: HTMLAttributes['class'];
}>();
</script>

<template>
  <span
    data-slot="micro-label"
    :class="
      cn('text-xs uppercase tracking-wide text-muted-foreground', props.class)
    "
  >
    <slot />
  </span>
</template>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npx nx test ui`
Expected: full suite green, including the 3 new `MicroLabel` cases.

- [ ] **Step 5: Commit**

```bash
git add libs/ui/src/components/typography/MicroLabel.vue libs/ui/src/components/typography/MicroLabel.spec.ts
git commit -m "feat(ui): add MicroLabel typography component"
```

---

## Task 6: Re-export typography components from `libs/ui` barrel

**Files:**
- Modify: `libs/ui/src/index.ts`

- [ ] **Step 1: Add the four exports**

Edit `libs/ui/src/index.ts`. After the existing `LoadingSpinner` line, append:

```ts
export { default as PageTitle } from './components/typography/PageTitle.vue';
export { default as PageSubtitle } from './components/typography/PageSubtitle.vue';
export { default as SectionHeading } from './components/typography/SectionHeading.vue';
export { default as MicroLabel } from './components/typography/MicroLabel.vue';
```

- [ ] **Step 2: Verify the package still type-checks and builds**

Run: `npx nx test ui`
Expected: all suites pass (typography specs + existing FixtureRow spec).

Run: `npx nx build web`
Expected: build succeeds with no new errors.

- [ ] **Step 3: Commit**

```bash
git add libs/ui/src/index.ts
git commit -m "feat(ui): re-export typography components from barrel"
```

---

## Task 7: Add `text-base` default to `CardTitle` primitive

The primitive currently has no size class, which is why every domain component overrides it with `text-sm` and breaks the hierarchy. After this change, domain components can drop the override and the visual treatment converges.

**Files:**
- Modify: `libs/ui/src/components/ui/card/CardTitle.vue:13`

- [ ] **Step 1: Update the primitive**

Edit `libs/ui/src/components/ui/card/CardTitle.vue`. Change the `:class` binding from:

```vue
:class="cn('leading-none font-semibold', props.class)"
```

to:

```vue
:class="cn('text-base font-semibold leading-none', props.class)"
```

- [ ] **Step 2: Verify build is green**

Run: `npx nx build web`
Expected: build succeeds. (The change is additive; no existing consumer relies on the *absence* of `text-base`.)

Run: `npx nx test ui && npx nx test web`
Expected: both passing.

- [ ] **Step 3: Commit**

```bash
git add libs/ui/src/components/ui/card/CardTitle.vue
git commit -m "feat(ui): default CardTitle to text-base font-semibold"
```

---

## Task 8: Migrate `LandingPage`

**Files:**
- Modify: `apps/web/src/pages/LandingPage.vue:10-12,47-79`

- [ ] **Step 1: Add typography imports**

Edit `apps/web/src/pages/LandingPage.vue`. Find the existing import block:

```ts
import TeamCard from '@champions-league-fixture/ui/TeamCard.vue';
import ErrorBanner from '@champions-league-fixture/ui/ErrorBanner.vue';
import LoadingSpinner from '@champions-league-fixture/ui/LoadingSpinner.vue';
```

Append three new imports right after it:

```ts
import PageTitle from '@champions-league-fixture/ui/PageTitle.vue';
import PageSubtitle from '@champions-league-fixture/ui/PageSubtitle.vue';
import SectionHeading from '@champions-league-fixture/ui/SectionHeading.vue';
```

- [ ] **Step 2: Replace the page header markup**

In the `<template>` block, replace this:

```vue
<header class="space-y-2 text-center">
  <h1 class="text-3xl font-bold tracking-tight">Champions League Mini Fixture</h1>
  <p class="text-sm text-muted-foreground">
    Four teams. Six weeks. Pick a season to start.
  </p>
</header>
```

with:

```vue
<header class="space-y-2 text-center">
  <PageTitle>Champions League Mini Fixture</PageTitle>
  <PageSubtitle>Four teams. Six weeks. Pick a season to start.</PageSubtitle>
</header>
```

- [ ] **Step 3: Replace the "Teams in this league" section heading**

Replace this:

```vue
<h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
  Teams in this league
</h2>
```

with:

```vue
<SectionHeading>Teams in this league</SectionHeading>
```

- [ ] **Step 4: Verify build and tests**

Run: `npx nx build web`
Expected: succeeds.

Run: `npx nx test web`
Expected: passes.

- [ ] **Step 5: Commit**

```bash
git add apps/web/src/pages/LandingPage.vue
git commit -m "refactor(web): use typography components on LandingPage"
```

---

## Task 9: Migrate `LeaguePage`

**Files:**
- Modify: `apps/web/src/pages/LeaguePage.vue:15-21,229-237,309-352`

- [ ] **Step 1: Add typography imports**

Edit `apps/web/src/pages/LeaguePage.vue`. After the existing `LoadingSpinner` import line, append:

```ts
import PageTitle from '@champions-league-fixture/ui/PageTitle.vue';
import PageSubtitle from '@champions-league-fixture/ui/PageSubtitle.vue';
import SectionHeading from '@champions-league-fixture/ui/SectionHeading.vue';
```

- [ ] **Step 2: Replace the header title block**

In the `<template>`, find:

```vue
<div>
  <h1 class="text-2xl font-bold tracking-tight">
    Champions League Mini Fixture
  </h1>
  <p class="text-sm text-muted-foreground">
    PSG · Bayern · Arsenal · Atletico 
  </p>
</div>
```

Replace with:

```vue
<div>
  <PageTitle>Champions League Mini Fixture</PageTitle>
  <PageSubtitle>PSG · Bayern · Arsenal · Atletico</PageSubtitle>
</div>
```

(Note: the trailing space in the original `Atletico ` text is removed.)

- [ ] **Step 3: Replace the "Played Matches" heading**

Find:

```vue
<h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
  Played Matches
</h2>
```

Replace with:

```vue
<SectionHeading>Played Matches</SectionHeading>
```

- [ ] **Step 4: Replace the "Upcoming Weeks" heading**

Find:

```vue
<h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
  Upcoming Weeks
</h2>
```

Replace with:

```vue
<SectionHeading>Upcoming Weeks</SectionHeading>
```

- [ ] **Step 5: Verify build and tests**

Run: `npx nx build web`
Expected: succeeds.

Run: `npx nx test web`
Expected: passes.

- [ ] **Step 6: Commit**

```bash
git add apps/web/src/pages/LeaguePage.vue
git commit -m "refactor(web): use typography components on LeaguePage"
```

---

## Task 10: Migrate `FixtureWeekCard`

**Files:**
- Modify: `libs/ui/src/components/FixtureWeekCard.vue:26-28`

- [ ] **Step 1: Drop the CardTitle class override**

Edit `libs/ui/src/components/FixtureWeekCard.vue`. Replace:

```vue
<CardTitle class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
  Week {{ week }}
</CardTitle>
```

with:

```vue
<CardTitle>Week {{ week }}</CardTitle>
```

The primitive default (`text-base font-semibold`, from Task 7) now provides the styling.

- [ ] **Step 2: Verify tests and build**

Run: `npx nx test ui && npx nx test web`
Expected: passing.

Run: `npx nx build web`
Expected: succeeds.

- [ ] **Step 3: Commit**

```bash
git add libs/ui/src/components/FixtureWeekCard.vue
git commit -m "refactor(ui): let FixtureWeekCard use CardTitle default styling"
```

---

## Task 11: Migrate `PredictionPanel`

**Files:**
- Modify: `libs/ui/src/components/PredictionPanel.vue:4,21-29`

- [ ] **Step 1: Add the MicroLabel import**

Edit `libs/ui/src/components/PredictionPanel.vue`. After the existing `TeamBadge` import:

```ts
import TeamBadge from './TeamBadge.vue';
```

Append:

```ts
import MicroLabel from './typography/MicroLabel.vue';
```

- [ ] **Step 2: Drop the CardTitle override and replace the `<p>` with `<MicroLabel class="block">`**

Replace this block:

```vue
<CardHeader class="space-y-1">
  <CardTitle class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
    Championship Odds
  </CardTitle>
  <p class="text-xs text-muted-foreground">
    {{ week === null ? 'No data yet' : `After Week ${week}` }}
  </p>
</CardHeader>
```

with:

```vue
<CardHeader class="space-y-1">
  <CardTitle>Championship Odds</CardTitle>
  <MicroLabel class="block">
    {{ week === null ? 'No data yet' : `After Week ${week}` }}
  </MicroLabel>
</CardHeader>
```

`class="block"` is required so the span sits on its own line and `CardHeader space-y-1` still applies. The visual changes from lowercase ("After Week 3") to uppercase ("AFTER WEEK 3") — intentional, consistent with the StandingsTable week strip and TeamCard stat labels.

- [ ] **Step 3: Verify tests and build**

Run: `npx nx test ui && npx nx test web`
Expected: passing.

Run: `npx nx build web`
Expected: succeeds.

- [ ] **Step 4: Commit**

```bash
git add libs/ui/src/components/PredictionPanel.vue
git commit -m "refactor(ui): use MicroLabel for PredictionPanel meta line"
```

---

## Task 12: Migrate `TeamCard`

**Files:**
- Modify: `libs/ui/src/components/TeamCard.vue:3,14-31`

- [ ] **Step 1: Add the MicroLabel import**

Edit `libs/ui/src/components/TeamCard.vue`. After the existing card primitive import:

```ts
import { Card, CardContent, CardHeader, CardTitle } from './ui/card';
```

Append:

```ts
import MicroLabel from './typography/MicroLabel.vue';
```

- [ ] **Step 2: Drop the CardTitle bold override**

Replace:

```vue
<CardTitle class="text-base font-bold">{{ team.name }}</CardTitle>
```

with:

```vue
<CardTitle>{{ team.name }}</CardTitle>
```

(`font-bold` → `font-semibold` via primitive default; intentional, for hierarchy consistency.)

- [ ] **Step 3: Replace the Attack and Defense labels**

Replace:

```vue
<span class="w-16 text-xs uppercase tracking-wide text-muted-foreground">Attack</span>
```

with:

```vue
<MicroLabel class="w-16">Attack</MicroLabel>
```

And replace:

```vue
<span class="w-16 text-xs uppercase tracking-wide text-muted-foreground">Defense</span>
```

with:

```vue
<MicroLabel class="w-16">Defense</MicroLabel>
```

(`w-16` works on a span inside a flex parent — the surrounding `<div class="flex items-center gap-2">` makes the span a flex item.)

- [ ] **Step 4: Verify tests and build**

Run: `npx nx test ui && npx nx test web`
Expected: passing.

Run: `npx nx build web`
Expected: succeeds.

- [ ] **Step 5: Commit**

```bash
git add libs/ui/src/components/TeamCard.vue
git commit -m "refactor(ui): use MicroLabel for TeamCard stat labels"
```

---

## Task 13: Migrate `StandingsTable`

**Files:**
- Modify: `libs/ui/src/components/StandingsTable.vue:12,29-35`

- [ ] **Step 1: Add the MicroLabel import**

Edit `libs/ui/src/components/StandingsTable.vue`. After the existing `TeamBadge` import:

```ts
import TeamBadge from './TeamBadge.vue';
```

Append:

```ts
import MicroLabel from './typography/MicroLabel.vue';
```

- [ ] **Step 2: Replace the week-label strip**

Replace:

```vue
<div
  v-if="weekLabel"
  class="border-b px-4 py-2 text-xs uppercase tracking-wide text-muted-foreground"
>
  {{ weekLabel }}
</div>
```

with:

```vue
<div v-if="weekLabel" class="border-b px-4 py-2">
  <MicroLabel>{{ weekLabel }}</MicroLabel>
</div>
```

Structural classes (`border-b px-4 py-2`) stay on the wrapper; typography is delegated to `MicroLabel`.

- [ ] **Step 3: Verify tests and build**

Run: `npx nx test ui && npx nx test web`
Expected: passing.

Run: `npx nx build web`
Expected: succeeds.

- [ ] **Step 4: Commit**

```bash
git add libs/ui/src/components/StandingsTable.vue
git commit -m "refactor(ui): use MicroLabel for StandingsTable week strip"
```

---

## Task 14: Final verification — dev server smoke check

The unit tests cover class composition but cannot confirm the visual hierarchy reads correctly. This task verifies it in a real browser.

**Files:** none (read-only verification).

- [ ] **Step 1: Confirm the stray folder is gone**

Run: `ls @champions-league-fixture 2>&1`
Expected: `ls: @champions-league-fixture: No such file or directory`.

- [ ] **Step 2: Run all unit suites**

Run: `npx nx test ui && npx nx test web`
Expected: both green, with 4 new typography spec files in `ui`.

- [ ] **Step 3: Run the dev server**

Run: `npx nx serve web`
Expected: dev server starts. Open the URL it prints (usually `http://localhost:4200`).

- [ ] **Step 4: Visual checklist on LandingPage**

Open the landing page. Confirm:
- Page title "Champions League Mini Fixture" is large (24px mobile, 30px desktop), bold, centered.
- Subtitle "Four teams. Six weeks…" is small, muted, centered.
- "Teams in this league" appears as a small uppercase muted heading above the team grid.
- Each `TeamCard` shows the team name at base size + semibold (not bold), with "ATTACK" and "DEFENSE" as uppercase muted micro-labels next to the bars.

- [ ] **Step 5: Visual checklist on LeaguePage**

Generate a tournament and land on the league page. Confirm:
- Page title is the *same size* as the LandingPage title (responsive: 24px / 30px).
- Subtitle "PSG · Bayern · Arsenal · Atletico" is small + muted, left-aligned.
- `StandingsTable` shows "LIVE" or "AFTER WEEK N" as a small uppercase muted strip above the table.
- `PredictionPanel` shows "Championship Odds" at base size + semibold (not the old small uppercase muted) — and the "AFTER WEEK N" line below it is small uppercase muted on its own line.
- `FixtureWeekCard` titles ("Week 1", "Week 2", …) render at base size + semibold (not the old small uppercase muted), visibly larger than the `SectionHeading` ("Played Matches" / "Upcoming Weeks") that contains them.

- [ ] **Step 6: Stop the dev server**

Press Ctrl-C in the `nx serve web` terminal.

- [ ] **Step 7: No commit needed — verification only**

If anything in steps 4 or 5 looked wrong, file a follow-up task; do not patch silently here.
