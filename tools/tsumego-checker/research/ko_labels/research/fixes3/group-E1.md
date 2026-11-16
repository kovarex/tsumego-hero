# Group E1: the leaf convention ko_taken_by turns a failure into KO+

tsumego.py's ko_taken_by (and its callers in compute_label, check_tree.py and status_eval.py) prints KO+ for a leaf
whose last move is the solver's ko capture when the class is a clean DEAD or ALIVE. Its comment states its purpose: a
line that ends with the solver's ko capture counts as taking first "even when the solver could steer back to clean life
or death later; the direct move is the clean one". The owner's trees follow it where the clean result is the solver's
SUCCESS: 8 leaves (5160, 5628, 8094, 13075 twice, 15399 three times) carry S[KO+] there, and the checker agrees.

The fault: the convention also fires when the clean result is the solver's FAILURE (the solver loses even with
unlimited ko threats), where KO+ ("the solver took first and wins with equal threats") cannot be right. k048 below
(13661 leaf 9, KILL, clean result ALIVE) is the confirmed case; 13462 "E19 C19 A19 A18 A17 G18 A19" (LIVE, clean result
DEAD, tree S[KO+]) is the other leaf the census finds; nobody has checked it yet, so judge it yourself.

Not in scope: the success case k029 (11332 leaf 3, LIVE, clean ALIVE). The research says ALIVE there, but the owner's
own trees use KO+ for that situation in 8 leaves, so it goes to him as a question; the checker keeps the convention for
a success and must say so in the label's text.


Each case: a leaf whose right label the ko-label research settled (an analyst and reviewers, judges where they
disagreed), and the label the merged checker (tsumego-checker at e019960d) still gives. The tree S[] is the owner's;
"right" is the research's verdict (for a site error the tree is wrong too). The merged checker's label and parts come
from validate.py at budget 200000 in the narrow area with a 300 s limit.

## k048: problem 13661 (Life & Death - Advanced 340)

- Leaf: B18 B17 C18 A18 E18 D18 F18 D19 B19 A16 A19 C17 A17
- Tree S[A-3KO+]; research verdict site_error; right S[ALIVE]
- Merged checker: label KO+; class ALIVE; strict None; net None; count None (decided None); d_conv None; 0.0 s, 3202 nodes
- Checker text: KO+: the line ends with the solver's capture of a ko, which the leaves count as taking first; without it the result is clean ALIVE.
- Parts: first take {"side": null, "point": null, "how": null, "preparation": null, "investment": null}; approaches {}; free moves null; local threats null; stages null; gain null; second ko null; alternative null
- Warnings: ["the solver's clean result: ALIVE"]
- Undecided parts: []
- Analyst (site_error, says ALIVE): Leaf 9 is not a ko. After Black's capture at A17, White simply plays C19: Black's five corner stones (A19 B19 B18 C18 and A17) are left with their only liberty at A18, where Black cannot play, so White captures them and lives; Black fails even with unlimited ko threats. The tree's S[A-3KO+] should be S[ALIVE], which is also the checker's own class; the checker's printed KO+ comes only from its leaf convention for a line ending with the solver's ko capture, which should not apply when the clean result is a failure for the solver. The order of Black's move-5 choices (F18, then A16, then E18) does not change.
- Key line from the leaf: C19 G18 A18 pass B18
- Variations: 1. C19 G18 A18 pass B18 pass pass: White C19 puts the five Black corner stones in atari at A18; Black A18 would be suicide (White's group still has A15, D16, E19); White captures five at A18 and makes eyes: ALIVE (the solver's PV).
2. Black's other tries after C19: D16, E19 or A15 only fill one of White's three liberties, and White captures five at A18 next.
3. pass (White plays elsewhere at once) still keeps ALIVE: Black would need three moves (A15, D16, E19) before C19 captures White, while White needs only C19; e.g. pass D16 C19.
4. The tree's reading (Black fills A15, D16, E19 while White waits, then C19 captures) needs White to ignore three moves and never play C19; White's ko retake A18 is never needed.
- Searches: evidence.py 13661 "B18 B17 C18 A18 E18 D18 F18 D19 B19 A16 A19 C17 A17" --label --pv (budget 400000): narrow and wide areas both mode A = N and mode D = N (Black does not kill even when it may ignore the ko ban); clean result ALIVE, 37,096 nodes; label printed KO+ only by ko_taken_by (tsumego.py: a leaf whose last move is the solver's ko capture becomes KO for DEAD or ALIVE alike) with the warning 'the solver's clean result: ALIVE'. PV: W C19 B G18 W A18 B pass W B18 B pass W pass. --ko: counted tables, Black fails at every balance s-o = -3..+3 in the narrow and the wide area; wide lines at s=0,o=0 and s=1,o=0 both W C19. --moves: ALIVE after White's A15, C19, E19, F19, G18 and pass; D16 undecided. Files: /tmp/claude-0/proto/research/kores/work/13661/k048_label.txt, k048_moves.txt.
- Reviewer 1 (position, upholds): VERDICT: the analyst's site_error / ALIVE survives. Two explanatory points need correcting (5 and 6 below); neither changes the status.

1. READING THE LEAF (White to play, ban at A18).
- Black's corner A19 B19 B18 C18 has two liberties, A18 and C19. A17 has one, A18. White's chain has four: A15, C19, D16, E19.
- After White C19, the corner and A17 are left only with A18. A18's three neighbours (A17, A19, B18) are all Black, so Black's A18 can never capture anything: it is suicide whatever White's liberty count. The analyst's stated reason ("White still has A15 D16 E19") is not the real one. Checked with the board code: Black's A18 stays illegal even after Black fills two of A15, D16, E19.
- Black needs all three fills to capture White; White needs one move. White's A18 then takes five stones; it is not a ko. White's eyes at A17, A19-B19 and C18 make life.
- No local threat helps Black, since White's C19 and A18 need no ko retake.

2. MY SEARCHES (fresh engine, 400000 nodes, one process at a time; files in /tmp/claude-0/proto/research/kores/work/k048-refute-c8255/).
- evidence.py --label --pv reproduces the analyst exactly: modes A=N and D=N in both areas, clean ALIVE, label KO+ only through ko_taken_by, 37,096 nodes.
- Solver.status at the leaf: ALIVE, best move C19, in both areas.
- At the leaf with s=6, o=0: Black fails in the narrow area (989 nodes) and the wide area (13,776 nodes).
- After C19: modes N/N in the narrow area (592 nodes) and the wide area (6,200). With s=6, every Black reply fails (D16, E19, A15, F19, G18, pass).
- White moves at the leaf with s=6, o=0, narrow area: C19, E19, A15, F19, G18 and pass all keep the win; only D16, a self-fill, fails.

3. THE PASS LINE.
- Narrow area: modes N/N, and Black fails at s=6.
- Wide area: the whole search stayed undecided at 2,000,000 nodes, so I do not use it.
- Split instead: all 16 Black replies fail at s=6 in the wide area, and pass D16 C19, pass E19 C19 and pass A15 C19 are each N/N.
- So White can even ignore one Black move.

4. THE CHECKER PART. The printed KO+ comes from ko_taken_by (tsumego.py about line 2860, used at line 1508). It turns a clean DEAD or ALIVE into KO+ whenever the leaf ends with the solver's ko capture. Its stated reason ("the solver could later steer to clean life or death") covers only the solver's success. For this clean failure it should print ALIVE. No open owner question applies: the leaf is not a ko at all.

5. WHAT THE TREE'S A-3KO+ IS (corrects the analyst's variation 4).
- When White retakes at A18 it captures A17, and A17 becomes a liberty of White's chain (it touches A16 and B17).
- So after Black's three fills, White's retake leaves White with A17 and C19, Black's corner with C19 only, and Black's C19 is illegal (checked).
- Narrow-area profile with White forced to pass:
  - k=1, 2, 3: Black fails at s=0, o=4 (2,243 / 6,468 / 9,236 nodes).
  - k=4: Black kills (D16 E19 A15 C19), so n = 3.
  - After three fills: Black fails at s=0, o=0 and wins at s=1,
- Reviewer 2 (definitions, upholds): VERDICT HOLDS: site_error, S[A-3KO+] -> S[ALIVE]. Own searches, one engine process at a time, budget 400000, one retry at 2000000 (the engine's solvex restarts an undecided search once at the same budget, so 'undecided at 2,000,000' means 4,000,002 nodes).

FACTS REPRODUCED
1. Leaf (evidence.py --label --pv): class ALIVE. Narrow and wider area both give mode A = N and mode D = N, so Black does not kill even when it may ignore the ko ban (37,096 nodes). KO+ is printed only by ko_taken_by, with the warning "the solver's clean result: ALIVE". PV: W C19 B G18 W A18 B pass W B18 B pass W pass.
2. After W C19, Black to play: ALIVE in both areas (A = N, D = N). One-move table: ALIVE after every legal Black move (A15, D16, E19, F19, G18, pass). A18 is not legal: it is suicide, because White's chain keeps A15, D16 and E19.
3. --ko at the leaf: Black fails at every s-o from -3 to +3 in both areas; the wide lines at s=0,o=0 and s=1,o=0 both start W C19.
4. Probe, no forced passes, s=6 o=0: Black fails in both areas (820 and 13,658 nodes), line W C19.
So A-3KO+ (T=3) and the checker's KO+ (T=0) are both refuted. Black fails at any balance, so no KO label fits. ALIVE, not SEKI: after C19, A18 and B18 White has eyes at A17, A19-B19 and C18.

WHERE THE TREE'S A-3KO+ COMES FROM (my probe.py, narrow area)
- Black's forced-pass profile (D3), 0 threats against 4: with White forced to pass 1, 2 or 3 times Black fails; with 4 passes Black wins (A15 D16 E19 C19). So t=4 and n=3.
- With 3 forced passes Black fails at s=0 and wins at s=1, so v=1 and e=1-v=0. D6 then gives exactly A-3KO+.
- White retakes at once (A18!) with Black forced to pass once: White wins outright, so n_O=0.
- The tree's label is therefore the reading in which White's C19 counts as a forced tenuki.
- Wider area: k=4 and v at s=1 are decided; k=1..3 and v at s=0 stay undecided at 2,000,000. So this reconstruction is decided only in the narrow area. The verdict does not rest on it.

AGAINST THE DEFINITIONS
- besogo: a KO label says the solver wins with T=-(a+e) more threats; A-3KO+ says Black kills with 3 more. Black never kills, so in a KILL problem the only fitting status is ALIVE (ordinal 11, the worst for Black).
- Owner's approach ko: "the other side can win by winning one ko at any time". Here White wins with no ko at all (C19, then A18 takes five stones, not one), at any time. Black's first approach never gets played.
- The only conceivable definitional route is the answered approach-round rule (c121): what the opponent gains with its free move belongs to the approach. The tree's reading stretches that rule to C19, White's free move right after Black's non-threatening A17. It does not reach this leaf:
  - The rule moves a gain in threats out of the label. It presupposes a ko that survives the free move; here the free move ends the fight.
  - The design labels only after the class step.
  - In c121 itself, an outright-saving free move (B15 for the whole group) made that target ALIVE rather than


## Context, not a target: k029


- Leaf: D17 E19 D19 C19 B19 B18 C18 A19 D19
- Tree S[ADKO]; research verdict site_error; right S[ALIVE]
- Merged checker: label KO+; class ALIVE; strict None; net None; count None (decided None); d_conv None; 0.1 s, 13371 nodes
- Checker text: KO+: the line ends with the solver's capture of a ko, which the leaves count as taking first; without it the result is clean ALIVE.
- Parts: first take {"side": null, "point": null, "how": null, "preparation": null, "investment": null}; approaches {}; free moves null; local threats null; stages null; gain null; second ko null; alternative null
- Warnings: ["the solver's clean result: ALIVE"]
- Undecided parts: []
- Analyst (site_error, says ALIVE): Leaf 3 is not a double ko: after Black's D19 capture, White cannot kill even by retaking at D18 at once. Black simply connects at C17 and then takes White's corner stones with A18, B19 and D19, using no ko capture of its own, so the tree's S[ADKO] should be S[ALIVE]. The checker's KO+ is also wrong: it comes only from its rule that a line ending with the solver's ko capture counts as a ko, while its own clean class, ALIVE, is right. Changing this leaf to ALIVE also lifts the tree's main line to ALIVE through White's A19 at move 8, but White's better 8th move is to retake the C17 ko after a threat (a double ko, ADKO in the narrow area), so that move should go into the tree as well; otherwise Black's A18 at move 7 (leaf 1's line) would wrongly grade as a mistake.
- Key line from the leaf: D18! C17 B19 A18 A16 D19
- Variations: From the leaf, White to play (D18 banned for White; '!' = a retake after a threat):
1. D18! C17 B19 A18 A16 D19 pass C19: White retakes at once; Black connects at C17 instead of fighting the ko; after B19 and A18, White's four corner stones and D18 have one liberty, D19; Black D19 captures five: ALIVE (wider area, no ko capture by Black).
2. D18! C17 D19 A18 F19 B19: White connects at D19 instead; A18 puts A19 and B18 in atari; B19 takes both, giving two eyes at A19 and B18: ALIVE (static life).
3. D18! C17 D19 A18 B19 F19: White's B19 joins seven stones with one liberty; F19 captures them: ALIVE.
4. D18! C17 A18 B19: White's A18 is self-atari; B19 captures three: ALIVE.
5. A16 C17 D18 A18 B19 D19: the solver's best try for White (A16 first, then the capture at D18); D19 again captures five: ALIVE.
6. pass A18 D18 B19 pass D19: White plays elsewhere; A18, then B19 and D19 take the corner: ALIVE.
7. F19 A18 D18 B19: ALIVE.
8. B19 A18 / A18 B19: White's self-ataris; Black captures four: ALIVE.
Context, from the start: D17 E19 D19 C19 B19 B18 C18 C17! (White's better 8th move, a retake after a threat, instead of the tree's A19): with ko captures barred, White now kills, so Black lives only through the two kos (the narrow area grades the position after C18 ADKO). After the tree's A19, Black lives outright with D19 (this leaf) or C17.
- Searches: Budget 400000 per search; one retry at 2000000; each search run on its own.

The checker's label:
- evidence.py 11332 "D17 E19 D19 C19 B19 B18 C18 A19 D19" --tree --label: label KO+ only by its leaf convention, class ALIVE.
- Narrow area: modes A=N D=N; rule 1 NN; clean test NN; seki NN.
- Wider area: class search undecided (4,000,715 nodes); its profiles and counts are undecided too (up to 30M nodes). None of these undecided searches is used below.

Narrow area, leaf (my script calling Solver.modes and count):
- Rules 0, 1, 2 and 3 all give A=N D=N (3,621 / 3,621 / 2,435 / 2,435 nodes).
- With Black barred from every ko capture (kofree=B), rules 0 and 1 give A=N D=N (3,465 / 3,763 nodes, 0 cycles).
- Counted search with White 6 threats ahead (s=0, o=6): Black wins (5,329 nodes).
- Every White move (21, including D18! and pass), then Black to play: A=N D=N under rule 0 and under kofree=B. D18! took 2,308 / 2,993 nodes.

Wider area, every White move at the leaf (28, including pass), then Black to play:
- All are A=N D=N under rule 0 and under kofree=B, except D18!.
- D18!: rule 0 mode D undecided (801,293 nodes). Clean mode D (kofree=B) is decided N (812,319 nodes), under rule 1 as well with 0 cycles. Its line is C17 B19 A18 A16 D19 pass C19.
- A16, A18, B19, F19, C15, D15, G19 and pass rechecked under rule 1 with kofree=B: all N, 0 cycles (62 to 4,759 nodes).
- The end positions of variations 1 to 8 are all ALIVE in the wider area (rules 0 and 1, kofree=B; 0 to 188 nodes).
- Not used (undecided): the wider-area leaf-level clean mode D at 400k (803,644 nodes) and its s=0, o=6 count (4,000,002 nodes). The move-by-move searches decide the leaf: White to move, and every child is N.

Context:
- Parent node (after W A19, Black to play), narrow area: ALIVE, clean NN; best moves D19 and C17.
- After Black's C18 (White to play), narrow area: ADKO, by A=N, D=K under rule 0, D=N under rule 2, and a two-ko cycle (13,185 nodes). White's best reply is C17!.
- The same position, wider area: rule 2 D=N; rule 0 mode D undecided at 400k and again at 2,000,000 (4,001,224 nodes).
- After C18 C17!, narrow area with kofree=B: A=K D=K (1,786 nodes).
- Leaves 1 and 2, narrow area: ADKO, agreeing with the tree.
- Reviewer 1 (position, upholds): I could not refute the analyst. Leaf 3 is ALIVE, so the tree's S[ADKO] is a site error. The checker's KO+ comes only from its leaf convention.

**How I read the position.** At the leaf White is to play and may not retake at D18. All of White's corner stones are short of liberties:
- C19 and E19 are each in atari, at B19 and F19.
- A19 and B18 share A18 and B19 as liberties.
- B19 and A18 are self-ataris for White (Black captures four with A18 or B19).

White's only real try is to retake at D18 after a threat. That captures D19 and puts C18 in atari. Black does not retake. It connects at C17, which joins C18, A17-B17 and the main group, with liberties at A16, A18, C15 and D15. D18 is then left with one liberty, D19, and every way to save the corner ends in a one-liberty group: D19 then A18 then B19 loses seven stones at F19, and B19 then A18 loses five at D19.

White's attack on the outside liberties (A16, C15, D15) always comes one move short. Black takes A19 and B18 with B19 and has two eyes, at A19 and B18. I tried White's outside moves first, too:
- C15 A18 D18 C17 D15 B19 A16
- A16 C17 D18 A18 C15 B19 D15

Both end in two eyes, statically alive (0 nodes). There are two ko shapes at the leaf, D18/D19 and C19 taken at B19, which probably explains the author's ADKO, but Black needs neither of them.

**My own searches** (fresh engine process, one at a time, 400k per search; files in /tmp/claude-0/proto/research/kores/work/k029-refute-c8255/):
- **Narrow area, the leaf:**
  - Cycle rules 0 to 3: A=N D=N (3,621 / 3,404 / 1,922 / 2,423 nodes).
  - Black barred from every ko capture (kofree=B), rules 0 and 1: N/N, 0 cycles.
  - The seki test (sekikill), plain and kofree=B: N/N, so this is not seki.
  - Counted search: Black wins at s=0 o=6 (5,536 nodes) and at s=0 o=0.
  - All 20 White moves, including D18! and pass: N under rule 0 and under kofree=B.
- **Wider area, the leaf:**
  - All 28 White moves with kofree=B: N.
  - D18!: D=N (812,319 nodes) under rules 0 and 1, line C17 B19 A18 A16 D19 pass C19.
  - The position after D18! C17 is decided at its own level: N/N with kofree=B (13,535 nodes) and under rule 0 (18,084 nodes). All 28 White replies there are N.
  - Not used: the leaf-level clean mode D is undecided at 400k (803,644 nodes) and again at 2M (4,003,644 nodes).
- **Variations:** the end positions of variations 1 to 8 and of my two extra lines are all ALIVE (narrow area, rule 0 and kofree=B). Every move in them is legal.
- **Parent node** (after White A19): N/N, and C17 and D19 are both N. Black's A18 there gives A=N D=K, the ADKO line of leaf 2. So the tree's White A19 at move 8 is a mistake.
- **After Black C18, narrow area:** rule 0 A=N D=K (13,185 nodes) and rule 2 D=N, so ADKO, with C17! as White's key move. White's other 8th moves are all N/N.

**On the checker's convention.** The rule that turns this leaf into KO+ (`ko_taken_by`) is a heuristic from the first commit of the checker prototype, not one of the owner's rules or open ques
- Reviewer 2 (definitions, upholds): The verdict holds: this is a site_error, and the leaf should be S[ALIVE]. Neither the tree's ADKO nor the checker's KO+ fits besogo's definitions, and the case does not turn on any open question.

DEFINITIONS
- A leaf's S[] is the status of its own position, with White to move. besogo works out every node's status by minimax over these leaf statuses. For a LIVE problem, ALIVE (type 11) ranks above ADKO (9) and KO (7).
- ADKO means Black lives only through a double ko. The checker's test (design D10) needs White to kill when it wins every ko under cycle rule 0 (A=N, D=K), with rule 2 saving Black. Here White does not kill even then: D=N under rule 0. Black also lives with every ko capture of its own barred. So ADKO fails its own definition.
- KO+ (count T=0) says Black wins exactly when it has at least as many threats as White. Black wins with s=0 against o=6, and also when White has unlimited threats, so KO+ gets the count wrong.

WHERE THE CHECKER'S KO+ COMES FROM
- It comes only from ko_taken_by (tsumego.py; design section 4 step 5). That rule turns a clean class into KO+ when the line ends with the solver's ko capture.
- It is a checker rule, not one of the owner's definitions, and it is not among open questions 1a-10 or the approach-label decision.
- It does match a habit of the owner's. In the kovalid validation runs the rule fires on 11 leaves, and the owner wrote KO+ on 9 of them. The research once told him he "may keep" such a KO+ (15399).
- It cannot be the right S[] here, for two reasons:
  - His own label here is ADKO, not KO+, so the habit is not what he applied.
  - S[KO+] would make besogo mark Black's D19 at move 9 wrong, because KO+ ranks below leaf 2's ADKO, yet D19 lives cleanly. The rule's own reason ("the direct move is the clean one") does not apply when the ko capture is itself clean.

OPEN QUESTIONS
- Q8 (open edges): the narrow area makes everything outside White's wall, which can only help White, and it gives ALIVE. The wider area agrees through the move-by-move check.
- Q10 (superko): no repetition is needed. The clean proofs have 0 cycles, and rules 0, 1 and 2 all give NN.
- Q7 (target): the target includes A17-B17 across the cut diagonal B17/C16. Each part lives when searched as the only target (narrow, kofree=B: NN for A17 B17 and NN for C16-F16-E18).
- Q6 (seki): the seki test gives NN.
- Questions 1-5 and 9: there is no ko structure at the leaf.

MY SEARCHES (budget 400k, one engine at a time, files in /tmp/claude-0/proto/research/kores/work/k029-defrev-c8255/)
- Narrow area at the leaf:
  - rule 0: NN (3,621 nodes)
  - kofree=B (Black barred from ko captures), rule 0: NN (3,642 nodes, 0 cycles)
  - kofree=B, rule 1: NN (3,550 nodes)
  - rule 2: NN
  - classify: ALIVE (clean NN, seki NN)
  - count s=0, o=6: Black wins (5,173 nodes)
  - The clean principal lines are D18! C17 D19 A18 D15 B19 and D18! C17 B19 A18 D15 D19 pass C19.
- Wider area at the leaf:
  - The leaf-level mode D is undecided (800,715 / 803,726 nodes

