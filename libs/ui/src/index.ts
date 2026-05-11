export { cn } from './lib/utils';

// High-level dumb components — re-exported so consumers can:
//   import { StandingsTable, FixtureRow } from '@champions-league-fixture/ui'
// or import per-file via the './*.vue' subpath:
//   import StandingsTable from '@champions-league-fixture/ui/StandingsTable.vue'
export { default as StandingsTable } from './components/StandingsTable.vue';
export { default as TeamCard } from './components/TeamCard.vue';
export { default as TeamBadge } from './components/TeamBadge.vue';
export { default as FixtureWeekCard } from './components/FixtureWeekCard.vue';
export { default as FixtureRow } from './components/FixtureRow.vue';
export { default as PredictionPanel } from './components/PredictionPanel.vue';
export { default as WeekProgressBar } from './components/WeekProgressBar.vue';
export { default as GameActionsBar } from './components/GameActionsBar.vue';
export { default as ErrorBanner } from './components/ErrorBanner.vue';
export { default as LoadingSpinner } from './components/LoadingSpinner.vue';
export { default as PageTitle } from './components/typography/PageTitle.vue';
export { default as PageSubtitle } from './components/typography/PageSubtitle.vue';
export { default as SectionHeading } from './components/typography/SectionHeading.vue';
export { default as MicroLabel } from './components/typography/MicroLabel.vue';
