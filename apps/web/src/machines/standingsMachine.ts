import { setup, fromPromise, assign } from 'xstate';
import type { StandingsRowResource } from '@champions-league-fixture/api-sdk';
import { standingsService } from '../services/standingsService';

interface StandingsContext {
  tournamentId: number;
  rows: StandingsRowResource[];
  viewedWeek: number | null;
  error: string | null;
}

interface StandingsInput {
  tournamentId: number;
}

type StandingsEvent =
  | { type: 'LOAD' }
  | { type: 'REFETCH' }
  | { type: 'LOAD_FOR_WEEK'; week: number }
  | { type: 'RETRY' };

const fetchLive = fromPromise<StandingsRowResource[], { tournamentId: number }>(
  ({ input }) => standingsService.list(input.tournamentId),
);

const fetchForWeek = fromPromise<
  StandingsRowResource[],
  { tournamentId: number; week: number }
>(({ input }) => standingsService.forWeek(input.tournamentId, input.week));

const errorMessage = (err: unknown): string =>
  err instanceof Error ? err.message : 'Failed to load standings';

export const standingsMachine = setup({
  types: {
    context: {} as StandingsContext,
    events: {} as StandingsEvent,
    input: {} as StandingsInput,
  },
  actors: { fetchLive, fetchForWeek },
  actions: {
    assignRows: assign({
      rows: (_, params: { output: StandingsRowResource[] }) => params.output,
      error: () => null,
    }),
    assignError: assign({
      error: (_, params: { message: string }) => params.message,
    }),
  },
}).createMachine({
  id: 'standings',
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
        LOAD: 'loadingLive',
        LOAD_FOR_WEEK: {
          target: 'loadingForWeek',
          actions: assign({ viewedWeek: ({ event }) => event.week }),
        },
      },
    },
    loadingLive: {
      entry: assign({ viewedWeek: () => null }),
      invoke: {
        src: 'fetchLive',
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
          if (event.type !== 'LOAD_FOR_WEEK') {
            throw new Error('loadingForWeek requires LOAD_FOR_WEEK');
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
        REFETCH: 'loadingLive',
        LOAD: 'loadingLive',
        LOAD_FOR_WEEK: {
          target: 'loadingForWeek',
          actions: assign({ viewedWeek: ({ event }) => event.week }),
        },
      },
    },
    error: {
      on: { RETRY: 'loadingLive' },
    },
  },
});
