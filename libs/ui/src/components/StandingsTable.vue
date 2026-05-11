<script setup lang="ts">
import type { StandingsRowResource } from '@champions-league-fixture/api-sdk';
import { ArrowUp, ArrowDown } from 'lucide-vue-next';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from './ui/table';
import TeamBadge from './TeamBadge.vue';
import MicroLabel from './typography/MicroLabel.vue';

interface Props {
  rows: StandingsRowResource[];
  weekLabel?: string;
  /**
   * Optional rank deltas keyed by team_id. Positive = team climbed,
   * negative = dropped, missing/zero = no change. When supplied, an
   * up/down arrow renders next to the rank column.
   */
  rankDeltas?: Map<number, number> | null;
}

defineProps<Props>();
</script>

<template>
  <div class="rounded-md border">
    <div v-if="weekLabel" class="border-b px-4 py-2">
      <MicroLabel>{{ weekLabel }}</MicroLabel>
    </div>
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead class="w-12">
            #
          </TableHead>
          <TableHead>Team</TableHead>
          <TableHead class="text-center">P</TableHead>
          <TableHead class="text-center">W</TableHead>
          <TableHead class="text-center">D</TableHead>
          <TableHead class="text-center">L</TableHead>
          <TableHead class="text-center">GF</TableHead>
          <TableHead class="text-center">GA</TableHead>
          <TableHead class="text-center">GD</TableHead>
          <TableHead class="text-center font-bold">Pts</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        <TableRow
          v-for="(row, index) in rows"
          :key="row.team_id"
        >
          <TableCell class="text-muted-foreground">
            <span class="inline-flex items-center gap-1">
              <span>{{ index + 1 }}</span>
              <ArrowUp
                v-if="rankDeltas && (rankDeltas.get(row.team_id) ?? 0) > 0"
                class="size-3 text-emerald-500 transition-opacity"
                :aria-label="`Up ${rankDeltas.get(row.team_id)}`"
              />
              <ArrowDown
                v-else-if="rankDeltas && (rankDeltas.get(row.team_id) ?? 0) < 0"
                class="size-3 text-red-500 transition-opacity"
                :aria-label="`Down ${Math.abs(rankDeltas.get(row.team_id) ?? 0)}`"
              />
            </span>
          </TableCell>
          <TableCell>
            <TeamBadge :name="row.team_name" />
          </TableCell>
          <TableCell class="text-center">{{ row.played }}</TableCell>
          <TableCell class="text-center">{{ row.won }}</TableCell>
          <TableCell class="text-center">{{ row.drawn }}</TableCell>
          <TableCell class="text-center">{{ row.lost }}</TableCell>
          <TableCell class="text-center">{{ row.goals_for }}</TableCell>
          <TableCell class="text-center">{{ row.goals_against }}</TableCell>
          <TableCell class="text-center">
            {{ row.goal_difference > 0 ? `+${row.goal_difference}` : row.goal_difference }}
          </TableCell>
          <TableCell class="text-center font-bold">{{ row.points }}</TableCell>
        </TableRow>
      </TableBody>
    </Table>
  </div>
</template>
