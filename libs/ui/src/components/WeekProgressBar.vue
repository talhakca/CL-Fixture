<script setup lang="ts">
import { computed } from 'vue';
import { Button } from './ui/button';
import { cn } from '../lib/utils';
import MicroLabel from './typography/MicroLabel.vue';

interface Props {
  totalWeeks: number;
  lastPlayedWeek: number;
  selectedWeek: number | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  select: [week: number];
  'select-latest': [];
}>();

const weeks = computed<number[]>(() =>
  Array.from({ length: props.totalWeeks }, (_, i) => i + 1),
);

const isPlayed = (week: number): boolean => week <= props.lastPlayedWeek;
const isLatest = (week: number): boolean => week === props.lastPlayedWeek && props.lastPlayedWeek > 0;
const isSelected = (week: number): boolean => props.selectedWeek === week;
const weekSelected = (week: number): void => {
  console.log(isLatest(week), props.selectedWeek === null)
  if(isLatest(week)) {
    emit('select-latest');
    return;
  }
  if (isPlayed(week)) {
    emit('select', week);
  }
  
};
</script>

<template>
  <div class="flex justify-center items-center gap-2">
    <MicroLabel>Weeks</MicroLabel>
    <button
      v-for="week in weeks"
      :key="week + 1"
      type="button"
      :disabled="!isPlayed(week) "
      :aria-label="`View week ${week}`"
      :aria-pressed="isSelected(week)"
      :class="
        cn(
          'flex size-9 items-center justify-center rounded-full text-sm font-medium transition-colors',
          'disabled:cursor-not-allowed disabled:opacity-40',
          isPlayed(week) && !isSelected(week) && 'bg-primary text-primary-foreground hover:bg-primary/80',
          !isPlayed(week) && 'bg-muted text-muted-foreground',
          isSelected(week) && 'bg-primary text-primary-foreground ring-2 ring-ring ring-offset-2',
          isLatest(week) && !isSelected(week) && selectedWeek === null && 'ring-2 ring-ring ring-offset-2',
        )
      "
      @click="weekSelected(week)"
    >
      {{ week }}
    </button>
     <Button
      variant="outline"
      size="sm"
      :disabled="selectedWeek === null"
      @click="emit('select-latest')"
    >
      Latest
    </Button>
  </div>
</template>
