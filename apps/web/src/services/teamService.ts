import {
  teamsIndex,
  type TeamResource,
} from '@champions-league-fixture/api-sdk';

export const teamService = {
  list: async (): Promise<TeamResource[]> => {
    const response = await teamsIndex();
    return response.data;
  },
};
