import {
  tournamentsPredictionsIndex,
  tournamentsPredictionsShow,
  type PredictionRowResource,
} from '@champions-league-fixture/api-sdk';

export const predictionService = {
  latest: async (tournamentId: number): Promise<PredictionRowResource[]> => {
    const response = await tournamentsPredictionsIndex(tournamentId);
    return response.data;
  },

  forWeek: async (
    tournamentId: number,
    week: number,
  ): Promise<PredictionRowResource[]> => {
    const response = await tournamentsPredictionsShow(tournamentId, week);
    return response.data;
  },
};
