## How the facts were found

- **Engine:** the frozen prototype `engine_x`, driven through `harness.py` (the checker's own SGF loading, target
  and areas). Scripts: `inv.py` (search wrapper and log), `analyze.py` (one leaf), `follow.py` (after one
  approach), `extra.py` (who needs the ko), `bounds_run.py` (sound bounds), `collect.py` (this inventory).
- **Budgets:** every counted search ran at 400k nodes; an undecided one was retried once at 2M (the engine itself
  retries each budget once with fresh move ordering, so a fully undecided search used 4.8M nodes). Exceptions,
  to stay inside the hour: the approach alternatives, the no-ko tests and the wide re-runs of profiles that were
  already undecided in the narrow area ran at 400k only. Every search is listed in `inventory.json` with its
  area, options, threats, nodes and result.
- **Areas:** the checker's narrow area first. The wide area was run when the narrow count or a narrow profile was
  undecided or one of its moves touched the area's wall or fringe. c081 ran only in the judges' 14-point corner
  frame, as the task asked. 2406's first leaf and 10408 were also run in hand-picked corner frames (listed).
- **End playout:** `end=1` in every counted search (two passes do not end the game while a defender chain in the
  target's area is in atari).
- **Count d:** the least s - o (solver's threats minus the opponent's) at which the solver wins, found by galloping
  from 0.
- **Ko shapes:** a single stone in atari whose capture is a ko capture. The holder owns the stone, the taker can
  capture it. P_taker is the position right after the taker's capture (holder to move, banned); P_holder is P_taker
  followed by the holder's immediate retake (taker to move, banned).
- **Forced-pass profile (design D3):** X holds the ko (P_X), the other side Y must pass its next k turns, X has no
  threats and Y has 4 (more when |d| is larger). t = least k at which X still wins outright; n = t - 1 is X's
  approach count. n = 0 means X's capture is a real threat: one tenuki by Y and X wins. k was scanned downward from
  5; outright is monotone in k, so a decided failure at k proves n >= k.
- **Held-ko check (design D4):** the k = 1 search again with Y barred from retaking at the ko for the whole search.
  If X now wins, Y's only defence in each round was retaking this ko, so the rounds are approaches, not stages.
- **Approach points:** every first move a for which X, after Y's pass and a, still wins outright with t - 1 more
  forced passes.
- **After one approach (the switch test):** from P_X, Y passes once, X plays one of its approach points, Y retakes
  at the ko for free (the ban was lifted). Then: Y's own profile there (does Y's capture threaten now?), X's
  profile after X retakes with a threat, and the count d there.
- **Who needs the ko:** the leaf's counted search at 0 threats each with both ko points barred for both sides,
  then for the solver only, then for the opponent only.
- **Bounds:** when a forced-pass search stayed undecided, the same search with one side's moves restricted to a
  short list. Restricting Y can only help X, so "X still fails" proves n >= k; restricting X can only hurt X, so
  "X still wins" proves n <= k - 1.
