# Group F, design 2: decide the stalled parts another way

Worktree: /home/user/tsumego-hero/.claude/worktrees/wf_928a9bec-85e-2 (detached at 4537ef85, then the two commits below).
Patch: /tmp/claude-0/proto/research/kofix3/F/design-2.patch (git format-patch 4537ef85..HEAD, two commits).
Scripts in this folder: h.py (a narrow Solver as validate.py builds it; CHECKER selects the checker), label.py (one
leaf's label with its parts), probe.py / tree.py / expand_test.py (single searches, solvemoves tables, the deeper reading
alone), oracle.py (the merged rules with given searches decided as the research decided them), unsettled_test.py (D4's
readings with their own search made undecided), compare.py / times.py / parts.py / fallthrough.py / d4labels.py
(regression against base-F-part0/1 and base-F-rest0/1.jsonl), split_a.py / split_design.py (commit A's files from the
final ones), reflow.py.

## Where each stalled part came from (merged checker, narrow, 200,000)

- k023 10403: the full count's searches at s=2 and s=3 (W to move) stall at 15-16M; profile B k=2 at P_S stalls at
  14M. Decided by hand (research) through B19 A19! and F19 A17. With both decided the merged rules print A-2KO+
  (oracle.py, k023.oracle.txt): n_S = 2 from fills (C18, D19), which the owner's 16 November answer rejects (A-1KO-).
- k035 13489: the full count at s=1 (B to move) stalls at 19M; so the first take falls back to the mode lines
  (preparation J18, a tenuki) and v at that P_S stalls too. With the count decided: A-1KO- (k035.oracle.txt).
- k041 13547: the full count at s=0 (W to move) stalls at 24M; the first take falls back to the leaf's one ko shape and
  both k=1 tests there stall at 14M. With the count decided: 10kKO (k041.oracle.txt).
- k043 13547: the full count at s=1 (B to move) stalls at 14M. With it decided the merged D4 stage reading prints KO-2
  (k043.oracle.txt): wrong, the research's A-1KO- needs D4 to see that White answers C19 at no cost.
- k049 13661: nothing of its label is undecided but the shifted check and the bent four. The wrong part is the main ko:
  D12's candidate G19 leads the principal line into a ko the line makes (A15/A16), and candidate A15's line is
  inconsistent because the rule-0 search after A15 is a White win only through 71 adjudicated repetitions (with cycle
  rule 1 Black wins there). White's one-pass test on the leaf's own A19/B19 ko stays undecided at 40M nodes even with
  the deeper reading (k049.wtest.txt): its proof runs through the narrow area's G19 ko and transposes.

## The change

Commit A (D4 only, tsumego.py and design.md): the two second-ko readings. Commit B (engine.cpp, tsumego.py, design.md):
`pre=` and the deeper reading. Commit A alone changes no label of the merged checker whose full count is undecided; on
top of a fix that decides the counts (design 1's) it gives k023 and k043 their labels (oracle runs below).

1. engine.cpp: solvex/solvemoves `pre=i;j;...`: the moves are replayed through generate() (a ban break spends a threat),
   and the positions they pass through stay on the path; solvemoves puts them under every root move and decides a root
   move that repeats one by the cycle rule. So a table below the root is the table the root's own search sees there.
2. tsumego.py count(..., deep=True) (the full count's threshold and the profiles' outright tests only): when the existing
   escalation (1x, 5x, one-ply) leaves the search undecided, deeper() reads its children: proof numbers over solvemoves
   tables (pre=), the attacker's moves by pn, the defender's by dn, ties by the other number; each move at the base budget
   with a fresh retry, depth 6, 10M nodes per search, 30M per label, never in the last 150 s before the time limit, and
   none after a reading of the label that decided nothing (hf=1 throughout). The first regression run (20M / 40M, no
   stop after a failure; after-F.run1.jsonl, 18 leaves) turned 13489 "E19 D18 B19 B18 C19 D19 A19 A16 D17 C18 A18 A17
   B19 A19" (class ?, every count balance undecided, 235 s) into a time-out (328 s): it read two balances. Stopped there.
3. keep_kills(): the attacker's wins the reading finds are stored as the counted searches of their positions (exact
   without the path: under cycle rule 0 in the counted search a repetition is a seki, since a ban break spends a threat
   and changes the position key, so the path only helps the defender); move_tables: the root's table with the decided
   moves serves counted_moves for the same arguments (D12's leaf moves).
4. second_ko_readings() (D4), only with d_full decided, the other side direct (n_Y = 0), each holding only when its
   A-form counts exactly d_full:
   - answered at no cost (k043): the stage (n_X = 1) rests on a second-ko capture, but the A-form with the capture does
     not count d_full; X's approach is its best free move in v's game that leaves Y a free retake. Where that A-form does
     not count d_full either, or is undecided: the approach count is undecided (held_ok None, "0 or 1"), never the stage.
   - switching (k023): n_X >= 2 and a candidate free move is a second-ko capture: n_X - 1 approaches, v after them,
     the owner's 10403 answer. Where that v is undecided or a fill: undecided between n_X - 1 and n_X; where its A-form
     does not count d_full: the n_X approaches stand.
5. Label text and warnings name both readings and the undecided case; design.md: section 5 (pre=), D4 (the two
   readings), section 9 (the deeper reading, the table row of the undecided D4 reading), section 10 (the four leaves).
