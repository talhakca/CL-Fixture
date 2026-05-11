<script setup lang="ts">
import type { FixtureResource } from '@champions-league-fixture/api-sdk';
import { Card, CardContent, CardHeader, CardTitle } from './ui/card';
import FixtureRow from './FixtureRow.vue';

interface Props {
  week: number;
  matches: FixtureResource[];
  editingFixtureId: number | null;
  isSubmitting: boolean;
  canEdit: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  'start-edit': [id: number];
  'cancel-edit': [];
  'save-edit': [payload: { id: number; homeGoals: number; awayGoals: number }];
}>();
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
        Week {{ week }}
      </CardTitle>
    </CardHeader>
    <CardContent class="divide-y p-0">
      <FixtureRow
        v-for="match in matches"
        :key="match.id"
        :fixture="match"
        :is-editing="props.editingFixtureId === match.id"
        :is-submitting="props.isSubmitting && props.editingFixtureId === match.id"
        :can-edit="canEdit"
        @start-edit="emit('start-edit', $event)"
        @cancel-edit="emit('cancel-edit')"
        @save-edit="emit('save-edit', $event)"
      />
    </CardContent>
  </Card>
</template>
