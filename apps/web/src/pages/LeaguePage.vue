<script setup lang="ts">
import { computed, onMounted, ref, watch, onBeforeUnmount } from 'vue';
import { useMachine } from '@xstate/vue';
import { useRouter } from 'vue-router';
import type { FixtureResource } from '@champions-league-fixture/api-sdk';

import { teamMachine } from '../machines/teamMachine';
import { fixtureMachine } from '../machines/fixtureMachine';
import { standingsMachine } from '../machines/standingsMachine';
import { predictionMachine } from '../machines/predictionMachine';
import { computeRankDeltas } from '../utils/rankDeltas';

const RANK_DELTA_VISIBLE_MS = 2000;

import StandingsTable from '@champions-league-fixture/ui/StandingsTable.vue';
import FixtureWeekCard from '@champions-league-fixture/ui/FixtureWeekCard.vue';
import PredictionPanel from '@champions-league-fixture/ui/PredictionPanel.vue';
import WeekProgressBar from '@champions-league-fixture/ui/WeekProgressBar.vue';
import GameActionsBar from '@champions-league-fixture/ui/GameActionsBar.vue';
import ErrorBanner from '@champions-league-fixture/ui/ErrorBanner.vue';
import LoadingSpinner from '@champions-league-fixture/ui/LoadingSpinner.vue';
import PageTitle from '@champions-league-fixture/ui/typography/PageTitle.vue';
import PageSubtitle from '@champions-league-fixture/ui/typography/PageSubtitle.vue';
import SectionHeading from '@champions-league-fixture/ui/typography/SectionHeading.vue';

interface Props {
  tournamentId: number;
}

const props = defineProps<Props>();
const router = useRouter();

const team = useMachine(teamMachine);
const fixture = useMachine(fixtureMachine, { input: { tournamentId: props.tournamentId } });
const standings = useMachine(standingsMachine, { input: { tournamentId: props.tournamentId } });
const prediction = useMachine(predictionMachine, { input: { tournamentId: props.tournamentId } });

// UI-only refs (presentation state, not domain). The page owns these per
// FRONTEND.md "pages orchestrate" rule.
const editingFixtureId = ref<number | null>(null);
const viewedWeek = ref<number | null>(null);

onMounted(() => {
  team.send({ type: 'LOAD' });
  standings.send({ type: 'LOAD' });
  prediction.send({ type: 'LOAD_LATEST' });
});

const fixtureSnapshot = computed(() => fixture.snapshot.value);

// When fixtureMachine leaves a `mutation`-tagged state, refresh the
// dependent reads. Watching the tag boolean gives us free transition
// detection — Vue's watch fires with (newValue, oldValue).
watch(
  () => fixtureSnapshot.value.hasTag('mutation'),
  (mutating, wasMutating) => {
    if (!wasMutating || mutating) return;
    if (fixtureSnapshot.value.matches('error')) return;

    if (viewedWeek.value === null) {
      standings.send({ type: 'REFETCH' });
      prediction.send({ type: 'REFETCH' });
    } else {
      standings.send({ type: 'LOAD_FOR_WEEK', week: viewedWeek.value });
      prediction.send({ type: 'LOAD_WEEK', week: viewedWeek.value });
    }
  },
);

watch(viewedWeek, (week) => {
  if (week === null) {
    standings.send({ type: 'LOAD' });
    prediction.send({ type: 'LOAD_LATEST' });
  } else {
    standings.send({ type: 'LOAD_FOR_WEEK', week });
    prediction.send({ type: 'LOAD_WEEK', week });
  }
});

// Derived view state ----------------------------------------------------------

const fixtures = computed<FixtureResource[]>(() => fixtureSnapshot.value.context.fixtures);

const displayedFixtures = computed(() =>
  viewedWeek.value === null
    ? fixtures.value
    : fixtures.value.filter((f) => f.week === viewedWeek.value),
);

const fixturesByWeek = computed(() => {
  const grouped = new Map<number, FixtureResource[]>();
  for (const f of displayedFixtures.value) {
    const list = grouped.get(f.week) ?? [];
    list.push(f);
    grouped.set(f.week, list);
  }
  return [...grouped.entries()].sort(([a], [b]) => a - b);
});

const playedWeeks = computed(() =>
  fixturesByWeek.value.filter(([, matches]) => matches.some((m) => m.is_played)),
);

const upcomingWeeks = computed(() =>
  fixturesByWeek.value.filter(([, matches]) => matches.every((m) => !m.is_played)),
);

const totalWeeks = computed(() => fixtureSnapshot.value.context.totalWeeks);
const lastPlayedWeek = computed(() => fixtureSnapshot.value.context.lastPlayedWeek);

const isHistorical = computed(() => viewedWeek.value !== null);
const isBootstrapping = computed(() => fixtureSnapshot.value.matches('bootstrapping'));
const isMutating = computed(() => fixtureSnapshot.value.hasTag('mutation'));
const isInError = computed(() => fixtureSnapshot.value.matches('error'));
// The action buttons stay visible whenever a schedule exists; they only
// flip to disabled (mutating, historical view, or season finished) so
// the layout never reflows mid-click.
const hasSchedule = computed(() => totalWeeks.value > 0);
const seasonOver = computed(
  () => totalWeeks.value > 0 && lastPlayedWeek.value >= totalWeeks.value,
);
const canPlay = computed(() => hasSchedule.value);
const canReset = computed(() => hasSchedule.value);

const pendingAction = computed<'play-week' | 'play-all' | 'reset-scores' | null>(() => {
  const s = fixtureSnapshot.value;
  if (s.matches('playingWeek')) return 'play-week';
  if (s.matches('playingAll')) return 'play-all';
  if (s.matches('resettingScores')) return 'reset-scores';
  return null;
});

const standingsLabel = computed(() =>
  viewedWeek.value === null ? 'Live' : `After Week ${viewedWeek.value}`,
);

const predictionWeek = computed<number | null>(() => {
  const ctx = prediction.snapshot.value.context;
  return ctx.viewedWeek ?? (lastPlayedWeek.value || null);
});

const predictionLoading = computed(
  () =>
    prediction.snapshot.value.matches('loadingLatest') ||
    prediction.snapshot.value.matches('loadingForWeek'),
);

// Rank deltas: computed from the play-week snapshot the fixtureMachine
// stores. We surface them only while the standings/prediction machines
// have settled into `ready` (so we're comparing previous to the truly
// updated rows), and clear after RANK_DELTA_VISIBLE_MS.

const lastPlayMeta = computed(() => fixtureSnapshot.value.context.lastPlayMeta);
const deltasVisible = ref(false);
let deltaTimer: ReturnType<typeof setTimeout> | null = null;

const showDeltasFor = (): void => {
  deltasVisible.value = true;
  if (deltaTimer) clearTimeout(deltaTimer);
  deltaTimer = setTimeout(() => {
    deltasVisible.value = false;
  }, RANK_DELTA_VISIBLE_MS);
};

watch(
  [
    () => lastPlayMeta.value?.at,
    () => standings.snapshot.value.context.rows,
  ],
  ([at, rows], [prevAt, prevRows]) => {
    if (at && at !== prevAt) {
      showDeltasFor();
    }
    // standings rows are a fresh array reference on every refetch; once
    // a refresh lands, drop any in-flight inline edit so the row settles
    // back to view mode with the new score.
    if (rows !== prevRows) {
      editingFixtureId.value = null;
    }
  },
);

onBeforeUnmount(() => {
  if (deltaTimer) clearTimeout(deltaTimer);
});

const standingsRankDeltas = computed<Map<number, number> | null>(() => {
  if (!deltasVisible.value || !lastPlayMeta.value) return null;
  if (viewedWeek.value !== null) return null;
  return computeRankDeltas(
    lastPlayMeta.value.previousStandings,
    standings.snapshot.value.context.rows,
  );
});

const predictionRankDeltas = computed<Map<number, number> | null>(() => {
  if (!deltasVisible.value || !lastPlayMeta.value) return null;
  if (viewedWeek.value !== null) return null;
  if (lastPlayMeta.value.previousPredictions.length === 0) return null;
  return computeRankDeltas(
    lastPlayMeta.value.previousPredictions,
    prediction.snapshot.value.context.rows,
  );
});

// Event handlers --------------------------------------------------------------

const onSaveEdit = (payload: { id: number; homeGoals: number; awayGoals: number }): void => {
  fixture.send({
    type: 'EDIT_SCORE',
    fixtureId: payload.id,
    homeGoals: payload.homeGoals,
    awayGoals: payload.awayGoals,
  });
};

const onSelectWeek = (week: number): void => {
  viewedWeek.value = week;
};

const onRetry = (): void => {
  fixture.send({ type: 'RETRY' });
};

const onBackToLanding = (): void => {
  router.push({ name: 'landing' });
};
</script>

<template>
  <div class="mx-auto max-w-7xl space-y-6 p-6">
    <header class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <PageTitle>Champions League Mini Fixture</PageTitle>
        <PageSubtitle>PSG · Bayern · Arsenal · Atletico</PageSubtitle>
      </div>
      <GameActionsBar
        :can-play="canPlay"
        :can-reset="canReset"
        :is-busy="isMutating"
        :is-historical="isHistorical"
        :is-season-over="seasonOver"
        :pending-action="pendingAction"
        @play-week="fixture.send({ type: 'PLAY_WEEK' })"
        @play-all="fixture.send({ type: 'PLAY_ALL' })"
        @reset-scores="fixture.send({ type: 'RESET_SCORES' })"
        @reset-tournament="onBackToLanding"
      />
    </header>

    <ErrorBanner
      v-if="isInError"
      :message="fixtureSnapshot.context.error ?? 'Unknown error'"
      @retry="onRetry"
      @dismiss="onRetry"
    />

    <LoadingSpinner
      v-if="isBootstrapping"
      label="Loading league…"
    />

    <template v-else>
      <WeekProgressBar
        v-if="totalWeeks > 0"
        :total-weeks="totalWeeks"
        :last-played-week="lastPlayedWeek"
        :selected-week="viewedWeek"
        @select="onSelectWeek"
        @select-latest="viewedWeek = null"
      />

      <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
          <StandingsTable
            :rows="standings.snapshot.value.context.rows"
            :week-label="standingsLabel"
            :rank-deltas="standingsRankDeltas"
          />
        </div>
        <aside class="space-y-4">
          <PredictionPanel
            :rows="prediction.snapshot.value.context.rows"
            :week="predictionWeek"
            :is-loading="predictionLoading"
            :rank-deltas="predictionRankDeltas"
          />
        </aside>
      </div>

      <template v-if="isHistorical">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <FixtureWeekCard
            v-for="[week, matches] in fixturesByWeek"
            :key="week"
            :week="week"
            :matches="matches"
            :editing-fixture-id="editingFixtureId"
            :is-submitting="fixtureSnapshot.matches('editingScore')"
            :can-edit="false"
            @start-edit="(id: number) => (editingFixtureId = id)"
            @cancel-edit="editingFixtureId = null"
            @save-edit="onSaveEdit"
          />
        </div>
      </template>
      <template v-else>
        <section
          v-if="playedWeeks.length > 0"
          class="space-y-3"
        >
          <SectionHeading class="font-normal">
            Played Matches
          </SectionHeading>
          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <FixtureWeekCard
              v-for="[week, matches] in playedWeeks"
              :key="week"
              :week="week"
              :matches="matches"
              :editing-fixture-id="editingFixtureId"
              :is-submitting="fixtureSnapshot.matches('editingScore')"
              :can-edit="true"
              @start-edit="(id: number) => (editingFixtureId = id)"
              @cancel-edit="editingFixtureId = null"
              @save-edit="onSaveEdit"
            />
          </div>
        </section>
        <section
          v-if="upcomingWeeks.length > 0"
          class="space-y-3"
        >
          <SectionHeading class="font-normal">
            Upcoming Weeks
          </SectionHeading>
          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <FixtureWeekCard
              v-for="[week, matches] in upcomingWeeks"
              :key="week"
              :week="week"
              :matches="matches"
              :editing-fixture-id="editingFixtureId"
              :is-submitting="fixtureSnapshot.matches('editingScore')"
              :can-edit="false"
              @start-edit="(id: number) => (editingFixtureId = id)"
              @cancel-edit="editingFixtureId = null"
              @save-edit="onSaveEdit"
            />
          </div>
        </section>
      </template>
    </template>
  </div>
</template>
