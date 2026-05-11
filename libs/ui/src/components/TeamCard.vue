<script setup lang="ts">
import type { TeamResource } from '@champions-league-fixture/api-sdk';
import { Card, CardContent, CardHeader, CardTitle } from './ui/card';
import MicroLabel from './typography/MicroLabel.vue';

interface Props {
  team: TeamResource;
}

defineProps<Props>();
</script>

<template>
  <Card class="overflow-hidden">
    <CardHeader>
      <CardTitle>{{ team.name }}</CardTitle>
    </CardHeader>
    <CardContent class="space-y-2 text-sm">
      <div class="flex items-center gap-2">
        <MicroLabel class="w-16">Attack</MicroLabel>
        <div class="relative h-2 flex-1 overflow-hidden rounded-full bg-muted">
          <div class="h-full bg-primary" :style="{ width: `${team.attack_strength}%` }" />
        </div>
        <span class="w-8 text-right tabular-nums">{{ team.attack_strength }}</span>
      </div>
      <div class="flex items-center gap-2">
        <MicroLabel class="w-16">Defense</MicroLabel>
        <div class="relative h-2 flex-1 overflow-hidden rounded-full bg-muted">
          <div class="h-full bg-primary" :style="{ width: `${team.defense_strength}%` }" />
        </div>
        <span class="w-8 text-right tabular-nums">{{ team.defense_strength }}</span>
      </div>
    </CardContent>
  </Card>
</template>
