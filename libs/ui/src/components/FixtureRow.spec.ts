import { render, fireEvent } from '@testing-library/vue';
import type { FixtureResource } from '@champions-league-fixture/api-sdk';
import FixtureRow from './FixtureRow.vue';

const baseFixture = (overrides: Partial<FixtureResource> = {}): FixtureResource => ({
  id: 1,
  week: 1,
  home_team: { id: 10, name: 'PSG', attack_strength: 92, defense_strength: 68 },
  away_team: { id: 20, name: 'Bayern', attack_strength: 85, defense_strength: 75 },
  home_team_id: 10,
  away_team_id: 20,
  home_goals: 3,
  away_goals: 1,
  is_played: true,
  played_at: '2026-05-10T12:00:00Z',
  winner_team_id: 10,
  ...overrides,
});

describe('FixtureRow', () => {
  it('emits start-edit with the fixture id when the pencil is clicked', async () => {
    const { getByRole, emitted } = render(FixtureRow, {
      props: {
        fixture: baseFixture({ id: 42 }),
        isEditing: false,
        isSubmitting: false,
        canEdit: true,
      },
    });

    await fireEvent.click(getByRole('button', { name: /edit score/i }));

    expect(emitted('start-edit')[0]).toEqual([42]);
  });

  it('hides the pencil when canEdit is false', () => {
    const { queryByRole } = render(FixtureRow, {
      props: {
        fixture: baseFixture(),
        isEditing: false,
        isSubmitting: false,
        canEdit: false,
      },
    });

    expect(queryByRole('button', { name: /edit score/i })).toBeNull();
  });

  it('emits save-edit with parsed integer payload', async () => {
    const { getByLabelText, getByRole, emitted } = render(FixtureRow, {
      props: {
        fixture: baseFixture({ id: 7, home_goals: 2, away_goals: 1 }),
        isEditing: true,
        isSubmitting: false,
        canEdit: true,
      },
    });

    const home = getByLabelText('Home goals') as HTMLInputElement;
    const away = getByLabelText('Away goals') as HTMLInputElement;
    await fireEvent.update(home, '5');
    await fireEvent.update(away, '0');

    await fireEvent.click(getByRole('button', { name: /save score/i }));

    expect(emitted('save-edit')[0]).toEqual([{ id: 7, homeGoals: 5, awayGoals: 0 }]);
  });

  it('disables save button while submitting', () => {
    const { getByRole } = render(FixtureRow, {
      props: {
        fixture: baseFixture(),
        isEditing: true,
        isSubmitting: true,
        canEdit: true,
      },
    });

    const saveButton = getByRole('button', { name: /save score/i });
    expect((saveButton as HTMLButtonElement).disabled).toBe(true);
  });
});
