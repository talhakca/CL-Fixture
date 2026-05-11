import { setup, fromPromise, assign } from 'xstate';
import type { PredictionRowResource } from '@champions-league-fixture/api-sdk';
import { predictionService } from '../services/predictionService';

interface PredictionContext {
  tournamentId: number;
  rows: PredictionRowResource[];
  viewedWeek: number | null;
  error: string | null;
}

interface PredictionInput {
  tournamentId: number;
}

type PredictionEvent =
  | { type: 'LOAD_LATEST' }
  | { type: 'LOAD_WEEK'; week: number }
  | { type: 'REFETCH' }
  | { type: 'RETRY' };

const fetchLatest = fromPromise<PredictionRowResource[], { tournamentId: number }>(
  ({ input }) => predictionService.latest(input.tournamentId),
);

const fetchForWeek = fromPromise<
  PredictionRowResource[],
  { tournamentId: number; week: number }
>(({ input }) => predictionService.forWeek(input.tournamentId, input.week));

const errorMessage = (err: unknown): string =>
  err instanceof Error ? err.message : 'Failed to load predictions';

export const predictionMachine = setup({
  types: {
    context: {} as PredictionContext,
    events: {} as PredictionEvent,
    input: {} as PredictionInput,
  },
  actors: { fetchLatest, fetchForWeek },
  actions: {
    assignRows: assign({
      rows: (_, params: { output: PredictionRowResource[] }) => params.output,
      error: () => null,
    }),
    assignError: assign({
      error: (_, params: { message: string }) => params.message,
    }),
  },
}).createMachine({
  id: 'prediction',
  initial: 'idle',
  context: ({ input }) => ({
    tournamentId: input.tournamentId,
    rows: [],
    viewedWeek: null,
    error: null,
  }),
  states: {
    idle: {
      on: {
        LOAD_LATEST: 'loadingLatest',
        LOAD_WEEK: {
          target: 'loadingForWeek',
          actions: assign({ viewedWeek: ({ event }) => event.week }),
        },
      },
    },
    loadingLatest: {
      entry: assign({ viewedWeek: () => null }),
      invoke: {
        src: 'fetchLatest',
        input: ({ context }) => ({ tournamentId: context.tournamentId }),
        onDone: {
          target: 'ready',
          actions: {
            type: 'assignRows',
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
    loadingForWeek: {
      invoke: {
        src: 'fetchForWeek',
        input: ({ context, event }) => {
          if (event.type !== 'LOAD_WEEK') {
            throw new Error('loadingForWeek requires LOAD_WEEK');
          }
          return { tournamentId: context.tournamentId, week: event.week };
        },
        onDone: {
          target: 'ready',
          actions: {
            type: 'assignRows',
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
        REFETCH: 'loadingLatest',
        LOAD_LATEST: 'loadingLatest',
        LOAD_WEEK: {
          target: 'loadingForWeek',
          actions: assign({ viewedWeek: ({ event }) => event.week }),
        },
      },
    },
    error: {
      on: { RETRY: 'loadingLatest' },
    },
  },
});
