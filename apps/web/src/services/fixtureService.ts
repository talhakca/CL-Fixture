import {
  tournamentsFixturesIndex,
  fixturesUpdate,
  type FixtureResource,
} from '@champions-league-fixture/api-sdk';

export const fixtureService = {
  list: async (tournamentId: number): Promise<FixtureResource[]> => {
    const response = await tournamentsFixturesIndex(tournamentId);
    return response.data;
  },

  /**
   * Edit returns the FULL updated fixture set for the fixture's tournament,
   * so the caller (machine actor) can assign it directly without a follow-up
   * GET. The fixture's tournament is resolved server-side from the id.
   */
  updateScore: async (
    fixtureId: number,
    homeGoals: number,
    awayGoals: number,
  ): Promise<FixtureResource[]> => {
    const response = await fixturesUpdate(fixtureId, {
      home_goals: homeGoals,
      away_goals: awayGoals,
    });
    return response.data;
  },
};
