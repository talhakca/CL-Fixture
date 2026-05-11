import {
  tournamentsStandingsIndex,
  tournamentsStandingsShow,
  type StandingsRowResource,
} from '@champions-league-fixture/api-sdk';

export const standingsService = {
  list: async (tournamentId: number): Promise<StandingsRowResource[]> => {
    const response = await tournamentsStandingsIndex(tournamentId);
    return response.data;
  },

  forWeek: async (
    tournamentId: number,
    week: number,
  ): Promise<StandingsRowResource[]> => {
    const response = await tournamentsStandingsShow(tournamentId, week);
    return response.data;
  },
};
