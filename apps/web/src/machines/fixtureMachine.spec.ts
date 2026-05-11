import { createActor } from 'xstate';
import type { FixtureResource } from '@champions-league-fixture/api-sdk';
import { fixtureMachine } from './fixtureMachine';
import { fixtureService } from '../services/fixtureService';
import { gameService } from '../services/gameService';

vi.mock('../services/fixtureService');
vi.mock('../services/gameService');

const baseFixture = (overrides: Partial<FixtureResource> = {}): FixtureResource => ({
  id: 1,
  week: 1,
  home_team: { id: 10, name: 'PSG', attack_strength: 92, defense_strength: 68 },
  away_team: { id: 20, name: 'Bayern', attack_strength: 85, defense_strength: 75 },
  home_team_id: 10,
  away_team_id: 20,
  home_goals: null,
  away_goals: null,
  is_played: false,
  played_at: null as unknown as string,
  winner_team_id: null,
  ...overrides,
});

const startActor = (tournamentId = 1): ReturnType<typeof createActor<typeof fixtureMachine>> =>
  createActor(fixtureMachine, { input: { tournamentId } }).start();

const waitForState = (
  actor: ReturnType<typeof createActor<typeof fixtureMachine>>,
  predicate: (snapshot: ReturnType<typeof actor.getSnapshot>) => boolean,
  timeoutMs = 200,
): Promise<void> =>
  new Promise((resolve, reject) => {
    if (predicate(actor.getSnapshot())) {
      resolve();
      return;
    }
    const timer = setTimeout(() => reject(new Error('state predicate timeout')), timeoutMs);
    const sub = actor.subscribe((snapshot) => {
      if (predicate(snapshot)) {
        clearTimeout(timer);
        sub.unsubscribe();
        resolve();
      }
    });
  });

describe('fixtureMachine', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('lands in ready with empty fixtures when bootstrap returns nothing', async () => {
    vi.mocked(fixtureService.list).mockResolvedValue([]);
    const actor = startActor(1);

    await waitForState(actor, (s) => s.matches('ready'));
    expect(actor.getSnapshot().context.fixtures).toEqual([]);
    expect(actor.getSnapshot().context.totalWeeks).toBe(0);
  });

  it('lands in ready with fixtures populated when bootstrap returns data', async () => {
    const fixtures = [baseFixture({ id: 1 }), baseFixture({ id: 2, week: 2 })];
    vi.mocked(fixtureService.list).mockResolvedValue(fixtures);
    const actor = startActor(1);

    await waitForState(actor, (s) => s.matches('ready'));
    expect(actor.getSnapshot().context.fixtures).toHaveLength(2);
    expect(actor.getSnapshot().context.totalWeeks).toBe(2);
  });

  it('PLAY_WEEK transitions through playingWeek and assigns the response', async () => {
    const initial = [baseFixture({ id: 1 })];
    const afterPlay = [baseFixture({ id: 1, is_played: true, home_goals: 2, away_goals: 1 })];

    vi.mocked(fixtureService.list).mockResolvedValueOnce(initial);
    vi.mocked(gameService.playWeek).mockResolvedValue({
      fixtures: afterPlay,
      week: 1,
      previousStandings: [],
      previousPredictions: [],
    });

    const actor = startActor(1);
    await waitForState(actor, (s) => s.matches('ready'));

    actor.send({ type: 'PLAY_WEEK' });
    expect(actor.getSnapshot().value).toBe('playingWeek');

    await waitForState(actor, (s) => s.matches('ready') && s.context.lastPlayedWeek === 1);
    expect(actor.getSnapshot().context.fixtures[0]?.is_played).toBe(true);
    expect(actor.getSnapshot().context.lastPlayMeta).not.toBeNull();
    expect(vi.mocked(gameService.playWeek)).toHaveBeenCalledWith(1);
  });

  it('actor failure routes to error and RETRY returns to bootstrapping', async () => {
    vi.mocked(fixtureService.list).mockRejectedValueOnce(new Error('boom'));
    const actor = startActor(1);

    await waitForState(actor, (s) => s.matches('error'));
    expect(actor.getSnapshot().context.error).toBe('boom');

    vi.mocked(fixtureService.list).mockResolvedValueOnce([]);
    actor.send({ type: 'RETRY' });

    await waitForState(actor, (s) => s.matches('ready'));
  });
});
