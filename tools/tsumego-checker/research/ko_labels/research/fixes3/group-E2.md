# Group E2: approaches that are not approaches (sente moves, local threats, repetition)

The checker's profile (D3) counts an approach where the research found none, on three leaves of two problems. In 12495
(k031, k032) White's "approach" D13 is really White's kill when Black ignores the capture, and Black's own D13 is a local
threat; the forced-pass search that finds the approach changes its answer with the repetition rule. In 13605 (k047)
Black's "approach" A17 is played with tempo (White must answer at A19), so it costs Black nothing, and White's three
local threats (A15, E18, F19) are not located; the wider area prints KO-3, one short only because F19 is wall there.
design.md section 11 lists the sente approach as risk 2. Find what the profile and the approach-round check get wrong
here and fix the rule, not the leaves.


Each case: a leaf whose right label the ko-label research settled (an analyst and reviewers, judges where they
disagreed), and the label the merged checker (tsumego-checker at e019960d) still gives. The tree S[] is the owner's;
"right" is the research's verdict (for a site error the tree is wrong too). The merged checker's label and parts come
from validate.py at budget 200000 in the narrow area with a 300 s limit.

## k031: problem 12495 (Kano Yoshinori Volume 2 206)

- Leaf: A10 B9 A12 A11
- Tree S[KO-]; research verdict site_error; right S[KO+]
- Merged checker: label A+1KO-3; class KO; strict A+1KO-; net None; count 0 (decided True); d_conv 2; 0.1 s, 26675 nodes
- Checker text: A+1KO-3: White took first (A11). White needs one approach (D13) before the ko is direct. After it, the direct ko is KO-2: Black needs 2 threats more than White, and 2 extra threats not located. But approach round 1 fails: Black must answer D13 instead of retaking for free (beyond +6 after the free retake), so the approach is sente (design risk 2). Label count: Black needs 2 threats more than White. Full count 0. +2 of the gain not located. Strict label A+1KO-.
- Parts: first take {"side": "W", "point": "A11", "how": "leaf", "preparation": [], "investment": null}; approaches {"S": 0, "O": 1}; free moves null; local threats []; stages []; gain {"side": "B", "total": 2, "located": 0, "unlocated": 2}; second ko null; alternative null
- Warnings: ["approach round 1 fails: Black must answer D13 instead of retaking for free (beyond +6 after the free retake; a sente approach, design risk 2)"]
- Undecided parts: []
- Analyst (site_error, says KO+): The tree's KO- misses Black's local ko threat at D13. D13 puts the lone C13 stone in atari, White must capture at E13, and Black retakes A12 without spending a threat, so White needs the first outside threat: KO+, as the earlier research settled (count 0 under every repetition rule). The checker's A+1KO-3 is wrong: White needs no approach, because if Black ignores White's capture at A11, White's D13 or B13 kills against any number of Black threats; the checker's 'one approach' comes from a forced-pass search whose answer changes with the repetition rule (if Kano's diagram has a White stone at E13, adding it would keep the tree's KO-).
- Key line from the leaf: D13 E13 A12
- Variations: From the leaf (Black to play, banned at A12):
1. D13 E13 A12: Black's local threat, White's forced capture, Black's free retake. White now needs an outside threat. With no threats: D13 E13 A12 pass B13, Black captures A13 and B12 and lives (Black wins at s=o=0).
2. D13 E13 A12 A11! B10 B13 A12 D13: White retakes with an outside threat, Black has none (a second D13 is suicide), and after B13 and D13 Black's A12 is a ko eye only White can resolve: dead (Black fails at s=0, o=1).
3. White cannot ignore D13: D13 B13 A12 (Black captures five stones); D13 A12 B13 (Black captures five); D13 pass B13 (Black takes C13 as a second ko and threatens A12 on three stones; E13 is White's only winning answer even with two threats more).
4. Black cannot play elsewhere, so White's A11 is a direct threat with no approach: pass D13 A12 B13, dead; pass D13 pass B13, dead; pass B13 A12 D13, dead; pass B13 D13 E13 A12 D13, dead (each fails with 6 Black threats against 0).
5. White has no local threat after the exchange: D13 E13 A12 then B13 D13 (Black takes four stones), D13 B13 (Black takes A13 and B12 and lives), A9 B13, B10 B13.
- Searches: 1. evidence.py 12495 "A10 B9 A12 A11" --label --tree --budget=400000: A+1KO-3, strict A+1KO-, full count 0 (-3..-1 no, 0..+3 yes), first take W A11, opponent profile n=1 (outright {1: False, 2: True}), direct-ko value 2, warning 'approach round 1 fails'.
2. Probe on the checker's Solver, wide area, 400000 per search (all decided, at most about 1,500 nodes each), one process per search. Leaf counted table under cycle rules 0, 1 and 2: Black fails at -3..-1 and wins at 0..+3. Also (s,o) = (1,1) win, (1,2) fail, (2,2) win, (2,3) fail, (3,3) win, (3,4) fail. Count 0.
3. Black barred from D13 while under a ko ban (kb=D13): count 1 (KO-, the tree's label) under all three rules, so D13 is worth exactly one threat.
4. "A10 B9 A12 A11 D13", White to play: at (0,0) every White move loses; at (0,2) only E13 wins.
5. "A10 B9 A12 A11 D13 E13 A12", White to play and banned: at (0,0) B10, A9, B13, D13 and pass all lose; counted table -1 no, 0 yes under all rules (plain KO+).
6. "A10 B9 A12 A11 D13 E13 A12 A11!", Black to play: at (0,0) B10, A9 and pass lose, and D13 is illegal (suicide).
7. Direct threat: the leaf with one forced Black pass (pb=1), o=0. Rules 0 and 2: Black fails at s=0..2 and 'wins' at s>=3. Rule 1: fails at s=0..6. Searches rooted at "... pass D13 A12 B13", "... pass D13 B10 B13", "... pass D13 A9 B13" and "... pass D13 pass B13": Black fails at s=6, o=0 under rules 0, 1 and 2. Rooted at "... pass B13 A12 D13": fails at s=0..6. Rooted at "... pass B13 D13 E13 A12 D13": fails at s=6.
8. The engine contradicts itself. The count at "A10 B9 A12 A11 pass B13 A12" (White to play) says Black wins at s=2..6, but the move table there gives White's D13 a win, and the search rooted after D13 has Black failing at every s. The pb=1 threshold is d=2 when its searches share one process and 3 when each count runs in its own process.
- Wrong part of the label (analyst): Approach count, and the direct-ko value built on it. White's profile gives outright {1: False}, so n_O=1 with D13 as the approach. But White's capture at A11 is a direct threat: after one Black tenuki, White's D13 (or B13) kills against any number of threats. Every position after White's D13, any Black reply and B13 fails for Black at s=6 under cycle rules 0, 1 and 2, and under rule 1 the profile itself is outright at k=1. Under rules 0 and 2 the parent searches disagree with their own children (path-dependent scoring of repetitions). The 'approach round 1 fails' warning is this direct threat misread as a sente approach. v_O=2 (KO-3) is the same artifact: 2 in the checker's process, 3 when each search is run alone, beyond +6 under rule 1. The local-threat attribution then ran on 'P_O with 1 forced pass' and found nothing. With n_O=0, the checker's own D6 gives a=0, e=-d(leaf)=0 (KO+), and D7 with kb=D13 locates D13 as Black's one local threat (count 1 without it). The first take (White A11) and the full count (0) are right; the strict label A+1KO- has the right count but the wrong structure.
- Reviewer 1 (position, upholds): The verdict holds: the leaf is a plain KO+ (count 0), so the tree's S[KO-] is a site error. The checker's A+1KO-3 is wrong in its approach count. I could not refute either point.

Setup: my own probe, /tmp/claude-0/proto/research/kores/work/k031-rev/probe.py. It reads the checker as a library only, starts one engine per search, uses a budget of 400000, and never escalates; nothing needed the retry. /tmp/claude-0/proto/research/kores/work/k031-rev/regions.py shows the narrow and wide areas are identical here. D13 and E13 are fringe points, playable because C13 has only two liberties; F13 and beyond count as White's wall. That changes nothing locally: on the real board too, Black's D13 has the single liberty E13.

Rule 1 scores every repetition as a kill and rule 2 every repetition as life. So I tested each Black win under rule 1 and each White kill under rule 2, the rule least favourable to the result.

What I tried, and what I found:

1. Count. At the leaf Black wins at (s,o) = (0,0), (1,1) and (3,3) even under rule 1. Black fails at (0,1), (1,2) and (2,3) even under rule 2. Black wins exactly when s >= o, which is KO+.

2. D13 is a real local threat. After D13, each White reply other than E13 loses even with White two threats ahead under rule 1: pass, B10, A9, B13 and A12 all lose. So E13 is forced, and Black retakes A12 without a threat.

3. White has no local threat after D13 E13 A12, tested at (0,0) under rule 1:
   - after a White pass, B10, A9 or D13, Black's B13 is unconditional life (a one-node search);
   - after White's B13, Black's D13 captures four stones.
   After White later retakes, a second Black D13 is suicide and B13 is illegal, so Black has only the one threat.

4. Controls:
   - Black barred from D13 while under a ko ban (kb=D13): Black fails at (0,0) and (1,1) under rule 2, and wins at (1,0) and (2,1) under rule 1. That is count 1, the tree's KO-.
   - A White stone on E13 gives the same pattern: KO-.
   So D13 is worth exactly one threat, and it is the only thing between the board as given and the tree's label.

5. White's capture at A11 is a direct threat. If Black plays elsewhere, White's D13 kills: with each search rooted alone, every Black reply fails at s=6, o=0 under rules 0 and 2. The replies are pass, A12, A9 and B10.
   - White's winning line takes A11 six times, each Black retake costing a threat, then captures at B9. White never needs a threat.
   - White's B13 kills as well: "pass B13 A12 D13", "pass B13 pass", "pass B13 D13 E13 pass" and "pass B13 D13 E13 A12 D13" all fail at s=6 under rules 0 and 2.
   - By hand: after W D13, B A12, W B13, the point A11 is a ko eye only White can take. The space at A9 and B10 with White's B9 inside gives no second eye. If Black connects at A11, White sacrifices at A9, Black takes two stones at B10, White throws in, and Black is dead.

6. The checker's "outright {1: False}" is an engine artifact.
   - The leaf with one forced pass (pb=1) at s=6 says Black wins under rules 0 and 
- Reviewer 2 (definitions, upholds): The verdict and its status hold. This is a site error, not a definition case.

DEFINITIONS. In besogo's status.js, KO+ means extraThreats 0 and a count of T=0. The tree's KO- is T=1, the checker's A+1KO-3 is T=2 and its strict A+1KO- is T=0. The leaf's full count is 0, so KO- asks Black for one threat too many. The owner's own tutorial (templates/Sites/gotutorial.php, lines 66-68 and 75-76) covers exactly this case: "when the opponent takes first, but the player has a local ko threat and the opponent doesn't, it would still be a KO+". The brief says the same: a local move the other side must answer works like a ko threat. Neither side needs an approach: Black's retake at A12 threatens B13 at once, and White's capture at A11 threatens to kill at once. So this is a direct ko, not an approach ko, a 10kKO or a mutual approach, and by the owner's approach-ko definition there is no A-form.

MY SEARCHES. Scratch files are in /tmp/claude-0/proto/research/kores/work/k031-defrev/ (probe.py and the result .txt files). Each run used one engine process and the wide area, at a budget of 400,000. Every search was decided in under 2,000 nodes, so no retry was needed. Here the wide area is the same as the narrow one, and D13 and E13 are playable edge points in both (a fringe point is playable when a neighbouring chain has at most 2 liberties).
(1) The leaf "A10 B9 A12 A11" has count 0. Under rule 0 Black wins at 0:0 and 1:1 and fails at 0:1 and 1:2; under rules 1 and 2 it wins at 0:0 and fails at 0:1.
(2) Black's move table at the leaf: at 0:0 only D13 wins (B10, A9 and pass lose); at 1:0 A12! and D13 win; at 0:1 nothing wins.
(3) With Black barred from D13 while under a ban (kb=D13), the count is 1 under rules 0, 1 and 2. D13 is worth exactly one threat.
(4) After D13, White to play: at 0:0 every White move loses (E13, B10, A9, B13, A12, pass). At 0:1 and 0:2 only E13 wins.
(5) After D13 E13 A12, White to play under the ban: count 0 under all three rules, and B10, A9, B13, D13 and pass all lose at 0:0. White has no local threat.
(6) After D13 E13 A12 A11!, Black to play: B10, A9 and pass lose at 0:0, and D13 is suicide.
(7) The checker's test for the approach, the leaf with one forced Black pass and White at 0 threats, each search in a fresh process: under rule 1 Black fails at s=2, 3, 4 and 6; under rules 0 and 2 Black fails at s=2 and "wins" at s=3, 4 and 6. This reproduces the checker's outright {1: False}.
But the child positions refute it. After "pass D13", Black has four legal moves (A12, A9, B10, pass); each one followed by White's B13 fails at s=6 under rules 0, 1 and 2. After "pass B13", each Black reply (A12, A9, B10, pass, and D13 E13 followed by any of these) followed by White's D13 fails at s=6 under rules 0 and 2. Rule 2 scores every repetition for Black, so these White wins cannot come from how repetitions are scored. The parents "pass D13", "pass B13" and "pass B13 D13 E13" still say Black wins under rules 0 and 2. At "pass B13 A12" the engine co

## k032: problem 12495 (Kano Yoshinori Volume 2 206)

- Leaf: A12 A11 A10 B9 A12
- Tree S[KO+]; research verdict site_error; right S[KO+2]
- Merged checker: label A+1KO-2; class KO; strict A+1KO+; net None; count -1 (decided True); d_conv 1; 0.0 s, 6559 nodes
- Checker text: A+1KO-2: Black took first (A12). White needs one approach (D13) before the ko is direct. After it, the direct ko is KO-2: Black needs 2 threats more than White, and 2 extra threats not located. But approach round 1 fails: Black must answer D13 instead of retaking for free (beyond +6 after the free retake), so the approach is sente (design risk 2). Label count: Black needs 1 threat more than White. Full count -1. +2 of the gain not located. Strict label A+1KO+.
- Parts: first take {"side": "B", "point": "A12", "how": "leaf", "preparation": [], "investment": null}; approaches {"S": 0, "O": 1}; free moves null; local threats []; stages []; gain {"side": "B", "total": 2, "located": 0, "unlocated": 2}; second ko null; alternative null
- Warnings: ["approach round 1 fails: Black must answer D13 instead of retaking for free (beyond +6 after the free retake; a sente approach, design risk 2)"]
- Undecided parts: []
- Analyst (site_error, says KO+2): Black took first and also holds the local threat at D13, so White must win the ko twice. Black wins with one threat fewer than White, which is KO+2, as the earlier research settled; the tree's KO+ ignores D13 (barring it gives exactly KO+). The checker's A+1KO-2 is wrong both in count (it implies 1, its own full count is -1) and in structure (White's retake at A11 is a direct threat with no approach). A12 first (KO+2) still ranks above A10 first (KO+), so the problem's answer does not change.
- Key line from the leaf: A11! D13 E13 A12
- Variations: From the leaf (White to play, banned at A11):
1. A11! D13 E13 A12: White's outside threat and retake, Black's local threat, White's forced capture, Black's free retake. White needs a second outside threat. With s=0, o=1: A11! D13 E13 A12 pass B13, Black captures A13 and B12 and lives.
2. A11! D13 E13 A12 A11! B10 B13 A12 D13: with s=0, o=2 White retakes again, Black has no threat (D13 is now suicide), and White's B13 and D13 leave Black's A12 a ko eye only White can resolve: dead.
3. White has no local move: B13 D13 (Black captures four stones); D13 B13 (Black captures A13 and B12 and lives); A9 B13; B10 B13; pass B13.
4. After A11!, a Black tenuki loses at once, as in k031: A11! pass D13 A12 B13, dead; A11! pass B13 A12 D13, dead.
- Searches: 1. evidence.py 12495 "A12 A11 A10 B9 A12" --label --ko --moves --budget=400000: A+1KO-2, strict A+1KO+, full count -1, ko sign KO+2 in both areas (-3 and -2 no, -1..+3 yes), first take B A12, opponent profile n=1 (outright {1: False, 2: True}), direct-ko value 2. One-move table: A9, B13, B10 and pass all ALIVE.
2. Probe on the checker's Solver, wide area, 400000 per search, one process per search. Leaf counted table under cycle rules 0, 1 and 2: Black fails at -3 and -2 and wins at -1..+3. (s,o) = (1,2), (2,3) and (3,4) win; (1,3), (2,4) and (3,5) fail. Count -1.
3. Black barred from D13 while under a ko ban (kb=D13): count 0 (KO+, the tree's label) under all rules.
4. Leaf move table for White at (0,0): B10, A9, B13, D13 and pass all lose. "... A12 D13": Black's B13 wins. "... A12 B13": Black's D13 wins.
5. "A12 A11 A10 B9 A12 A11!" with pb=1 (the leaf 2 position after one forced Black pass), o=0. Rules 0 and 2: Black fails at s=0..2 and 'wins' from s=3. Rule 1: fails at s=0..6. Searches rooted after White's D13 and B13 fail for Black at s=6 under all rules, as in k031.
6. k031's checks of D13, E13 and the position after D13 E13 A12 apply after A11!.
- Wrong part of the label (analyst): Approach count and direct-ko value, as in k031. P_O (the leaf plus White's retake A11!) is k031's position. Its outright test at k=1 is a path-dependent repetition artifact (not outright under rules 0 and 2 with 4 Black threats; outright under rule 1; and every position after White's D13 and B13 is dead for Black at s=6 under all rules), so n_O=1 is wrong. v_O=2 then gives KO-2 and a label count of 1, which contradicts the checker's own full count of -1 (the 'unlocated +2 gain' is this error, not a gain). With n_S=n_O=0, D6 gives a=0, e=-d(leaf)=1 (KO+2), and D7 with kb=D13 locates D13 as Black's one local threat (count 0 without it). The first take (Black A12) and the full count (-1) are right; the strict label A+1KO+ has the right count but the wrong structure.
- Reviewer 1 (position, upholds): I could not refute the verdict. The tree's S[KO+] is wrong, KO+2 is right, and the checker's A+1KO-2 is wrong because of a path-dependent repetition result. My scratch files are in /tmp/claude-0/proto/research/kores/work/k032-rev/: probe.py and raw.py, with all output in the out_*.txt files.

**Setup.** I used one engine process at a time, 400000 nodes per search (none needed the 2000000 retry). The wide area equals the narrow one here. D13 and E13 are real first-line points in the dynamic fringe.

**Reading the position.** At the leaf Black has just taken at A12, and A13 and B12 are in atari at B13. C13 has only two liberties, B13 and D13, so Black's D13 is a local threat White must answer at E13:
- White B13 instead is self-atari, and Black's A12 takes five stones.
- White A12 instead is also self-atari.
- If White ignores it, Black's B13 starts a second ko in which White pays a threat for every retake.

The bottom (W B9 inside, Black A10) gives Black at most one eye, so the top ko decides everything.

**Counted searches at the leaf.** Under cycle rules 0, 1 and 2:
- (s,o) = (0,3) and (0,2): Black fails. (0,1), (0,0), (1,0) and (2,0): Black wins.
- (1,2), (2,3) and (3,4) win; (1,3), (2,4) and (3,5) fail.

So Black wins whenever s >= o-1: count -1, which is KO+2.

**Move tables along the key line.**
- Leaf: every White local move (A9, B10, B13, D13, pass) loses even with two White threats. Only A11! wins, and only at o=2.
- After A11!: D13 is Black's only winning move.
- After A11! D13: E13 is White's only answer that wins, even with two threats. A9, B10, B13, A12 and pass all lose.
- After A11! D13 E13: A12 (the free retake) is Black's only winning move.
- After A11! D13 E13 A12: every White local move loses.
- After White's second A11!: Black has no local move left. B10 and A9 lose, D13 and B13 are suicide, and only a retake with a threat wins.

**Locating the extra threat.** Barring Black from D13 while under a ban (kb=D13) gives KO+ under all three rules: (0,0) wins, (0,1) fails. A White stone added on E13 gives the same. So D13 is worth exactly one threat, and the tree's KO+ simply ignores it.

**Why the checker says A+1.**
- Black's capture is a direct threat: at the leaf with one forced White pass, Black wins even against 4 or 6 White threats.
- White's capture (the leaf plus A11!) with one forced Black pass, 0 to 6 Black threats against 0:
  - Rule 1: Black fails at every count.
  - Rules 0 and 2: Black "wins" from s=3 in a fresh process, but from s=2 when run after other searches. The boundary moves with search history.
- That "win" breaks minimax:
  - Rooted at "A11! pass D13", rules 0 and 2 say Black wins (39 repetitions adjudicated).
  - Yet every Black reply rooted on its own is a White kill under all three rules at s=4: A12, A9, B10, and pass (also with the last-move-was-a-pass flag set). This holds at s=6 too where I ran it (A12 B13 at s=6 under rules 0 and 2; pass at s=6 under rules 0 and 2, with the flag set).
  - The same happe
- Reviewer 2 (definitions, upholds): The verdict holds: the tree's S[KO+] on leaf "A12 A11 A10 B9 A12" is a site error and the right status is KO+2. It does not turn on any open question. All my searches used the checker's Solver read-only, one process at a time, at 400k nodes, and every one was decided. Scratch files are in /tmp/claude-0/proto/research/kores/work/k032-defrev/: probe.py, probe2.py (a copy whose move table also takes a cycle rule) and one .txt output file per check.

FACTS (my own searches, wide area unless noted)
1. The checker output reproduces (evidence_leaf.txt): A+1KO-2, strict label A+1KO+, full count -1, sign KO+2 in both areas. White's approach count is 1 (outright test fails at k=1, passes at k=2) and the direct-ko value is 2.
2. Leaf count is -1 (leaf_wide.txt, leaf_narrow.txt). Black wins at (s,o) = (0,1), (1,2), (2,3), (3,4) and fails at (0,2), (1,3), (2,4), (3,5). (0,1) wins and (0,2) fails under all four cycle rules (0-3), and the narrow area gives the same.
3. White has no local move at the leaf. At (0,0), White's B10, A9, B13, D13 and pass all lose; at (0,1) even A11! loses. White needs two threats.
4. D13 is Black's one local threat (leaf_barD13*.txt). Barring D13 while Black is under a ban gives count 0, which is plain KO+, under rules 0-3.
5. Line A11! D13 (after_D13.txt, after_exchange.txt, second_retake.txt):
   - At (0,1), E13 is White's only saving answer to D13.
   - After "A11! D13 E13 A12" the ko is plain KO+: Black wins at (0,0) and (1,1), fails at (0,1) and (1,2).
   - After White's second retake, Black's only local moves (B10, A9, pass) all lose at (0,0); D13 is now suicide.
6. White's retake A11 is a direct threat, so White's approach count is 0, not the checker's 1.
   - Outright test, one forced Black pass after A11!, Black s = 2..6 against 0 (PO_pb1.txt): White wins at every s under rules 1 and 3. Under rule 0 Black "wins" from s=3; under rule 2 from s=2.
   - Split one move at a time:
     - After "A11! pass D13", Black's A12, B10 and A9 lose under all four rules (after_pass_D13.txt).
     - Only Black's pass "wins", and only under rules 0 and 2. Rooted one move later, at "A11! pass D13 pass" with White to move, White's B13, B10 and A9 all win under all four rules. After "...pass B13" Black fails under all four rules (after_pass_D13_pass*.txt).
   - Rule 2 scores every repetition for Black, so these White wins rest on no repetition. The k=1 failure is a path or table artifact of repetition scoring; its threshold even moved with query order (the analyst's run had rule 2 at s=3).
   - The B13 branch behaves the same way (after_pass_B13*.txt). Its move tables show Black "wins", but each rooted follow-up is White's: "...pass B13 A12": White's D13 wins. "...pass B13 D13 E13 A12 D13": Black fails under rules 0-3. "...pass B13 D13 E13 pass D13": Black fails under rules 0 and 2.
   - The checker's own warning line agrees: "A11! pass D13 A12 B13" beats 5 to 6 extra Black threats. So D13 is the follow-up of a direct threat, not an approach. Th

## k047: problem 13605 (Xuanxuan Qijing 314)

- Leaf: C17 C16 A16 B17 B19 C19 A18 A19
- Tree S[KO-]; research verdict site_error; right S[KO-4]
- Merged checker: label A-1KO+2; class KO; strict A-1KO-2; net None; count 3 (decided True); d_conv 0; 0.1 s, 12076 nodes
- Checker text: A-1KO+2: White took first (A19). Black needs one approach (A17) before the ko is direct. After it, the direct ko is KO+2: Black wins even when White has 1 threat more, and 2 extra threats not located. Label count: Black wins with as many threats as White. Full count 3. +3 of the gain not located. Strict label A-1KO-2.
- Parts: first take {"side": "W", "point": "A19", "how": "leaf", "preparation": [], "investment": null}; approaches {"S": 1, "O": 0}; free moves null; local threats []; stages []; gain {"side": "W", "total": 3, "located": 0, "unlocated": 3}; second ko null; alternative null
- Warnings: []
- Undecided parts: []
- Analyst (site_error, says KO-4): The tree's S[KO-] only records that White took first. White also has three local threats that Black must answer: A15, then E18 and F19 against D19-D18. So Black needs four threats more than White, S[KO-4], as the earlier research settled. The checker's A-1KO+2 is wrong because no approach is needed: after Black retakes at B19, if White plays elsewhere, A17 forces White's A19, Black connects at A15, and White can never connect at B19, so White is dead. The narrow-area search that found an approach contradicts itself. The checker's own wider area prints KO-3, one short only because F19 is wall there. No move's grade changes: B19 at move 5 stays worse than A18's DEAD.
- Key line from the leaf: B19! A15 A14 A19 B19! E18 E19 A19 B19! F19 E18 A19 B19! pass A17 A19 A15
- Variations: From the leaf (Black to play, White has just taken at A19, A18 is in atari):
1. Black's moves without the retake lose. A17 A15: White captures three stones, ALIVE. A15 A17, or pass A17 (or pass B19): ALIVE. Black must retake with an outside threat.
2. B19! A15 A14 A19: White's first local threat. A17 instead of A14 lets White's A19 capture four stones.
3. B19! E18 E19 A19: the second. If Black ignores it, White's E19 captures D19-D18 and the group gets space.
4. B19! F19 E18 A19 (G19 also answers): the third. If Black ignores it, White's E18 captures D19-D18-E19. None of the three can be repeated (A15 and E18 become suicide).
5. B19! E19 E18 A19: E19 instead of E18 gives White only one threat on that side.
6. B19! pass A17 A19 A15: DEAD whatever White's threats. White's A19 is forced (its group is in atari at C17), and White can never connect at B19 (self-atari). This is the checker's 'approach line', but it is the kill itself, so Black's retake threatens at once.
7. With s=3, o=0: B19! A15 A14 A19 B19! E18 E19 A19 B19! F19 E18 A19, and Black has no threat left: pass A17, ALIVE.
8. With s=4, o=0: the key line, DEAD.
9. With s=4, o=1: White retakes once more with its outside threat and lives.
10. After all three local threats and Black's retake, the ko is a plain KO+: White needs one outside threat more than Black.
On the checker's closed edge (F19 counted as Black's wall) only two local threats exist and the count is 3 (KO-3). The board as given is open at F19, so KO-4. The tree's KO- is wrong under either reading.
- Searches: All runs use the kovalid checker read-only, with PROBLEMS=status_problems.jsonl, one engine at a time, 400000 nodes per search and one retry at 2000000. L is the leaf: C17 C16 A16 B17 B19 C19 A18 A19. In the s:o tables below, s is Black's outside threats and o is White's.

(1) evidence.py 13605 "L" --label at budgets 400000 and 200000. Result: KO-3 from the wider area.
- Approaches 0 for both sides; both are outright with one forced pass.
- White's local threats: A15, and E18/E19.
- Full count 3: s-o=2 fails, 3 wins.
- Warnings: 'the narrow area gives A-1KO+2', and a wall warning at E18/E19.
- Its text says A15 is 'answered at C17'. That reply is taken from the losing side's move in a search line; the real answer is A14, as its own line shows.

(2) The narrow area alone (area_label) gives the dossier's A-1KO+2. Black's profile is not outright with one forced pass and outright with two, v=-1, full count 3, label count 0, and a gain of +3 that is not located.

(3) Narrow-area counted searches, which contradict each other:
- L B19! pass A17 A19 (Black to move), s=0 o=2: Black fails in 242 nodes, with 18 repetitions adjudicated. The one-ply table says A15 'loses'.
- L B19! pass A17 A19 A15 (White to move), s=0 o=2 and o=5: Black wins (82 and 217 nodes). Every White move there loses (B19, C17, E18, E19, pass).
- Under cycle rule 1, the first search flips to a Black win (79 nodes).
- L B19! with White forced to pass once, o=2 and o=5: Black fails, with 20 and 34 repetitions adjudicated. Under cycle rule 1 Black wins (93 nodes).
So the narrow area's 'not outright' and v=-1 rest on repetitions scored as seki that do not hold for the position after A15. Black's approach count there is an engine artifact.

(4) Wider area:
- L B19! with one forced White pass: Black wins at s=0 o=4 (1866 nodes) and o=6 (2286 nodes).
- L B19! pass A17 A19 A15 at o=6: every White move loses.
- Leaf: 2:0 fails, 3:0 wins, 3:1 fails, 4:1 wins.

(5) Class modes (solve A/D) in the wider area and in ext1, which is the wider area plus E18 E19 F19 G19 G18 opened:
- L B19! pass A17: A=K, D=K, 0 cycles.
- L B19! pass A17 A19 A15: A=K, D=K, 0 cycles. White is dead even when it may ignore the ko ban.
- Leaf: A=N, D=K (KO).

(6) Counted searches in ext1:
- Leaf: 2:0 fails, 3:0 fails, 4:0 wins, 4:1 fails, 4:2 fails, 5:2 fails. 5:1 and 6:2 are undecided at 2M and not used.
- L B19! A15 A14 A19: 2:0 fails, 3:0 wins, 3:1 fails.
- L B19! A15 A14 A19 B19! E18 E19 A19: 1:0 fails, 2:0 wins, 2:1 fails.
- Aft
- Reviewer 1 (position, upholds): The verdict holds: the tree's KO- is wrong, the checker's A-1KO+2 is wrong, and the right status is KO-4 (Black wins when s >= o+4). I could not refute any part of it.

READING THE POSITION (leaf: W A19 has just taken, Black to move, banned from B19)
- Black has no local threat. A17 lets W A15 take three stones. A15, pass, E18 or E19 let W A17 take A18, and White lives with eyes at A18, B19 and C17.
- After B19!, White has exactly three forcing moves:
  - A15. Only A14 answers it; A17 lets W A19 take four stones.
  - E18. Only E19 answers it (it is the only capture). Ignoring it lets W E19 take D18-D19 and the group gets eye space.
  - Then F19. E18 or G19 answers it. Ignoring it lets W E18 take three stones.
- None of the three can be played again: A15, E18 and F19 become suicide.
- Other orders give White less. E19 first gives one threat on the top. F19 first gives one too: Black must answer at E18, because G19 lets White add E18.
- A14, G18, G19, F17, H18 and H19 force nothing. A17 and C17 are self-ataris. None of Black's answers threatens White back.
- No approach is needed. After B19! and a White tenuki, A17 puts White in atari, White's A19 is forced, and Black connects at A15. White has one eye at C17, and B19 can never be a second eye: A19 can only connect at B19, which is a self-atari. So White is dead whatever its threats.
- So Black needs four threats more than White: KO-4 on the board as given. If F19 is treated as wall (design question 8, open board edges) the count is KO-3. KO- is wrong under either reading, and no move's grade changes.

MY SEARCHES
All in /tmp/claude-0/proto/research/kores/work/k047-posread-c8255/ (script rd.py). One engine at a time, 400000 nodes with one retry at 2000000.
- evidence.py --label at the leaf reproduces KO-3 in the wider area, A-1KO+2 in the narrow one, and the wall warning at E18/E19.
- Leaf counts:
  - ext1 (wider area plus E18 E19 F19 G19 G18): 2:0 fails, 3:0 fails, 4:0 wins, 4:1 fails.
  - "mini" (wider area plus only E18 E19 F19): 3:0 fails, 4:0 wins, 4:1 fails.
- One-ply tables in ext1:
  - At the leaf with 4:0, only B19! wins.
  - After B19! with 3:0, every White move loses, pass included.
  - After B19! with 2:0, only E18 and A15 win for White.
  - Forced answers: after A15 only A14 wins; after E18 only E19; after F19 (played after E18-E19) E18 or G19.
  - After B19! F19 (F19 first) with 2:0, E18 and A17 win and G19 loses.
- "top" area (ext1 plus H19 H18 F17 G17):
  - After B19! A15 A14 A19: 2:0 fails, 3:0 wins, 3:1 fails.
  - After B19! A15 A14 A19 B19! E18 E19 A19: 1:0 fails, 2:0 wins, 2:1 fails.
  - After all three threats and Black's retake: 0:0 wins, 0:1 fails, 1:1 wins, 1:2 fails, a plain KO+. The same with G19 instead of E18 answering F19.
  - After B19! with 3:0: every White move other than E18 and A15 is decided as losing (F17, G17, H18, H19, G18, G19, F19, E19, A14, A13, pass). The A15 move loses too: "B19! A15 A14" is a Black win at 3:0.
- "bot" area (ext1 plus B13 C13 D13 A12 B12),
- Reviewer 2 (definitions, upholds): The verdict holds: site_error, S[KO-] should be S[KO-4]. The earlier research (c069) was right, and the checker's A-1KO+2 is a narrow-area artifact.

How the definitions give KO-4
- First take: White's A19 at the leaf is a ko capture, so the base is KO- (e0 = -1).
- No approaches (a = 0). Both captures threaten at once:
  - If Black ignores White's capture, White A17 lives.
  - If White ignores Black's retake at B19, A17 kills. White's A19 is then forced and Black connects at A15. White can never connect at B19 (self-atari), so it is dead whatever its threats.
  - This is not the owner's approach ko ("one side needs approaches, the other wins by winning one ko"). A17 is the kill, not an approach. So no approach form with the same count (A-1KO-3) is a candidate, and the open approach-label decision and Q1, Q3, Q4, Q5 and Q9 do not arise.
- Local threats. The brief says a local move the other side must answer works like a ko threat, and the site's tutorial says local threats are "usually taken into this equation". The owner's own S[KO-2] in 11320 counts a free White exchange. White has three, Black has none, so e = -1 - 3 = -4.
- In besogo that is approaches 0, extraThreats -4, written KO-4, with T = 4: Black wins when s >= o + 4. The tree's KO- has T = 1.

Open questions: the only one that touches the count is Q8 (open board edges), and only through F19.
- Under any reading the count is at least 3, so S[KO-] is wrong whatever the owner answers. Even the checker's narrowest area counts 3.
- besogo shows the board up to 3 lines past the outermost stone: columns A-J, rows 11-19. F19 is even inside the stones' own frame (Black has a stone at F18).
- So on the board as the player sees it, which is Q8's proposed answer, F19 and G19 are open and the label is KO-4.
- KO-3 comes only from the checker's wider area ending at F19, which counts F19 as Black's wall. The checker flags that wall itself.
- Only if the owner ruled the top edge beyond E19 closed would it become KO-3, and that is still a site error. The verdict does not turn on an open question.

My own searches. All are read-only and one engine at a time, at 400k nodes with one retry at 2M. L is the leaf.
- Narrow area, the checker's own area_label: A-1KO+2, strict label A-1KO-2. Black is not outright with one forced pass but is with two; v = -1, full count 3, label count 0, and +3 of the gain is not located. This reproduces the dossier.
- The narrow area contradicts itself:
  - L B19! pass A17 A19 (Black to move), s=0 o=2: Black fails (242 nodes), and the one-ply table says A15 loses.
  - L B19! pass A17 A19 A15 (White to move): Black wins at o=2 (82 nodes) and o=5 (217 nodes).
  - Under cycle rule 1 the first search becomes a Black win (79 nodes).
  - L B19! with one forced White pass, o=2 and o=5: Black fails under cycle rule 0 (373 and 652 nodes) and wins under rule 1 (93 nodes).
  - So the narrow area's approach comes from how the engine scores repetitions, not from the position.
- Wider area, 

