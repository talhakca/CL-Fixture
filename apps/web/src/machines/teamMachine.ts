import { setup, fromPromise, assign } from 'xstate';
import type { TeamResource } from '@champions-league-fixture/api-sdk';
import { teamService } from '../services/teamService';

interface TeamContext {
  teams: TeamResource[];
  error: string | null;
}

type TeamEvent = { type: 'LOAD' } | { type: 'RETRY' };

export const teamMachine = setup({
  types: {
    context: {} as TeamContext,
    events: {} as TeamEvent,
  },
  actors: {
    loadTeams: fromPromise(() => teamService.list()),
  },
}).createMachine({
  id: 'team',
  initial: 'idle',
  context: {
    teams: [],
    error: null,
  },
  states: {
    idle: {
      on: { LOAD: 'loading' },
    },
    loading: {
      invoke: {
        src: 'loadTeams',
        onDone: {
          target: 'ready',
          actions: assign({
            teams: ({ event }) => event.output,
            error: () => null,
          }),
        },
        onError: {
          target: 'error',
          actions: assign({
            error: ({ event }) =>
              event.error instanceof Error ? event.error.message : 'Failed to load teams',
          }),
        },
      },
    },
    ready: {
      on: { LOAD: 'loading' },
    },
    error: {
      on: { RETRY: 'loading' },
    },
  },
});
