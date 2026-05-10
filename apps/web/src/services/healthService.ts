import { health, type Health200 } from '@champions-league-fixture/api-sdk';

export const healthService = {
  check: (): Promise<Health200> => health(),
};
