# Question 1 inventory: 10kKO, approach forms and the symmetric case

Facts behind question 1, from the frozen engine `engine_x` through `harness.py`. One record per leaf is in
`inventory.json` (31 leaves: the 27 10kKO leaves, the 3 approach-form leaves that looked mutual, c081). The 30
approach-form siblings used as controls are in `inventory_controls.json`. Every claim below comes from a search
listed in those files with its area, options, budget and nodes. Engine time used: about 31 minutes.

The export has 27 10kKO leaves in **15** problems (the task's list of 15 ids is complete; "16" was a miscount).

## 1. The owner's three definitions as tests

Each side's ko capture gets a **forced-pass count n** (design D3): the side that just captured needs n free moves,
besides the one that settles it, before its capture wins outright. n = 0 means the capture is a real threat.

| owner's words | test on the searches |
|---|---|
| **Approach ko**: "one side needs to do approaches to start a normal ko, while the other side can win by winning one ko at any time" | one side n >= 1, the other side n = 0 |
| **10kKO**: "one side needs to do investment, which is then wasted, to start a ko no matter who started" | both sides n = 1; after one approach by either side, the other side retakes for free and the ko is then direct both ways (both n = 0). The investment only hands the opponent the ko. In every such leaf the approach point is shared. |
| **Symmetric** ("normal KO"): "both need extra approach, and it can switch there and back" | both sides n >= 1; after one side's approach and the free retake, the retaker still needs an approach of its own (the ko switches back). After one approach each it is direct and the first taker holds it: the approaches cancel. |

Two more facts were recorded because they turned out to separate the leaves:
- **Who needs the ko**: the leaf searched with both ko points barred for both sides. If the defender then lives, only
  the attacker needs the ko (a seki-like standoff otherwise).
- **Investment before any ko**: whether a move is needed before any ko capture exists (no ko shape on the board).

## 2. The leaves at a glance

Counts are from the narrow area unless marked; "?" is undecided at 4.8M nodes. Points in brackets are the
approach points (every first move after which the side still wins with one fewer forced pass).

| leaf | tree | goal, to move | ko capture at the leaf | count d | forced-pass counts | after one approach | who needs the ko | fit |
|---|---|---|---|---|---|---|---|---|
| c081 (frame) | KO+ | LIVE, W | none: W first plays A19 | 0 | W 1 (A17 B17), B 1 (A17 B17) | direct, no switch back | attacker | **10kKO** |
| 10391_3 | 10kKO | KILL, W | B at C19 | 1 | B 1 (A17 B17), W 1 (A17 B17) | direct, no switch back | attacker | **10kKO** |
| 10051_11 | 10kKO | KILL, W | B at A19 | 1 | B 1 (D18 D19), W 1 (D18 D19) | direct, no switch back | attacker | **10kKO** |
| 15513_0 | 10kKO | KILL (W solves), B | W at C19 | 1 | W 1 (A17 B17), B 1 (A17 B17) | direct, no switch back | attacker | **10kKO** |
| 15516_0 | 10kKO | KILL, W | B at A19 | 1 | B 1 (A16 B16), W 1 (A16 B16) | direct, no switch back | attacker | **10kKO** |
| 13547_25 | 10kKO | KILL, W | W at A18 | ? | W 1 (A16 C17), B 1 (A16 C17) | direct, no switch back | defender | **10kKO** |
| 10393_0 | 10kKO | LIVE, W | none: W first plays A19 | 0 | W 2 (A17 B17 + E19), B 1 (A17 B17) | at A17/B17 as 10kKO; E19 first switches back | attacker | none (10kKO + 1) |
| 13549_13 | 10kKO | KILL, W | W at B19 | 1 | W 1 (B17 C17), B 2 (B17 C17 + E19) | at B17/C17 as 10kKO; E19 first switches back | defender | none (10kKO + 1) |
| 7487_97 | A-1KO- | KILL, B | W at B19; A17 banned (W just took A18) | 2 | main ko: B 1 (A15 G19), W 1 (E18, or B19 in the other ko); other ko: W 0, B 1-2 | switches there and back, then direct (count 0) | two kos | **symmetric** |
| 10403_0 | A+1KO- | KILL, W | B at A17; W at B19 | >= 2 | A17 ko: B 1, W 1, partly different points; A19 ko: W 0, B 1-2 | switches back for most approaches | two kos | **symmetric** |
| 13547_0 | 10kKO | KILL, W | W at A17 | 2 | W 0, B 1 (A19 C17) | n/a | defender | **approach ko** (exactly A-1KO-) |
| 13541_0 | 10kKO | KILL, B | W at B19 | 1 | W 0, B 3 | n/a | defender | **approach ko** |
| 13541_5 | 10kKO | KILL, B | W at B19 | 1 | W 0, B 3 | n/a | defender | **approach ko** |
| 11280_8 | 10kKO | KILL, B | W at B19 | 1 | W 0, B 3 | n/a | defender | **approach ko** |
| 11280_17 | 10kKO | KILL, B | W at B19 | ? | W 0, B 4 | n/a | defender | **approach ko** |
| 13475_7 | 10kKO | LIVE, B | B at B19 | -1 | B 0, W 3 | n/a | defender | **approach ko** |
| 2406_0 | 10kKO | LIVE, W | none: a throw-in starts it | -2 (narrow), -1 (frame) | B 0; W 1 (narrow) or 2 (frame) | n/a | attacker | **approach ko** |
| 10408_3 | 10kKO | LIVE, B | B19 banned (W just took A19) | ? | W 0 (narrow, frame), B 0-1 | n/a | - | none (not mutual) |
| 11279_0 | 10kKO | KILL, B | W at B19 | ? | W 0, B 0-3 | n/a | defender | none (not mutual) |
| 11282_6, 11282_7 | 10kKO | KILL, W | W at B19 | ? | W 0, B 0-2 | n/a | defender | none (not mutual) |
| 13547_18 | 10kKO | KILL, B | W at A18 | ? | W 0, B 0-1 | n/a | defender | none (not mutual) |
| 11282_4 | 10kKO | KILL, W | W at B19 | ? | W 1, B 2 | retaker 0-1 | defender | none |
| 11282_5 | 10kKO | KILL, W | W at B19 | ? | W 1, B 0-2 | - | defender | none |
| 13547_20 | 10kKO | KILL, B | W at A18 | ? | W 0-1, B 2 | C18 switches back, A16 does not | defender | none |
| 10051_23 | 10kKO | KILL, W | W at B19 | ? | W 0-1, B 1 (D18 D19) | after B's approach W's retake is direct | defender | none |
| 13547_19 | 10kKO | KILL, W | W at A18 and at E19 | ? | two kos, counts 0-2 | - | two kos | none |
| 13547_26 | 10kKO | KILL, W | B at A19 | ? | B 0-1, W 0-1 | - | attacker | none |
| 13661_4 | 10kKO | KILL, B | W at B19 | 2 | W 0-1, B 0-1 | - | defender | none |
| 9883_2 | A+1KO+ | KILL, W | B at D18; F19 banned | ? | mostly undecided; B's D18 capture n = 0 | - | ? | none |
| 2406_3 | 10kKO | LIVE, W | no ko | ALIVE | - | - | - | none (no ko; research c016: ALIVE) |

## 3. The pattern

**3.1 The textbook shape (c081, 10391_3, 10051_11, 15513_0, 15516_0).** All five share the same facts:
- A capturing race in the corner between the defender's group and the attacker's corner stones, with two shared
  liberties and a ko next to them.
- Each side's capture needs exactly one approach, and the approach is the same pair of points: the two shared
  liberties. The held-ko check confirms both as approaches.
- After one approach by either side, the other side retakes for free, and both captures are then real threats.
  The ko never switches back. Whoever invests, the opponent ends up holding a direct ko.
- If the ko is never played, the defender lives. Only the attacker needs it.
- The count is that of a one-approach ko for the attacker: 1 for KILL (A-1KO+), 0 for c081 (LIVE, A+1KO-).
- The count after the attacker's approach equals the leaf's count, so the attacker's investment buys nothing but
  the right to fight the ko. That is the owner's "investment, which is then wasted".

This is the owner's 10kKO definition, and **not** his symmetric case: it cannot switch there and back. Four of the
five carry 10kKO in the tree; c081 carries KO+ (the research question). 13547_25 has the same profile, but there
the attacker wins if the ko is never played.

**3.2 The symmetric case exists, and the tree writes it as approach forms.** 7487_97 (A-1KO-) and 10403_0 (A+1KO-):
- Both have two ko shapes on the board. The main ko is mutual. In 7487_97 the two sides' approach points differ
  (Black A15 or G19, White E18); in 10403_0 they partly overlap (C18, D19), and most approaches still switch back.
- After one side's approach, the free retake still needs an approach of its own (held-ko check: approaches, not
  stages).
- Checked on 7487_97 to the end: after one approach each, the ko is direct, the side that took first holds it and
  the count is 0. The approaches cancel. This is "it can switch there and back", which the owner now calls a normal
  KO.
- The same switch-back appears inside three 10kKO leaves when the side with the extra approach plays it first
  (10393_0, 13549_13 at E19, 13547_20 at C18).

**3.3 Many 10kKO leaves are approach kos by the owner's own definition.** In 13547_0, 13541_0, 13541_5, 11280_8,
11280_17, 13475_7 and 2406_0, one side's capture is a real threat (n = 0) and the other side needs approaches.
- In all but 2406_0 the defender is the side that needs the ko: the attacker kills if it is never played.
- 13547_0 is exactly A-1KO- by profile and count: White takes first, Black needs one approach, count 2. Its sibling
  leaf 1 carries A-1KO+ with the same profile and count 2.
- Five more 10kKO leaves are not mutual either way: one side's capture is a decided real threat and only the other
  count is undecided (10408_3, where the attacker White is direct; 11279_0, 11282_6, 11282_7, 13547_18, where the
  defender White is direct).

**3.4 The controls: the tree's approach forms follow the forced-pass count.** These are the 30 approach-form
siblings in the same problems.
- In 25, one side's capture is a real threat and the other side's count is exactly the tree's number. For
  example, 13661's A-4KO+ and A-3KO+ leaves have n = 4 and 3, 11282's A-2KO has n = 2, and the A-1 leaves have n = 1.
- One differs (13547 leaf 7: A-2KO+, n = 1); two are undecided; one has no ko on the board (11279 leaf 13).
- One control, 13549 leaf 9 (A-1KO+), has the textbook profile of 3.1. Both sides need one approach at B17/C17,
  the ko is direct after one approach, and the defender lives if the ko is never played. Its count is 1.

**3.5 What separates the labels.**
- *10kKO from the symmetric case* (the owner's question): the switch test. In the textbook shape the approach point
  is shared, one investment makes the ko direct with the opponent holding it, and it never switches back (3.1).
  In 7487_97 the points differ; in 10403_0 they partly overlap but the second ko is involved. In both, the ko
  switches back once and the approaches cancel (3.2). Comparing the point sets alone is not enough (10403_0); the
  switch test decides every decided mutual leaf.
- *The tree's 10kKO from the tree's approach forms*: no structural fact separates them.
  - The textbook profile carries 10kKO in 4 of 6 cases (c081 KO+, 13549 leaf 9 A-1KO+).
  - The one-sided approach profile carries an A-form in 25 controls, but also 10kKO in 7 leaves, plus 5 more where
    one side is decided direct.
  - In KILL problems besogo grades 10kKO and every A-1 form the same, so the choice never changed grading.
- *A usable reading for the checker*, if the owner agrees:
  - a mutual count that is direct after one approach by either side (no switch back) is 10kKO;
  - a mutual count that switches back is a plain KO;
  - a one-sided count is an approach ko, whatever the tree says.
  - The tree's 10kKO on one-sided leaves would then be reported as notation.

**3.6 The forced-pass count overstates semeai approaches.** On 13541_0, 13541_5 and 11280_8 the attacker's
forced-pass count is 3, but the leaf count is 1: Black needs only one threat more than White. On 13475_7 White's
forced-pass count is 3, but White needs only 2 threats more than Black. On the counted line of 13541_0 (the
winning line at balance 1), White's extension at F19 is answered by Black's G19, so that outside fill costs Black
no tempo, while the forced-pass test (White must pass) counts it as an approach. This is design risk 2 (sente
approaches). A label from design D6 would print A-3KO for these, against the tree's 10kKO and the count of one
approach.

## 4. Corrections to what we told the owner and to the design

- **10408 is not mutual.** White's capture at A19 is a real threat (n = 0, decided in the narrow area and in a
  corner frame), because it threatens A17 against Black's A18. Question 1 cited 10408 as "each side's capture needs
  one approach". That came from the static batch test; Black's own count stays undecided (0 or 1).
- **besogo does not grade 10403's and 9883's labels like 10kKO.** For KILL, 10kKO equals approaches = -1. A+1KO-
  and A+1KO+ have approaches = +1 and rank above 10kKO for Black. Only 7487's A-1KO- is graded the same. Design
  section 11 risk 4, open question 2 and question 1's wording ("besogo grades them the same") are wrong for two of
  the three leaves.
- **10403_0's label does not match its count.** Black fails at balances 0 and 1 (decided in both areas). A+1KO-
  implies Black wins at equal threats.
- **2406_0 in the design's corner frame** (A15 to F19, the points of `c2406.py`) gives count -1, with White
  needing 2 approaches and Black's capture direct. It does not give the design's "d = 0, mutual 1/1".
- **10051** has two 10kKO leaves: leaf 11 is textbook, leaf 23 is undecided. Black's F19 is in atari there, an
  outside resource on the open edge.
- **D9 as designed** ("mutual with the attacker's count 1 is 10kKO") would print 10kKO for 7487_97 and 10403_0,
  the owner's symmetric case, and for 13549 leaf 9. It needs the same-point/switch test of 3.5.

## 5. Positions for diagrams (data for the page builder)

Each position is given as moves from the leaf; `!` marks a retake that uses a threat and `pass` a tenuki. These are
for building diagrams only; the page itself must show diagrams, not these runs.

| example | position (moves from the leaf) | what it shows |
|---|---|---|
| c081 textbook | leaf | no ko yet: White must first play A19 |
| | `A19 pass C19` | White took first; neither capture threatens yet |
| | `A19 pass C19 pass A17 B19` | White's investment at A17, Black's free retake now threatens B17 (count 0) |
| | `A19 pass C19 B19! pass A17 C19` | the mirror: Black invests at A17, White's free retake threatens (count 1) |
| 15516_0 textbook (smallest) | leaf, `pass A19`, `pass A19 pass B16 A18` | Black takes, invests at B16, White's free retake is direct |
| 7487_97 symmetric | leaf | White just took at A18 |
| | `A17!` | Black retakes with a threat; not yet a threat itself |
| | `A17! pass G19 A18` | Black approaches at G19, White retakes free, but White's capture is not a threat yet |
| | `A17! pass G19 A18 pass E18 A17` | White approaches at E18, Black retakes free: direct, Black holds it, count 0 |
| 13547_0 vs leaf 1 | 13547_0's leaf, then `A17` (White takes first); sibling leaf 1 of 13547 | the same approach-ko profile labelled 10kKO and A-1KO+ |

## 6. Undecided and open points

- **Counts undecided** (4.8M nodes, narrow and wide): 10051_23, 10408_3 (also the frame), 11279_0, 11280_17,
  11282_4 to 11282_7, 13547_18, 13547_19, 13547_20, 13547_25, 13547_26, 9883_2, 10403_0 (at balance 2).
- **Profiles undecided**, with their brackets:
  - 10051_23: White 0-1. One-ply expansion: D18, D19 and G19 undecided, D18 also at 4.8M.
  - 10408_3: Black 0-1, in the frame as well. Expansion: A17, D18 and D19 undecided, A17 also at 4.8M.
  - 11279_0: Black 0-3. Expansion: 6 of 7 first moves fail; connecting at B19 is undecided, even with White
    restricted.
  - 11282_5: Black 0-2. 11282_6 and 11282_7: Black 0-2. 13547_18: Black 0-1.
  - 13547_20: White 0-1. 13547_26 and 13661_4: both sides 0-1. 9883_2: most profiles.
- **Wide area**: the class and count are undecided for most semeai leaves. The narrow results lean on approach
  points at the area's wall: F19, A16, A15 and E19 in 13541, 13475, 11280 and 13549. The narrow numbers for those
  leaves are flagged in the records.
- **Open for the owner**:
  - Is a mutual count with the same approach point (3.1) his 10kKO, and the switch-back (3.2) his plain KO?
  - What should the one-sided leaves he wrote as 10kKO (3.3) print?
  - Is "10kKO plus one private approach" (10393_0, 13549_13) still 10kKO?

## 7. How the facts were found

- **Engine:** the frozen prototype `engine_x`, driven through `harness.py` (the checker's own SGF loading, target
  and areas). Scripts: `inv.py` (search wrapper and log), `analyze.py` (one leaf), `follow.py` (after one
  approach), `extra.py` (who needs the ko), `bounds_run.py` (sound bounds), `collect.py` (this inventory).
- **Budgets:** every counted search ran at 400k nodes; an undecided one was retried once at 2M (the engine itself
  retries each budget once with fresh move ordering, so a fully undecided search used 4.8M nodes). Exceptions,
  to stay inside the hour: the approach alternatives, the follow-ups after one approach, the no-ko tests, the
  one-ply expansions and the wide re-runs of profiles already undecided in the narrow area ran at 400k only (one
  child of each undecided expansion was retried at the full budget). Every search is listed in `inventory.json`
  with its area, options, threats, nodes and result.
- **Areas:** the checker's narrow area first. The wide area was run when the narrow count or a narrow profile was
  undecided or one of its moves touched the area's wall or fringe. Two deviations: for the first eleven leaves
  the wide count ran regardless, and for the first six the wall test missed fringe neighbours, so 10393_0's E19
  (next to the fringe point F19) was not re-run wide. c081 ran only in the judges' 14-point corner
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
  approach count. n = 0 means X's capture is a real threat: one tenuki by Y and X wins. k was scanned upward for the
  first twelve leaves and downward from 5 afterwards; outright is monotone in k, so a decided failure at k proves
  n >= k and a win proves n <= k - 1.
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

## 8. Files

- `inventory.json`: one record per leaf (31): problem, leaf moves, tree label, leaf index in tree order (pre-order,
  0-based, among the tree's leaves), every fact above, the hand-written reading (`investment`, `first_take`,
  `approach_points_same`, `switch`, `who_needs_the_ko`, `fit`, `fit_reason`, `notes`) and every search behind it
  (`searches`, `searches_follow_up`, `undecided_searches`). Frame runs are nested as `frame_run`.
- `inventory_controls.json`: the 30 approach-form siblings (narrow area only); `controls_table.json` compares them
  with the tree's labels.
- `raw/`: the per-leaf search logs (`<key>.json`, `.follow.json`, `.extra.json`, `.expand.json`, `.chain.json`).
- Scripts: `inv.py`, `analyze.py`, `follow.py`, `extra.py`, `expand.py`, `child.py`, `child_bound.py`, `bound.py`,
  `bounds_run.py`, `chain.py`, `verdicts.py`, `collect.py`, `summ.py`, `enum_leaves.py`, `show.py`.
