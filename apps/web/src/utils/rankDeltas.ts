/**
 * Given two same-team-set rankings (sorted previous and sorted current),
 * returns a map of team_id → rank change. Positive values mean the team
 * climbed; negative values mean it dropped.
 *
 * Both inputs are assumed to be already sorted (the backend sorts
 * standings by EPL tiebreaker and predictions by probability DESC).
 */
export const computeRankDeltas = <T extends { team_id: number }>(
  previous: readonly T[],
  current: readonly T[],
): Map<number, number> => {
  const previousRank = new Map<number, number>();
  previous.forEach((row, index) => {
    previousRank.set(row.team_id, index + 1);
  });

  const deltas = new Map<number, number>();
  current.forEach((row, index) => {
    const currentRank = index + 1;
    const prev = previousRank.get(row.team_id);
    if (prev === undefined) return;
    deltas.set(row.team_id, prev - currentRank);
  });

  return deltas;
};
