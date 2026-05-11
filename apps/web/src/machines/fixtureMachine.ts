import { setup, fromPromise, assign } from 'xstate';
import type {
  FixtureResource,
  PredictionRowResource,
  StandingsRowResource,
} from '@champions-league-fixture/api-sdk';
import { fixtureService } from '../services/fixtureService';
import { gameService, type PlayWeekResult } from '../services/gameService';

interface LastPlayMeta {
  previousStandings: StandingsRowResource[];
  previousPredictions: PredictionRowResource[];
  at: number;
}

interface FixtureContext {
  tournamentId: number;
  fixtures: FixtureResource[];
  totalWeeks: number;
  lastPlayedWeek: number;
  /**
   * Snapshot of standings + predictions captured by the last play-week
   * mutation, used by the page to render rank-change arrows. Cleared by
   * any other mutation; UI reads `at` to know when the snapshot is fresh.
   */
  lastPlayMeta: LastPlayMeta | null;
  error: string | null;
}

interface FixtureInput {
  tournamentId: number;
}

type FixtureEvent =
  | { type: 'PLAY_WEEK' }
  | { type: 'PLAY_ALL' }
  | { type: 'EDIT_SCORE'; fixtureId: number; homeGoals: number; awayGoals: number }
  | { type: 'RESET_SCORES' }
  | { type: 'RETRY' };

const computeWeekStats = (
  fixtures: FixtureResource[],
): { totalWeeks: number; lastPlayedWeek: number } => {
  const totalWeeks = fixtures.reduce((max, f) => Math.max(max, f.week), 0);
  const lastPlayedWeek = fixtures.reduce(
    (max, f) => (f.is_played ? Math.max(max, f.week) : max),
    0,
  );
  return { totalWeeks, lastPlayedWeek };
};

const errorMessage = (err: unknown): string =>
  err instanceof Error ? err.message : 'Unexpected fixture error';

const fetchFixtures = fromPromise<FixtureResource[], { tournamentId: number }>(
  ({ input }) => fixtureService.list(input.tournamentId),
);

const playWeek = fromPromise<PlayWeekResult, { tournamentId: number }>(
  ({ input }) => gameService.playWeek(input.tournamentId),
);

const playAll = fromPromise<FixtureResource[], { tournamentId: number }>(
  ({ input }) => gameService.playAll(input.tournamentId),
);

const resetScores = fromPromise<FixtureResource[], { tournamentId: number }>(
  ({ input }) => gameService.resetScores(input.tournamentId),
);

const editScore = fromPromise<
  FixtureResource[],
  { fixtureId: number; homeGoals: number; awayGoals: number }
>(({ input }) => fixtureService.updateScore(input.fixtureId, input.homeGoals, input.awayGoals));

export const fixtureMachine = setup({
  types: {
    context: {} as FixtureContext,
    events: {} as FixtureEvent,
    input: {} as FixtureInput,
  },
  actors: {
    fetchFixtures,
    playWeek,
    playAll,
    resetScores,
    editScore,
  },
  actions: {
    assignFixtures: assign({
      fixtures: (_, params: { output: FixtureResource[] }) => params.output,
      totalWeeks: (_, params: { output: FixtureResource[] }) =>
        computeWeekStats(params.output).totalWeeks,
      lastPlayedWeek: (_, params: { output: FixtureResource[] }) =>
        computeWeekStats(params.output).lastPlayedWeek,
      lastPlayMeta: () => null,
      error: () => null,
    }),
    assignPlayWeekResult: assign({
      fixtures: (_, params: { result: PlayWeekResult }) => params.result.fixtures,
      totalWeeks: (_, params: { result: PlayWeekResult }) =>
        computeWeekStats(params.result.fixtures).totalWeeks,
      lastPlayedWeek: (_, params: { result: PlayWeekResult }) =>
        computeWeekStats(params.result.fixtures).lastPlayedWeek,
      lastPlayMeta: (_, params: { result: PlayWeekResult }) => ({
        previousStandings: params.result.previousStandings,
        previousPredictions: params.result.previousPredictions,
        at: Date.now(),
      }),
      error: () => null,
    }),
    assignError: assign({
      error: (_, params: { message: string }) => params.message,
    }),
  },
}).createMachine({
  id: 'fixture',
  initial: 'bootstrapping',
  context: ({ input }) => ({
    tournamentId: input.tournamentId,
    fixtures: [],
    totalWeeks: 0,
    lastPlayedWeek: 0,
    lastPlayMeta: null,
    error: null,
  }),
  states: {
    bootstrapping: {
      invoke: {
        src: 'fetchFixtures',
        input: ({ context }) => ({ tournamentId: context.tournamentId }),
        onDone: {
          target: 'ready',
          actions: {
            type: 'assignFixtures',
            params: ({ event }) => ({ output: event.output }),
          },
        },
        onError: {
          target: 'error',
          actions: {
            type: 'assignError',
            params: ({ event }) => ({ message: errorMessage(event.error) }),
          },
        },
      },
    },
    ready: {
      on: {
        PLAY_WEEK: 'playingWeek',
        PLAY_ALL: 'playingAll',
        EDIT_SCORE: 'editingScore',
        RESET_SCORES: 'resettingScores',
      },
    },
    playingWeek: {
      tags: ['mutation'],
      invoke: {
        src: 'playWeek',
        input: ({ context }) => ({ tournamentId: context.tournamentId }),
        onDone: {
          target: 'ready',
          actions: {
            type: 'assignPlayWeekResult',
            params: ({ event }) => ({ result: event.output }),
          },
        },
        onError: {
          target: 'error',
          actions: {
            type: 'assignError',
            params: ({ event }) => ({ message: errorMessage(event.error) }),
          },
        },
      },
    },
    playingAll: {
      tags: ['mutation'],
      invoke: {
        src: 'playAll',
        input: ({ context }) => ({ tournamentId: context.tournamentId }),
        onDone: {
          target: 'ready',
          actions: {
            type: 'assignFixtures',
            params: ({ event }) => ({ output: event.output }),
          },
        },
        onError: {
          target: 'error',
          actions: {
            type: 'assignError',
            params: ({ event }) => ({ message: errorMessage(event.error) }),
          },
        },
      },
    },
    editingScore: {
      tags: ['mutation'],
      invoke: {
        src: 'editScore',
        input: ({ event }) => {
          if (event.type !== 'EDIT_SCORE') {
            throw new Error('editingScore reached without EDIT_SCORE event');
          }
          return {
            fixtureId: event.fixtureId,
            homeGoals: event.homeGoals,
            awayGoals: event.awayGoals,
          };
        },
        onDone: {
          target: 'ready',
          actions: {
            type: 'assignFixtures',
            params: ({ event }) => ({ output: event.output }),
          },
        },
        onError: {
          target: 'error',
          actions: {
            type: 'assignError',
            params: ({ event }) => ({ message: errorMessage(event.error) }),
          },
        },
      },
    },
    resettingScores: {
      tags: ['mutation'],
      invoke: {
        src: 'resetScores',
        input: ({ context }) => ({ tournamentId: context.tournamentId }),
        onDone: {
          target: 'ready',
          actions: {
            type: 'assignFixtures',
            params: ({ event }) => ({ output: event.output }),
          },
        },
        onError: {
          target: 'error',
          actions: {
            type: 'assignError',
            params: ({ event }) => ({ message: errorMessage(event.error) }),
          },
        },
      },
    },
    error: {
      on: { RETRY: 'bootstrapping' },
    },
  },
});
