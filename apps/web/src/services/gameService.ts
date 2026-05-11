import {
  tournamentsPlayWeek,
  tournamentsPlayAll,
  tournamentsResetScores,
  type FixtureResource,
  type PredictionRowResource,
  type StandingsRowResource,
} from '@champions-league-fixture/api-sdk';

export interface PlayWeekResult {
  fixtures: FixtureResource[];
  week: number;
  previousStandings: StandingsRowResource[];
  previousPredictions: PredictionRowResource[];
}

/**
 * Each mutation returns the full updated fixture set for the tournament,
 * so the consuming machine can assign it directly without a follow-up
 * `fixtureService.list()` call.
 *
 * play-week additionally bundles the standings + prediction snapshot from
 * the week BEFORE play, so the page can render rank-change arrows without
 * a second round trip.
 */
export const gameService = {
  playWeek: async (tournamentId: number): Promise<PlayWeekResult> => {
    const response = await tournamentsPlayWeek(tournamentId);
    return {
      fixtures: response.data,
      week: response.meta.week,
      previousStandings: response.meta.previous_standings as unknown as StandingsRowResource[],
      previousPredictions: response.meta.previous_predictions as unknown as PredictionRowResource[],
    };
  },

  playAll: async (tournamentId: number): Promise<FixtureResource[]> => {
    const response = await tournamentsPlayAll(tournamentId);
    return response.data;
  },

  resetScores: async (tournamentId: number): Promise<FixtureResource[]> => {
    const response = await tournamentsResetScores(tournamentId);
    return response.data;
  },
};
