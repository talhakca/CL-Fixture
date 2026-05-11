<script setup lang="ts">
import type { TeamResource } from '@champions-league-fixture/api-sdk';
import { Card, CardContent, CardHeader, CardTitle } from './ui/card';

interface Props {
  team: TeamResource;
}

defineProps<Props>();
</script>

<template>
  <Card class="overflow-hidden">
    <CardHeader>
      <CardTitle class="text-base font-bold">{{ team.name }}</CardTitle>
    </CardHeader>
    <CardContent class="space-y-2 text-sm">
      <div class="flex items-center gap-2">
        <span class="w-16 text-xs uppercase tracking-wide text-muted-foreground">Attack</span>
        <div class="relative h-2 flex-1 overflow-hidden rounded-full bg-muted">
          <div class="h-full bg-primary" :style="{ width: `${team.attack_strength}%` }" />
        </div>
        <span class="w-8 text-right tabular-nums">{{ team.attack_strength }}</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="w-16 text-xs uppercase tracking-wide text-muted-foreground">Defense</span>
        <div class="relative h-2 flex-1 overflow-hidden rounded-full bg-muted">
          <div class="h-full bg-primary" :style="{ width: `${team.defense_strength}%` }" />
        </div>
        <span class="w-8 text-right tabular-nums">{{ team.defense_strength }}</span>
      </div>
    </CardContent>
  </Card>
</template>
