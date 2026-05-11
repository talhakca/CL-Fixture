<script setup lang="ts">
import { Play, FastForward, RotateCcw, Trash2, Loader2, FilePlus } from 'lucide-vue-next';
import { Button } from './ui/button';

export type PendingAction = 'play-week' | 'play-all' | 'reset-scores' | null;

interface Props {
  canPlay: boolean;
  canReset: boolean;
  isBusy: boolean;
  isHistorical: boolean;
  isSeasonOver: boolean;
  /**
   * Which mutation is currently in flight, if any. The matching button
   * shows an inline spinner; all other buttons stay visible but disabled.
   */
  pendingAction?: PendingAction;
}

withDefaults(defineProps<Props>(), { pendingAction: null });

const emit = defineEmits<{
  'play-week': [];
  'play-all': [];
  'reset-scores': [];
  'reset-tournament': [];
}>();
</script>

<template>
  <div class="flex flex-wrap items-center gap-2">
    <Button
      v-if="canPlay"
      :disabled="isBusy || isHistorical || isSeasonOver"
      @click="emit('play-week')"
    >
      <Loader2
        v-if="pendingAction === 'play-week'"
        class="size-4 animate-spin"
      />
      <Play
        v-else
        class="size-4"
      />
      Play Next Week
    </Button>

    <Button
      v-if="canPlay"
      variant="secondary"
      :disabled="isBusy || isHistorical || isSeasonOver"
      @click="emit('play-all')"
    >
      <Loader2
        v-if="pendingAction === 'play-all'"
        class="size-4 animate-spin"
      />
      <FastForward
        v-else
        class="size-4"
      />
      Play All
    </Button>

    <div class="ml-auto flex gap-2">
      <Button
        v-if="canReset"
        variant="outline"
        :disabled="isBusy || isHistorical"
        @click="emit('reset-scores')"
      >
        <Loader2
          v-if="pendingAction === 'reset-scores'"
          class="size-4 animate-spin"
        />
        <RotateCcw
          v-else
          class="size-4"
        />
        Reset Scores
      </Button>

      <Button
        variant="secondary"
        @click="emit('reset-tournament')"
      >
        <FilePlus class="size-4" />
        New Tournament
      </Button>
    </div>
  </div>
</template>
