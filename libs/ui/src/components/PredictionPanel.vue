<script setup lang="ts">
import type { PredictionRowResource } from '@champions-league-fixture/api-sdk';
import { ArrowUp, ArrowDown } from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from './ui/card';
import LoadingSpinner from './LoadingSpinner.vue';
import TeamBadge from './TeamBadge.vue';

interface Props {
  rows: PredictionRowResource[];
  week: number | null;
  isLoading: boolean;
  rankDeltas?: Map<number, number> | null;
}

defineProps<Props>();

const formatPercent = (value: number): string => `${(value * 100).toFixed(1)}%`;
</script>

<template>
  <Card>
    <CardHeader class="space-y-1">
      <CardTitle class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
        Championship Odds
      </CardTitle>
      <p class="text-xs text-muted-foreground">
        {{ week === null ? 'No data yet' : `After Week ${week}` }}
      </p>
    </CardHeader>
    <CardContent class="space-y-3">
      <LoadingSpinner
        v-if="isLoading"
        label="Computing…"
      />
      <p
        v-else-if="rows.length === 0"
        class="text-sm text-muted-foreground"
      >
        Play a week to see championship odds.
      </p>
      <div
        v-else
        class="space-y-2"
      >
        <div
          v-for="row in rows"
          :key="row.team_id"
          class="flex items-center gap-3"
        >
          <span class="w-24 truncate text-sm">
            <TeamBadge :name="row.team_name" />
          </span>
          <div class="relative h-2 flex-1 overflow-hidden rounded-full bg-muted">
            <div
              class="h-full bg-primary"
              :style="{ width: `${row.championship_probability * 100}%` }"
            />
          </div>
          <span class="flex w-20 items-center justify-end gap-1 text-sm tabular-nums">
            <ArrowUp
              v-if="rankDeltas && (rankDeltas.get(row.team_id) ?? 0) > 0"
              class="size-3 text-emerald-500"
              :aria-label="`Up ${rankDeltas.get(row.team_id)}`"
            />
            <ArrowDown
              v-else-if="rankDeltas && (rankDeltas.get(row.team_id) ?? 0) < 0"
              class="size-3 text-red-500"
              :aria-label="`Down ${Math.abs(rankDeltas.get(row.team_id) ?? 0)}`"
            />
            <span>{{ formatPercent(row.championship_probability) }}</span>
          </span>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
