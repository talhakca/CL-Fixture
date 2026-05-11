<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useMachine } from '@xstate/vue';
import { useRouter } from 'vue-router';
import { Loader2 } from 'lucide-vue-next';

import { teamMachine } from '../machines/teamMachine';
import { tournamentService } from '../services/tournamentService';

import TeamCard from '@champions-league-fixture/ui/TeamCard.vue';
import ErrorBanner from '@champions-league-fixture/ui/ErrorBanner.vue';
import LoadingSpinner from '@champions-league-fixture/ui/LoadingSpinner.vue';

const router = useRouter();
const team = useMachine(teamMachine);

const isCreating = ref(false);
const createError = ref<string | null>(null);

onMounted(() => {
  team.send({ type: 'LOAD' });
});

const teams = computed(() => team.snapshot.value.context.teams);
const isLoadingTeams = computed(() => team.snapshot.value.matches('loading'));
const teamsError = computed(() => team.snapshot.value.context.error);

const onGenerate = async (): Promise<void> => {
  if (isCreating.value) return;
  isCreating.value = true;
  createError.value = null;
  try {
    const tournament = await tournamentService.create();
    await router.push({ name: 'tournament', params: { id: String(tournament.id) } });
  } catch (error) {
    createError.value = error instanceof Error ? error.message : 'Failed to start tournament';
  } finally {
    isCreating.value = false;
  }
};

const onRetryTeams = (): void => {
  team.send({ type: 'RETRY' });
};
</script>

<template>
  <div class="mx-auto max-w-5xl space-y-8 p-6">
    <header class="space-y-2 text-center">
      <h1 class="text-3xl font-bold tracking-tight">Champions League Mini Fixture</h1>
      <p class="text-sm text-muted-foreground">
        Four teams. Six weeks. Pick a season to start.
      </p>
    </header>

    <ErrorBanner
      v-if="teamsError"
      :message="teamsError"
      @retry="onRetryTeams"
      @dismiss="onRetryTeams"
    />

    <ErrorBanner
      v-if="createError"
      :message="createError"
      @retry="onGenerate"
      @dismiss="createError = null"
    />

    <LoadingSpinner v-if="isLoadingTeams" label="Loading teams…" />

    <section v-else-if="teams.length > 0" class="space-y-4">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
        Teams in this league
      </h2>
      <div class="grid gap-4 sm:grid-cols-2">
        <TeamCard v-for="t in teams" :key="t.id" :team="t" />
      </div>
    </section>

    <div class="flex justify-center">
      <button
        type="button"
        :disabled="isCreating || teams.length === 0"
        class="inline-flex h-11 items-center gap-2 rounded-md bg-primary px-8 text-base font-medium text-primary-foreground shadow-sm transition-colors hover:bg-primary/90 disabled:pointer-events-none disabled:opacity-50"
        @click="onGenerate"
      >
        <Loader2 v-if="isCreating" class="size-4 animate-spin" />
        {{ isCreating ? 'Generating…' : 'Generate New Tournament' }}
      </button>
    </div>
  </div>
</template>
