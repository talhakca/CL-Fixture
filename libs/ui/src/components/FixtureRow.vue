<script setup lang="ts">
import type { FixtureResource } from '@champions-league-fixture/api-sdk';
import { Pencil, Check, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from './ui/button';
import { Input } from './ui/input';

interface Props {
  fixture: FixtureResource;
  isEditing: boolean;
  isSubmitting: boolean;
  canEdit: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  'start-edit': [id: number];
  'cancel-edit': [];
  'save-edit': [payload: { id: number; homeGoals: number; awayGoals: number }];
}>();

// Mirror the fixture's score into local refs only while editing — these are
// pure form-state, not domain state. The page owns `editingFixtureId`; this
// component owns "what's currently in the inputs".
const homeInput = ref<number>(props.fixture.home_goals ?? 0);
const awayInput = ref<number>(props.fixture.away_goals ?? 0);

watch(
  () => [props.isEditing, props.fixture.id],
  ([editing]): void => {
    if (editing) {
      homeInput.value = props.fixture.home_goals ?? 0;
      awayInput.value = props.fixture.away_goals ?? 0;
    }
  },
);

const onSave = (): void => {
  emit('save-edit', {
    id: props.fixture.id,
    homeGoals: Math.max(0, Math.floor(Number(homeInput.value) || 0)),
    awayGoals: Math.max(0, Math.floor(Number(awayInput.value) || 0)),
  });
};
</script>

<template>
  <div class="flex items-center gap-2 px-3 py-2">
    <span class="flex-1 truncate text-right text-sm font-medium">
      {{ fixture.home_team?.name }}
    </span>

    <template v-if="isEditing">
      <Input
        v-model="homeInput"
        type="number"
        min="0"
        max="20"
        class="w-14 text-center"
        :disabled="isSubmitting"
        aria-label="Home goals"
      />
      <span class="text-muted-foreground">–</span>
      <Input
        v-model="awayInput"
        type="number"
        min="0"
        max="20"
        class="w-14 text-center"
        :disabled="isSubmitting"
        aria-label="Away goals"
      />
    </template>

    <template v-else-if="fixture.is_played">
      <span class="w-14 text-center text-base font-bold">{{ fixture.home_goals }}</span>
      <span class="text-muted-foreground">–</span>
      <span class="w-14 text-center text-base font-bold">{{ fixture.away_goals }}</span>
    </template>

    <template v-else>
      <span class="w-14 text-center text-muted-foreground">·</span>
      <span class="text-muted-foreground">–</span>
      <span class="w-14 text-center text-muted-foreground">·</span>
    </template>

    <span class="flex-1 truncate text-left text-sm font-medium">
      {{ fixture.away_team?.name ?? `Team ${fixture.away_team_id}` }}
    </span>

    <div class="flex w-20 justify-end gap-1">
      <template v-if="isEditing">
        <Button
          variant="ghost"
          size="icon-sm"
          aria-label="Save score"
          :disabled="isSubmitting"
          @click="onSave"
        >
          <Check class="size-4" />
        </Button>
        <Button
          variant="ghost"
          size="icon-sm"
          aria-label="Cancel edit"
          :disabled="isSubmitting"
          @click="emit('cancel-edit')"
        >
          <X class="size-4" />
        </Button>
      </template>
      <Button
        v-else-if="canEdit && fixture.is_played"
        variant="ghost"
        size="icon-sm"
        aria-label="Edit score"
        @click="emit('start-edit', fixture.id)"
      >
        <Pencil class="size-4" />
      </Button>
    </div>
  </div>
</template>
