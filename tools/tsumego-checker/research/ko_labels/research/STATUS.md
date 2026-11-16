# Where the ko-label work stopped (16 November, usage limit)

## Fix group F: labels the solver left undecided

- Cause found (design 1): the proof table could drop a position's numbers when its slot held another position's proof,
  so a search re-expanded that one position until its budget ran out. Fixed in engine.cpp, with a wall-clock deadline
  inside every search.
- Branch `group-f-final` (pushed, not merged into tsumego-checker): design 1's fix, design 2's second-ko readings (D4),
  the first fix of those readings and the fixes from the code review (bent-four abort, deadline rounding and refusal,
  check_tree time-outs). Results, narrow area:
  - the 146-leaf regression list: no decided label lost, 30 undecided leaves decided, 13661 k049 now 10kKO;
  - the 63 research leaves: 49 equal to the research (47 before), nothing worse, all leaves within 24 s.
- Not merged because the readings still print a wrong label in the both-area mode that check_tree.py uses: 10403 gives
  A-2KO+ from the wider area (the switching test ignores D8's approach-round gains), and 11279 k109 rests on a tie
  between two forms. Findings: `fixes3/F/skeptics-round1.md`; the review: `fixes3/F/review-d4.md`.
- Branch `group-f-d4-round2-wip` (pushed): the second fix round, stopped unfinished. Next: finish it (gain-aware
  switching test, ties to the switch as in the owner's 10403 answer, undecided where the readings cannot decide), test in
  both areas, then merge group-f-final plus that fix into tsumego-checker and rebuild the engine.

## The fourth research round (leaves the group F solver newly decides against the tree)

- `round4/`: brief, cases (k107-k121), dossiers, the 15 analyses and the 8 reviews finished (`reviews.json`).
  `round4_collect.py` and `to_results.py` read them once the remaining reviews are run (k111-k121).
- Settled so far: 11272 k108 KO- (site error, both reviewers); 11279 k109 A-1KO-2 (site error; tree, analyst and
  solver all wrong, both reviewers). 5708 k107 and 11283 k110: the tree is wrong under every reading, the exact label
  turns on question 8 (open board edges).
- Analysts only, reviews pending: 11283 k110, 13489 k111-k113, 13547 k114 and k117-k119, 15794 k120: site errors where
  the solver's label is right; 13547 k115, k116: question 6 (SEKI+); 13547 k118: approach form or counted form; 15794
  k121: 10kKO, the solver's A-3KO- is wrong (free fills counted as approaches, the sente-approach weak point).
- To look at later: 11272 leaf 65 (probably KO-2), 13489 leaves 37 and 38 (probably not BENT4), 840 leaf 1 (the switching
  reading gives A-1KO-), and D12's choice printing a decided label beside an undecided first move.
