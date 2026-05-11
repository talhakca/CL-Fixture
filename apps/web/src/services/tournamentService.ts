import {
  tournamentsIndex,
  tournamentsStore,
  tournamentsShow,
  tournamentsDestroy,
  type TournamentResource,
} from '@champions-league-fixture/api-sdk';

export const tournamentService = {
  list: async (): Promise<TournamentResource[]> => {
    const response = await tournamentsIndex();
    return response.data;
  },

  create: async (): Promise<TournamentResource> => {
    const response = await tournamentsStore();
    return response.data;
  },

  findOrFail: async (id: number): Promise<TournamentResource> => {
    const response = await tournamentsShow(id);
    return response.data;
  },

  delete: async (id: number): Promise<void> => {
    await tournamentsDestroy(id);
  },
};
