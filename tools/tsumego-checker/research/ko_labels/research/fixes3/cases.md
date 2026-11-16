# The checker faults left after the merged fixes (16 November)

Each case: a leaf whose right label the ko-label research settled (an analyst and reviewers, judges where they
disagreed), and the label the merged checker (tsumego-checker at e019960d) still gives. The tree S[] is the owner's;
"right" is the research's verdict (for a site error the tree is wrong too). The merged checker's label and parts come
from validate.py at budget 200000 in the narrow area with a 300 s limit.

## k023: problem 10403 (Life & Death - Advanced 395)

- Leaf: B18 A18 C19 A16 A19
- Tree S[A+1KO-]; research verdict site_error; right S[A-1KO-]
- Merged checker: label A-?KO?; class KO; strict None; net None; count None (decided False); d_conv None; 152.3 s, 46244318 nodes
- Checker text: A-?KO?: White takes first at B19 (from the mode lines). Black needs ? approaches (1 forced pass is not enough, 3 are). White's capture is direct. Full count undecided (between 2 and 4). Undecided: count (31007800 nodes), profile B k=2 (14000006 nodes).
- Parts: first take {"side": "W", "point": "B19", "how": "modes", "preparation": [], "investment": null}; approaches {"S": null, "O": 0}; free moves null; local threats null; stages []; gain {"side": null, "total": null, "located": 0, "unlocated": null}; second ko null; alternative null
- Warnings: []
- Undecided parts: [{"part": "count", "search": "W to move s=3 o=0 end=2,pv=1", "nodes": 15006050, "area": "narrow"}, {"part": "count", "search": "W to move s=2 o=0 end=2,pv=1", "nodes": 16001750, "area": "narrow"}, {"part": "profile B k=2", "search": "W to move s=0 o=4 pw=2,end=2,pv=1", "nodes": 14000006, "area": "narrow"}]
- Analyst (site_error, says A-1KO-): S[A+1KO-] says White needs an approach and that Black wins with as many threats as White. Both claims are wrong: White's B19 capture threatens to live at once (if Black does not retake, White C18 lives), and Black fails even with one more threat than White, in both search areas. The leaf is an approach ko the other way round. White takes first at B19, and Black needs one approach at E19 before its capture at A17, in the second ko, threatens to kill. So Black needs two threats more than White: S[A-1KO-], your label with the sign of the approach flipped. That count (2) is decided only in the checker's search area; in the wider area, where White has extra moves at F19 and G18 against Black's F18, balance 2 stayed undecided. No move's grade changes, because any KO beats the SEKI and ALIVE siblings.
- Key line from the leaf: B19 A19! pass E19 B19 A19! pass A17 pass C18
- Variations: All move lists start at the leaf with White to play; ! marks a retake after a threat elsewhere. (1) Equal threats: B19 C18 D19 (W B19, B C18, W D19). Black cannot retake, White captures three stones and lives; Black fails at s-o=0. (2) Black one threat ahead: B19 A19! C18 A17 E19 B19 A18 pass D19. Black's extra threat is used up and White lives. Every Black alternative after W C18 and after W E19 also fails (decided in both areas). (3) Black two threats ahead (search area): B19 A19! pass E19 B19 A19! pass A17 pass C18 and White is dead. Or B19 A19! pass E19 B19 A19! C18 A17 pass D19, which captures. After E19, Black's A17 capture is a real threat. (4) Without the approach: B19 A19! pass A17 pass. Every Black move now fails against White's threats (0 against 4), so A17 alone threatens nothing. (5) Wrong approaches: B19 A19! pass C18 B19 A19! F19 and B19 A19! pass D19 B19 A19! A17. White wins both at equal threats from there, so E19 is the only approach that works. (6) White's capture is a real threat: B19 pass C18. White lives even if Black has 4 threats. (7) White playing elsewhere after Black's first retake costs White nothing: the count is 1 both at B19 A19! and at B19 A19! pass. So Black's retake at A19 is not a threat by itself, which rules out a plain KO-2.
- Searches: Helper /tmp/claude-0/proto/research/kores/work/k023/h.py (the checker's Solver, both areas). Each search used 400k nodes; listed retries used 2M. Leaf L = B18 A18 C19 A16 A19 (White to play). Positions below are moves after L. Counts at L. Search area: s0 o0 fails (48 nodes), s1 o0 fails (528), s2 o1 fails (997). Wider area: the same three fail (88, 2589, 4013 nodes). Search area s2 o0: the direct search is undecided, so I expanded it one ply. White's D19, E19, C18, A17 and pass lose (all under 700 nodes). After F19, Black's A17 wins (6570). After B19, only A19! is playable for Black. At P2 = B19 A19! with s1 o0, White's D19, E19, C18, A17 and pass lose, and after F19 Black's A17 wins (8299). So Black wins at s2 o0: the count is 2 in the search area. The shifted check at s3 o1 stayed open: the line B19 A19! F19 A17 was undecided at 2M. Wider area s2 o0 and s3 o0: B19, E19, F19, G18, G19, H19 and pass are undecided at 400k. All seven White replies at P2 are undecided at 2M. The mode D class is also undecided at 2M, so the wider area's count is undecided. White's capture: B19 with Black forced to pass once and Black holding 4 threats is a White win in 3 nodes, in both areas. Black's profile from P2 in the search area: 1 forced White pass fails (415k nodes); 3 forced passes win outright (416). For 2 passes, after White's first forced pass every Black move fails except A17, and after A17 plus the second forced pass every Black move fails (decided). So the forced-pass count is 2. Held-ko check, with White barred from B19 and 1 forced pass: Black wins (741). Value after two free moves (v_S) = 0: Black wins s0 o0 and fails s0 o1, with the line C18 then A17. So the checker's D6 would print A-2KO+ (count 2) once its profile is decided. That counts the second-ko capture A17 as an approach, which is the known second-ko weak point. Count reading (search area): count at P2 = 1, count after B19 A19! pass = 1, count after B19 A19! pass E19 = 1, and count at Q = B19 A19! pass E19 B19 A19! = 0 (wins s0 o0 in 5890 nodes, fails s0 o1). This is the A-1KO- pattern, and White gains nothing in the approach round. A17 before the approach (B19 A19! pass A17 pass, s0 o4): every Black move fails, in both areas. A17 after the approach (B19 A19! pass E19 pass A17 with White forced to pass once, s0 o4): Black wins with C18 in both areas (54 and 87 nodes). Class in the search area: A=N, D=K. My own full evidence.py --label run was still in the wider area after 12 minutes and I stopped i
- Reviewer 1 (position, upholds): Verdict holds: site_error, A-1KO-. But the analyst's key line fails on the real board and needs replacing (see below).

All searches were my own, one engine process at a time, 400k nodes per search with 2M retries, using my own helpers. Files are in /tmp/claude-0/proto/research/kores/work/k023-posread-c8255/ (r.py, prove.py, prove2.py, *.out). Move lists start at the leaf, White to play; ! marks a retake after a threat.

1) Tree is wrong (decided in both areas). Black fails at s0 o0 (48 / 88 nodes), s1 o0 (528 / 2589) and s2 o1 (997 / 4013). White's capture at B19 is direct: with Black forced to pass once and holding 4 or 6 threats, White lives with C18 (2-3 nodes). A+1KO- implies Black wins at equal threats and that White needs an approach; both are false.

2) Count 2 in the search area (decided by expansion). At L with s2 o0, White's D19, E19, C18, A17 and pass lose. After F19, Black's A17 wins (6570). After B19, A19! is Black's only move. Then at P2 (B19 A19!) with s1 o0, White's D19, E19, C18, A17 and pass lose, and after F19 Black's A17 wins (8299). Counts: P2 = 1, P2p (B19 A19! pass) = 1, after E19 = 1, Q (B19 A19! pass E19 B19 A19!) = 0 (2386 / 32 nodes).

3) A local threat the analyst missed. It does not change the status, but it breaks the key line.
- In the wider area, Q at s0 o0 is a Black loss (proved by expansion). White plays F19, an atari on E19. Every Black answer loses except A17. White then plays G18, an atari on F18:
  - F17 lets White retake A18 for free, and every Black move then loses (B19 is met by H18, 405k).
  - A18 loses to F17, and every Black move then loses.
  - B19 loses to D19 (4753).
- At Q with s1 o0 Black wins: White's F19 loses (13432), and after G18 Black's A17 wins (9535).
- So the line B19 A19! pass E19 B19 A19! pass A17 pass C18 works only in the search area. There White's G18 touches the wall at H18 and has one liberty, so Black captures it at G19. On the board, and in the wider area, it has two (G19, H18).
- Refutation on the board: B19 A19! pass E19 B19 A19! F19 A17 G18 F17 A18. Black has no threat left.

4) Black's correct order: take the second ko first. At P2p with s1 o0, A17 wins in both areas.
- Narrow area: 2225 nodes.
- Wider area: White's C18, D19, E19, G19, H19 and pass lose. F19 is answered by G18 (9675). G18 is answered by E19: then A18 is answered by F17 (that position wins in 4755 nodes), and F17 is answered by F19 (400917).
- Approaching first (E19) at P2p loses in the wider area. B19 there also loses (D19, then F19).
- New key line (wider area, walked): B19 A19! pass A17 G18 E19 A18 F17 B19 A19! C18 A17 pass D19.
- Search area: B19 A19! pass A17 C18 E19 A18 A17! pass D19.

5) Structure. White's B19 always threatens. Black's retake is not a threat (P2 = P2p = 1). Black needs one non-capturing move, E19, which gives White one free retake (at A18 when A17 comes first). A17 is a capture that White can only retake with a threat, so it is no approach. Profile from P2: 3 forced passes win (188), 1
- Reviewer 2 (definitions, disputes): The analyst is right that the tree is wrong, and every fact the analyst decided reproduces in my searches. But A-1KO- does not follow from the definitions alone. After the one approach E19, Black's retake is still not a real threat. Black also needs its capture A17 in the second ko, and White gets a second free move. The design's D3/D6 count that capture as an approach and give A-2KO+ with the same count. The owner's definitions do not decide how a capture in a second ko counts, so this is a definition case.

WHAT I RAN. Helper: /tmp/claude-0/proto/research/kores/work/k023-defrev-c8255/d.py. It imports the checker read-only, starts one engine process per run, builds the area like evidence.py/add_wide, and uses 400k per search with one retry at 2M. Logs are in the same folder: basic.out, Q_narrow.out, Q_wide.out, Q_pass_moves.out, Bstate_R_narrow.out, count2_narrow.out, count2_A17_narrow.out, profile_P2_narrow.out, P2_tenuki_narrow.out. Positions below are moves after the leaf (White to play). P2 = B19 A19!, Q = B19 A19! pass E19 B19 A19!, R = Q pass A17 A18!.

1. THE TREE IS WRONG UNDER EVERY READING.
- Black fails at s0o0, s1o0 and s2o1 in both areas (narrow 48/528/997, wide 88/2589/4013 nodes). So the count is at least 2, against the label's T=0.
- White's capture is direct. After B19 with one forced Black pass, Black fails even holding 4 threats (2 nodes, both areas). So n_O=0, while A+1 gives White the approach.

2. COUNT 2, NARROW AREA ONLY.
- At s2o0 White's D19, E19, C18, A17 and pass lose (118/93/76/670/363 nodes).
- F19 is refuted by A17 (6570).
- After B19, Black's only move is A19!. At P2 with s1o0, White's D19, E19, C18, A17 and pass lose, and F19 is refuted by A17 (8299).
- Wider area: balance 2 stays open (analyst), so a count of 3 is not excluded there.

3. THE STRUCTURE.
- P2: count 1 with or without a White tenuki (fails 601 and 1752 at s0o0, wins 2846 at s1o0 with the tenuki). This is the approach round.
- Held-ko check: White barred from B19, one forced pass, Black wins at s0o4 (narrow 261, wide 2993). So these are approaches, not stages.
- D3 profile at P2: one forced pass fails (3297). With two, every first move loses except A17, and after "pass A17 pass" every Black move loses (decided). Three win (188). So n_S=2.
- v_S with two forced passes = 0 (wins 2263, fails 6935). D6 with F=O gives a=-2, e=0: A-2KO+, T=2.
- At Q, after the approach, Black's retake is still not a real threat:
  - One forced White pass fails at s0o4 (863); two win (61).
  - Wide: the direct search is undecided at 2M, but the one-ply expansion decides it. After White's tenuki every Black move loses, and A17 is refuted by A18!: after it every Black move loses.
  - White's tenuki at Q is free: count 0 with it (1753/2049) and without it (2386/32).
- After A17 the ko is direct: one forced pass and Black wins at s0o4 (narrow 21, wide 33).
- After the approach the ko switches there and back through the second shape. At R Black's tenuki is free (count 1 with an
- The owner's answer: {"question": "1b", "answer": "after Black's one approach (E19) the ko switches there and back, each side then needing a capture and one more move: a symmetric ko, which is a normal ko, so 10403 is a one-move approach ko for Black, S[A-1KO-] (the owner, 16 November)", "before": "definition"}

## k029: problem 11332 (1000 Weiqi problems - 1st half 517)

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

## k035: problem 13489 (1000 Weiqi problems - 1st half 456)

- Leaf: B19 D17 D19 E19 A18 C18 C19 A17 F19 B18
- Tree S[KO-]; research verdict site_error; right S[A-1KO-]
- Merged checker: label A-1KO?; class KO, BENT4 not ruled out (bent-four test undecided at 401,131 nodes); strict None; net None; count None (decided False); d_conv None; 130.6 s, 35460174 nodes
- Checker text: A-1KO? (class KO, BENT4 not ruled out (bent-four test undecided at 2,001,131 nodes)): White takes first at A19 after the preparation B J18 (from the mode lines). Black needs one approach (D18, A16 undecided) before the ko is direct. Full count undecided (between 1 and 2). Undecided: bent-four (2001131 nodes), count (19025020 nodes), direct ko B (14000006 nodes).
- Parts: first take {"side": "W", "point": "A19", "how": "modes", "preparation": [["B", "J18"]], "investment": null}; approaches {"S": 1, "O": 0}; free moves null; local threats null; stages []; gain {"side": null, "total": null, "located": 0, "unlocated": null}; second ko null; alternative null
- Warnings: ["bent-four test undecided", "approach line: the direct-ko value is undecided"]
- Undecided parts: [{"part": "bent-four", "search": "solve bentfour", "nodes": 2001131, "area": "narrow"}, {"part": "count", "search": "B to move s=1 o=0 end=2,pv=1", "nodes": 19025020, "area": "narrow"}, {"part": "direct ko B", "search": "W to move s=1 o=0 pw=1,end=2,pv=1", "nodes": 14000006, "area": "narrow"}]
- Analyst (site_error, says A-1KO-): The tree's KO- is wrong: Black needs two ko threats more than White, not one. Black's A16 looks like a forcing approach, but it is a lone stone in atari. After White takes at A19 and Black retakes, White captures it at A15 and keeps a free retake, so Black must first play A15. White then takes the ko at A19 at once (its capture threatens D18), and Black needs the approach A16 before its retake threatens anything. That is S[A-1KO-], the approach label the checker started (A-1KO?) but could not finish. Under the open approach-label decision the counted form would be KO-2, with the same threats; the tree's KO- is wrong under either choice.
- Key line from the leaf: A15 A19 A18! pass A16 A19 A18! pass D18
- Variations: A15 A19 A18! pass A16 A19 A18! pass D18: with two threats more than White Black kills. A15 prepares, White takes first, Black's approach A16 gives White a free retake. | A15 A19 A18! pass A16 A19: with one threat Black cannot retake again, and White captures B19-C19-D19 at D18. | A15 pass A16 A19: if White waits, Black's A16 forces the take and the ko is a plain KO- (Black wins with one threat), so White takes at once. | A16 A19 A18! A15: the tree's idea fails with one threat. White answers the retake by capturing A16, and with no threat left every Black move fails, because White's retake at A19 is free. | A16 A15 A14 A19 ...: after White captures A16, Black's A14 turns A15/A16 into a second ko, and White alternates between the two kos. | A14 A19 A18! pass: with one threat Black fails here too (middle model, every move decided). | D18 A19 and A19 D18: self-atari; White captures five stones and lives. | With no threats (s=0) every Black first move fails.
- Searches: Checker run read-only, 400k per search with one retry at 2M, END_PLAY 2. Narrow area, leaf B19 D17 D19 E19 A18 C18 C19 A17 F19 B18 with Black to move: s=0 fails (4,981 nodes). s=1 is undecided as one search. s=2 wins (1,668 nodes, A15 A19 A18! J18 A16 A19 A18! pass D18). Table at s=1: D18 23, A19 205, pass 1, and G18, G19, H18, H17, J18 all fail. A15 and A16 are undecided at 400k and decided below. Leaf+A15 A19 (Black to move, s=1): every Black move fails (A18! 5,725, A16 73, pass 156). Leaf+A15 A19 A18! (White to move, s=0): fails (405,726). After pass: fails (3,576). After pass A16: fails (332). Leaf+A16 at s=1: A15 and A19 open, the rest fail. Leaf+A16 A19 (s=1): A18! open, the rest fail (A15 69, pass 75). Leaf+A16 A19 A18! A15 (Black to move, s=0): every Black move fails (D18 26, A19 215, A14 133, C15 1,222, B14 1,378, pass 1). So A16 fails to White's A19 and the narrow count is exactly 2. Shifted check after A15: s=3 o=1 wins (1,047); s=2 o=1 undecided at 2M. Leaf+A15 pass A16 A19: s=1 wins (15), s=0 fails (331). Wider area: leaf+A15 at s=2 wins (5,090 nodes); leaf+A15 A19 A18! at s=1 wins (2,810), so the count is at most 2 with the edge open. Middle model (narrow area plus A14 B14 A13 B13), leaf+A14 A19 at s=1: every move fails except A18!. After A18! pass (s=0) every move fails. So Black's free use of A14 does not lower the count. On the checker's label: its first take names J18 as Black's preparation, but the right preparation is A15. Its approach candidates D18 and A16 come from the forced-pass test. With White free to answer, D18 loses five stones at A19 and a lone A16 is captured at A15. Its A-1 holds only once A15 is on the board. Its class 'KO, BENT4 not ruled out' comes only from an undecided test: White's captures leave two separate eyes (A18 and B19-C19-D19), so no bent four arises.
- Reviewer 1 (position, upholds): I could not refute the verdict. The count is exactly 2, A15 is Black's only first move that reaches it, and the label's parts read A-1KO- by D2 to D6. The counted form KO-2 has the same count; that is the open approach-label question, and the tree's KO- is wrong either way.

How I ran it: fresh engine processes, one at a time, with END_PLAY 2. Each single search got 400k with one retry at 2M. Each move in a one-ply table got 400k plus the engine's own fresh retry. I used three areas: the narrow area, a left-edge model (narrow plus A14 B14 A13 B13, which adds the fringe A12 B12 C13), and the wide area. Scripts and logs are in /tmp/claude-0/proto/research/kores/work/k035-posread-c8255/.

1. The count is 2.
- Narrow area:
  - s=0 fails (4,981 nodes).
  - At s=1 the table is: D18 23, A19 205, pass 1; G18, G19, H17, J18 fail at about 405k; H18 fails at 2,157. A15 and A16 stay undecided as single searches at 2M, so I decided them through their subtrees:
    - A15: after W A19, every Black move fails (A18! 5,725).
    - A16: after W A19, every move fails except A18!. After A18!, W A15 is White's only move that does not lose, and after it every Black move fails at s=0.
  - s=2 wins (1,655): A15 A19 A18! J18 A16 A19 A18! pass D18.
- Left-edge model, at s=1: every Black first move fails. That covers A15, A16, A14, B14, A13, B13 and the tenukis G18, G19, H18, H17, J18; D18, A19 and pass fail at once. For each, W A19 leaves only A18! open. After A18! and a White pass (W A15 after A16), every Black move fails at s=0. At s=2 the leaf wins (7,375).
- Wide area: A15 at s=2 wins (5,089). At s=1 most root moves are undecided, because the right-side empty points (G to L) inflate the search. Those points do not touch the fight, and the left edge is covered by the left-edge model.
- With one White threat: s=3 o=1 wins (1,845). s=2 o=1 fails: after A15 and W A19 only A18! is open, and after A18! and a pass every move fails.

2. Could a second first move reach count 2 and change the label (design D12)? No.
- A16 fails even at s=2:
  - W A19, then Black's only try A18!, then W A15 (White's only move).
  - Black's A14 there turns A15/A16 into a second ko. White retakes one ko for free whenever Black takes the other: A14 A19 A18! pass, then every Black move fails at s=0.
  - C15, B14 and the tenukis also fail at that point, each through W A19, then A18! and a pass.
- The tenukis G18, G19, H18, H17, J18 fail at s=2, each through W A19, then A18! and a pass. The one open entry, H17 ... A16, I closed via W A19: after it every Black move fails, A18! included.

3. The label's parts, checked directly with forced-pass searches:
- First take: White at A19 after Black's preparation A15. P_F = leaf A15 A19 wins at s=2 (414) and fails at s=1, so d(P_F) = d(leaf) = 2.
- White must take at once. If White passes after A15, Black wins at s=1 (53). After "A15 pass A16 A19", Black wins at s=1 (15) and fails at s=0 (331).
- Black's approach count: from P_S = leaf A15 A19 A18!, one forced W
- Reviewer 2 (definitions, upholds): The verdict holds: site_error, with the correct status A-1KO-. The tree's S[KO-] has count T=1. Black really needs two threats more than White (T=2). The open approach-label decision only chooses how to write T=2: A-1KO- in the approach form or KO-2 in the counted form. KO- is wrong under either, so the verdict does not depend on that question.

I used the checker read-only with PROBLEMS=/tmp/claude-0/dump2/status_problems.jsonl, END_PLAY 2. Every search was 400k with one retry at 2M, one engine process at a time. Scratch files and the full log are in /tmp/claude-0/proto/research/kores/work/k035-defrev-c8255/ (log.txt).

**The facts, rechecked with my own searches**

Narrow area, at the leaf with Black to move:
- Class: plain modes give A=N D=K. The clean kill gives A=N D=N. So it is a ko. The bent-four rule's mode A stays undecided at 2M.
- s=0 fails (4,981 nodes). s=2 wins (1,655 nodes) with A15 A19 A18! J18 A16 A19 A18! pass D18.
- s=1 is undecided as one search. Its one-ply table: D18 23, A19 205, pass 1, and G18, G19, H18, H17, J18 all fail.
- A15 fails at s=1. After leaf+A15 A19, every Black move fails (A18! 5,725, A16 73, pass 156).
- A16 fails at s=1. Leaf+A16 A19 leaves only A18! open. Then leaf+A16 A19 A18! A15 at s=0: every Black move fails.
- So the count at o=0 is exactly 2.
- Shifted check: s=3 o=1 wins (1,845). s=2 o=1, which the analyst left undecided, fails after A15 when split one move deeper: leaf+A15 A19 leaves only A18! open, and after A18! pass every Black move fails at s=1 o=1.

**The label parts, by the design's definitions (D2 to D8)**
- **First take (D2):** White's A19 after A15, so the ko part is KO-. White waiting is worse for White: leaf+A15 pass gives Black a win with one threat (53 nodes) and a loss with none, a plain KO-. So White takes at once. d(P_F)=2: leaf+A15 A19 wins at s=2 (414) and fails at s=0.
- **Black's approach count (D3):** From leaf+A15 A19 A18!, one forced White pass does not give an outright win (3,125 nodes). Two forced passes do (4). So n_S=1.
- **Approach or stage (D4):** With White barred from A19, Black wins outright (18), so it is an approach, not a stage.
- **Which move is the approach:** The candidates are D18 and A16. D18 fails even at s=4 once White may answer: White captures five stones at A19. A16 keeps v (wins at s=1, fails at s=0).
- **White's count:** With Black forced to pass, White wins outright even against 4 Black threats (116), so n_O=0.
- **Direct ko (D5):** With one forced White pass, Black wins at s=1 (54) and fails at s=0 (3,902). So v_S=1.
- **Label (D6):** a=-1 and e=-1, which is A-1KO-, with label count 2.
- **White's gain in the approach round (D8):** 0. In the approach round of the full count line (leaf+A15 A19 A18!, White to move, s=1), every White move fails, including D18 and A16 at 1 node each. So the full count equals the label count, and the strict label is also A-1KO-.

**No tie at the leaf (question 9)**
A15 is the only first move that wins at s=2:
- A16 fails: 

## k041: problem 13547 (1000 Weiqi problems - 1st half 486)

- Leaf: B18 B19 D19 C19 B17 A18 A17 A15 E19 C18 A14
- Tree S[10kKO]; research verdict solver_error; right S[10kKO]
- Merged checker: label A?KO?; class KO, BENT4 not ruled out (bent-four test undecided at 401,669 nodes); strict None; net None; count None (decided False); d_conv None; 104.4 s, 55429207 nodes
- Checker text: A?KO? (class KO, BENT4 not ruled out (bent-four test undecided at 2,001,669 nodes)): Who takes first is undecided. Black needs ? approaches (2 forced passes are enough, fewer undecided). White needs ? approaches (2 forced passes are enough, fewer undecided). Full count undecided (between 0 and 1). Undecided: bent-four (2001669 nodes), count (24002277 nodes), profile B k=1 (14000006 nodes), profile W k=1 (14000006 nodes).
- Parts: first take {"side": null, "point": null, "how": null, "preparation": [], "investment": null}; approaches {"S": null, "O": null}; free moves null; local threats null; stages []; gain {"side": null, "total": null, "located": 0, "unlocated": null}; second ko null; alternative null
- Warnings: ["bent-four test undecided"]
- Undecided parts: [{"part": "bent-four", "search": "solve bentfour", "nodes": 2001669, "area": "narrow"}, {"part": "count", "search": "W to move s=0 o=0 end=2,pv=1", "nodes": 24002277, "area": "narrow"}, {"part": "profile B k=1", "search": "W to move s=0 o=4 pw=1,end=2,pv=1", "nodes": 14000006, "area": "narrow"}, {"part": "profile W k=1", "search": "B to move s=4 o=0 pb=1,end=2,pv=1", "nodes": 14000006, "area": "narrow"}]
- Analyst (solver_error, says 10kKO): White's best move is D16. It joins White's two chains and leaves A16 and C17 as liberties that both White's group and Black's three inner stones need. Whoever fills one first hands the other side a capture that wins at once. That is your 10kKO, so S[10kKO] is right. White's C17 instead gives A-1KO+, which besogo ranks the same in a KILL problem. The checker's KO? came from a fallback line in which White fills A16 itself, which hands Black a direct ko. After D16 Black needs one threat more than White, a count the checker had left undecided.
- Key line from the leaf: D16 A19 pass C17 A18 A19! pass A16
- Variations: Leaf = B18 B19 D19 C19 B17 A18 A17 A15 E19 C18 A14, White to play. (1) D16 A19 pass C17 A18 A19! pass A16: White connects; Black takes at A19 without a threat; White waits; Black invests at C17; White retakes at A18 without a threat and threatens A16; Black retakes with its threat and captures at A16. Black wins with 1 threat against 0 and fails with 0 against 0. (2) D16 A19 A18! pass A16 A19: the mirror. If White invests at A16 (after retaking with a threat), Black retakes for free and White's whole group is in atari at C17. (3) A16 A19: White fills the shared liberty itself (the checker's preparation). The ko is then direct for both sides and Black took first: Black wins at s=o=0 and fails at s=0, o=1 (KO+, count 0), so A16 is worse for White than D16. (4) C17 A19: Black needs D16 before its capture threatens, while White's retake at A18 threatens at once: A-1KO+, ranked equal to 10kKO. (5) A19 (White connects its ko stone): Black D16 kills; Black wins even at s-o=-2.
- Searches: Narrow area (the checker's search area plus its fringe); each counted search 400,000 nodes, one retry at 2,000,000. Leaf + D16 at s=o=0: undecided as a single search. One-ply table there (each child 400k): C17, A16, F19, A13 and pass all fail; A19 undecided. Leaf + D16 A19 pass: d=1 (s-o=0 Black fails, 2,889 nodes; +1 Black wins). Its one-ply table at s=o=0: C17, A16, F19, A18, A13 and pass all fail. So after D16 Black fails at s=o=0, and the leaf's count is at least 1. Leaf at s=1, o=0: Black wins (15,923 nodes), so the full count is 1. Leaf + A16 A19: s=o=0 Black wins (4,722); s=0, o=1 Black fails (169). Leaf + A19: Black wins at s-o=-2 (3,616). Switch test after D16: D16 A19 pass C17 A18 and D16 A19 pass A16 A18, with Black forced to pass once and holding 4 threats: White wins outright (46 nodes each). D16 A19 A18! pass A16 A19 and ...C17 A19, with White forced to pass once and holding 4 threats: Black wins outright (2 nodes each). Black's profile after D16 A19: one forced White pass is not outright (3,214); two forced passes are outright (4); so n=1 on each side, mutual. Leaf + C17 A19 pass at s=o=0: every Black move fails except the self-atari A18 (undecided at 800k); at s=1, o=0 Black wins. Wide area: leaf + D16 A19 pass: +1 Black wins (79), 0 undecided at 4.8M. Dossier label: KO?, first take from the mode lines after W A13, B B13, W A16, count bracket [0,1].
- Wrong part of the label (analyst): Wrong parts: first take, approaches and count. Because the count was undecided, the first take fell back to the mode lines (W A13, B B13, W A16, then B A19). In that line White fills A16, a liberty both sides share. That hands Black a direct ko (count 0) and is not White's best play, so 'both captures are direct, approaches 0' describes a White mistake. After White's D16 both sides need one approach at the shared liberties A16/C17, and the switch test shows each approach hands the other side a capture that threatens at once: 10kKO, count 1. The count, left between 0 and 1, is 1: after D16 every Black reply fails at s=o=0.
- Reviewer 1 (solver, upholds): I tried to refute the analyst and could not. I ran every search in a fresh engine with one search at a time (400k nodes, one retry at 2M, 4.8M with the engine's own internal retry). Every claim the analyst decided gave the same result, and my extra checks support the verdict.

The leaf count is exactly 1 (narrow area, all decided):
- Black wins at the leaf with s=1, o=0 (15,923 nodes). The one-ply table at s=1, o=0 shows all seven White moves failing: C17, A16, A19, D16, F19, A13 and pass. So no White move pushes the count to 2.
- Black fails after White's D16 at s=o=0. The search there is undecided as a single search (4.8M), but its one-ply table is complete (the area has only five empty points plus the fringe). C17 and A16 fail at once because they are self-atari; F19, A13 and pass also fail. Only A19 stays open.
- After D16 A19, White's pass wins at s=o=0 (2,051). After D16 A19 pass, every Black move fails at s=o=0: C17, A16, A18, A13, F19 and pass. The same holds with the "last move was a pass" flag (2,771 nodes). Black wins there at s=1 (21 nodes).
- After D16, s=2, o=1 is a Black win (702) and s=1, o=1 a Black failure (4,220).

The structure after D16 is the owner's 10kKO (narrow area, decided):
- Black's capture at A19 is not outright with one forced White pass (2,831) but is with two (4), so Black needs one approach.
- White's retake at A18 is not outright with one forced Black pass (Black wins, 2,012) but is with two (70), so White needs one approach too.
- Held-ko checks: with the retake point barred, Black wins (12) and White wins (45), so both are approaches and not stages.
- Switch test: after Black's approach at A16 or C17 and White's free retake, White's capture is a real threat (Black fails with 4 threats, 46 each). The mirror holds: after White's approach, Black's free retake wins outright (2 each).
- So the two shared liberties A16 and C17 serve as the approach for both sides, the ko never switches back, and White lives in seki if nobody invests. That is D9's textbook 10kKO, the same shape the inventory already found at sibling leaf 26.

White's other moves are worse or only tie:
- A16 (the checker's preparation move) makes a direct ko that Black takes first. Black wins after A16 A19 at s=o=0 and fails at o=1, and both captures are threats at once. That is KO+ with count 0, a White mistake.
- A19 loses: Black wins even with White three threats ahead.
- C17: the leaf's count stays 1 either way, because D16 already holds Black to 1. C17's own count is open between 0 and 1: after C17 A19 pass every Black move fails except Black's self-atari connection at A18, which is undecided at 800k. White's retake after C17 is a real threat (45 nodes). Black's capture is outright with two forced White passes; one pass is undecided at 4.8M, but reading shows Black needs D16 first. So C17 is at best A-1KO+, which besogo ranks equal to 10kKO.

Wider area: it agrees wherever it decides. The k=2 profiles and all four switch tests come out the same. 

## k043: problem 13547 (1000 Weiqi problems - 1st half 486)

- Leaf: B18 B19 B17 C18 A17 D19 A19 D16 A15 A16 A14 A18
- Tree S[A-2KO+]; research verdict site_error; right S[A-1KO-]
- Merged checker: label KO?; class KO, BENT4 not ruled out (bent-four test undecided at 400,937 nodes); strict None; net None; count None (decided False); d_conv None; 25.9 s, 16435528 nodes
- Checker text: KO? (class KO, BENT4 not ruled out (bent-four test undecided at 2,000,937 nodes)): White took first (A18). Black's C19 is a capture in a second ko, after which White has no free retake, so it is no approach (D4). Both captures are direct. Full count undecided (between 1 and 2). Undecided: bent-four (2000937 nodes), count (14000157 nodes).
- Parts: first take {"side": "W", "point": "A18", "how": "leaf", "preparation": [], "investment": null}; approaches {"S": 1, "O": 0}; free moves null; local threats null; stages [{"side": "S", "rounds": 1, "moves": [[2, 0], [1, 0]], "second_ko_capture": "C19", "kind": "second ko"}]; gain {"side": null, "total": null, "located": 0, "unlocated": null}; second ko null; alternative null
- Warnings: ["bent-four test undecided"]
- Undecided parts: [{"part": "bent-four", "search": "solve bentfour", "nodes": 2000937, "area": "narrow"}, {"part": "count", "search": "B to move s=1 o=0 end=2,pv=1", "nodes": 14000157, "area": "narrow"}]
- Analyst (site_error, says A-1KO-): White has just retaken at A18, and Black's three stones are in atari at C17. Black must retake at A19, which also puts B19 in atari; White connects at C19 (or extends at E19), and Black then needs one approach, E19 (or F19), after which White retakes for free and Black needs a second threat. That is A-1KO- with a count of 2: one approach, not the tree's two, and White took first. The checker's A-1KO+ is also wrong. It took C19 as the approach because it forces White to pass, but that capture starts a second ko that White never allows (it connects at C19), and the true count is 2, not 1.
- Key line from the leaf: A19! C19 E19 A18 A19! pass C17
- Variations: Leaf = B18 B19 B17 C18 A17 D19 A19 D16 A15 A16 A14 A18, Black to play. (1) A19! C19 E19 A18 A19! pass C17: Black retakes (threat 1); White connects; Black approaches at E19; White retakes for free; Black retakes (threat 2); White's group is in atari at C17. Black needs 2 threats more. (2) A19! E19 F19 A18 A19!: White extends instead; Black approaches at F19; White retakes for free; Black needs one more threat (d=1 after F19 A18). (3) A19! pass C19: if White ignores the atari on B19, Black captures it with a ko capture of its own and White has no free retake; d=0 after White's pass. This is the checker's approach line, and White avoids it. (4) After A19! C19 E19 A18 A19!, the ko is plain and direct: Black wins at s=o=0 and fails at s=0, o=1. (5) At the leaf, E19, F19, A13 or a pass fail at once because White captures at C17; C17 is suicide.
- Searches: Checker label (evidence.py --label --budget=400000, KO_BUDGET_FACTOR=1): narrow A-1KO+, approach C19 (moves C19 B19), v_S=0, one Black extra threat unattributed, count bracket [1,2] (s-o=+1 undecided), warning 'approach line: round 1: no free retake after the approach'; wide A?KO?. My narrow searches (400k, retry 2M), with P_S = leaf + A19!: P_S at s-o=0 undecided. One-ply table at P_S with s=1, o=0: C17, C19, E19, F19, A13 and pass all lose for White, so the leaf at s=2, o=0 is a Black win. P_S + E19: d=1 (0 fails, 2,532 nodes; +1 wins, 488). One-ply table at the leaf with s=1, o=0: E19, F19, A13 and pass fail; A19! leads to P_S at s=o=0, where White's E19 wins. Full count 2. P_S + pass: d=0. P_S + C19: s=1, o=0 Black wins (21); at s=o=0 every Black reply fails except the self-atari A18 (undecided at 800k). P_S + C19 E19 A18: d=1. P_S + C19 E19 A18 A19!: d=0. P_S + E19 F19 A18: d=1. Wide area: P_S + pass and P_S + C19 E19 A18: +1 Black wins, 0 undecided at 4.8M; P_S + E19: +1 undecided.
- Reviewer 1 (position, upholds): The verdict (site_error) and the status A-1KO- with a count of 2 both survive. The analyst's key line does not: White's connection at C19 loses outright, so White's only real defence is the extension at E19.

All searches ran one engine at a time: 400k per search, one retry at 2M. Files are in /tmp/claude-0/proto/research/kores/work/k043-refute/: q.py for the narrow and wide areas, mq.py and sealed.py for the sealed model; n*.txt hold the narrow results, m*.txt the sealed, w*.txt the wide.

1. The count is 2, checked in three areas.
- Narrow area: the leaf with A19! E19 at s=o=0 is a Black failure (2,298 nodes). At the leaf every Black move except A19! fails at once. So the leaf at s=1, o=0 fails for Black.
- Narrow area, the position after A19! (P_S) at s=1, o=0: C17, C19, E19, F19, A13 and pass all lose for White, so the leaf at s=2, o=0 is a Black win. Count 2.
- Sealed model: I built it so White's first-line extension gets real points instead of the narrow area's wall at H19. Real Black wall stones stand at J19 J18 H17 G17 F16 C14 B13 A12, with F17-F19, G19, H19, H18 and A13 inside. At P_S with s=1 all ten White moves lose. At s=0 White's E19 wins: after it every Black reply fails, and Black's capture at C19 loses to White's F19 (6,832 nodes). Count 2.
- Wide area: the analyst left "P_S + E19 at +1" undecided. It is a Black win: after A19! E19 F19 every White move fails at s=1. The lower bound in the wide area stays partly undecided (Black's C19 after E19).

2. White's C19 loses, so the analyst's key line is wrong.
- After A19! C19, Black throws in at A18. White captures five stones at C17, and Black's A18 again takes the vital point of the bulky five (A17 A18 A19 B17 B18).
- Narrow area: after A19! C19 A18 C17 A18 every White move fails. A pass or F19 is answered by E19, and then every White move fails. If White plays A13 instead of capturing, Black's F19 (7,785 nodes) or B13 wins.
- Sealed model: the same. Every White try fails, including F17, F18, F19, G19 and pass, each answered by E19, which forces C17, then A18. Black still wins with White holding 4 threats (817,280 nodes); there is no ko in it.
- So the A18 move the analyst left undecided and dismissed as a self-atari actually wins, and P_S + C19 is not d=1.
- Corrected key line: A19! E19 F19 A18 A19! pass C19 pass C17, checked in both the narrow and the sealed area. White's F19 at P_S may also hold, which does not change the count.

3. The structure is unchanged: one approach, then a direct ko.
- White took first, Black needs one approach (F19), then the ko is direct. The position after A19! E19 F19 A18 has d=1 in the narrow, sealed and wide areas.
- I tried White's G19 and F18 attacks on the approach stone F19 as a local threat the analyst missed. They are not one. Black ignores them with C19, White recaptures F19, Black connects at B19 and wins the semeai: every White move then fails (sealed m16; wide w10 and w12).

4. Why each label is right or wrong.
- Tree S[A-2KO+]: the count (2
- Reviewer 2 (definitions, upholds): The verdict holds: site_error, and the correct status is S[A-1KO-]. Under every reading I tried, the tree's A-2KO+ is wrong, and A-1KO- follows from the owner's definitions without any open question. One correction: the analyst's key line rests on a White mistake (C19) and has to be replaced.

**What the labels mean (status.js)**
- A-2KO+ is approaches -2, extraThreats 0: "Black needs 2 approach moves, Black takes first", T=2.
- A-1KO- is -1/-1: "Black needs 1 approach, White takes first", T=2.
- The checker's A-1KO+ has T=1.
- The tree's label and A-1KO- have the same count, but besogo compares approaches first and ranks A-1KO- above A-2KO+ for Black. So this is not a notation difference. Both parts of the tree's label are wrong, and the two errors happen to cancel in the count.

**Facts from my own searches**
All are narrow area unless marked wide, one engine at a time, 400k per search with one retry at 2M. Scripts and the raw log are in /tmp/claude-0/proto/research/kores/work/k043-defrev-c8255/ (runs.txt).
- **First take and White's threat:** White's A18 captured A19, so White took first. White's capture threatens at once: with one forced Black pass, Black fails even at s=4 (66 nodes). So n_O=0 and the ko is not mutual, which rules out 10kKO and questions 1a/1b.
- **Black needs exactly one approach:** P_S = leaf + A19!. With one forced White pass Black fails at o=4 (2,301 nodes); with two forced passes Black wins (997).
- **Held-ko check:** with White barred from A18, Black wins outright after A19! E19 F19 (94), A19! pass E19 (43) and A19! C19 E19 (4). So E19 and F19 are approaches, not stages.
- **Full count 2:**
  - At the leaf with s=1, o=0: E19, F19, A13 and pass all fail. A19! reaches P_S at 0/0, where White's E19 wins (Black fails at 0 with 2,298 nodes, wins at +1 with 553).
  - At P_S with s=1, o=0: all six White moves fail, so Black wins the leaf at s=2.
- **Conventional line with a genuine approach:**
  - A19! pass E19: d=1 (135/103).
  - After White's free retake A18: d=1 (145/55).
  - After Black's second retake: d=0 (43/171), and also d=0 in the wide area (242/4,142).
  - So v=1, e=-1, and d_conv=2 equals the full count. The c121 rule has nothing to take out, and the strict label is also A-1KO-.
- **Why the checker's A-1KO+ is wrong:**
  - It takes C19 after a forced White pass as the approach (A19! pass gives d=0: 2,052/1,913).
  - C19 captures B19 and starts a second ko. White then has no free retake: A18 is suicide and B19 is banned. The checker's own warning says so.
  - The owner's description of approach rounds ("white always takes after that") and D5's v ("with Y now free to retake") both assume a free retake. So C19 is not an approach.
  - White never allows it anyway: E19 stops it at no cost in the count.
  - Suggested checker fix: reject an approach candidate that is a ko capture leaving no free retake, the same way D5 rejects a move that fills the ko.

**Correction to the analyst's lines**
- White's C19 in line (1), the 

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

## k049: problem 13661 (Life & Death - Advanced 340)

- Leaf: B18 B17 C18 A18 A16 F18 G18 E18 F19 B19 C19 E19 D16 C17 A19 A17
- Tree S[10kKO]; research verdict solver_error; right S[10kKO]
- Merged checker: label A+2KO-2; class KO, BENT4 not ruled out (bent-four test undecided at 400,269 nodes); strict A+2KO-4; net None; count 2 (decided True); d_conv 0; 13.8 s, 6603443 nodes
- Checker text: A+2KO-2 (class KO, BENT4 not ruled out (bent-four test undecided at 2,000,269 nodes)): Black takes first at A16 after the preparation B G19, W A15, B A14, W pass. Black's 1 round are stages, not approaches (the held-ko check fails), so they count as threats. White needs two approaches (B19) before the ko is direct. After them, the direct ko is KO-2: Black needs 2 threats more than White, and White has a local threat in two forms (D18 answered at A19, D19 answered at A19), which buys a free retake; 1 extra threat not located. Label count: Black wins with as many threats as White. Full count 2: the count is two threats better for White than the label (not located). Strict label A+2KO-4. Undecided: bent-four (2000269 nodes), count (2000002 nodes).
- Parts: first take {"side": "B", "point": "A16", "how": "line", "preparation": [["B", "G19"], ["W", "A15"], ["B", "A14"], ["W", "pass"]], "investment": null}; approaches {"S": 1, "O": 2}; free moves null; local threats [{"side": "W", "points": ["D18", "D19"], "answers": {"D18": "A19", "D19": "A19"}, "units": 1, "d_before": 2, "d_after": 1, "forms_undecided": [], "wall": [], "answer": "A19", "line": null}]; stages [{"side": "S", "rounds": 1, "moves": [[3, 1], [3, 0]], "second_ko_capture": null, "kind": "held-ko check"}]; gain {"side": "B", "total": -2, "located": 0, "unlocated": -2}; second ko null; alternative null
- Warnings: ["bent-four test undecided", "a first move of the side to move has an undecided label", "approach line: round 2: the holder does not win R_2 at the direct-ko value"]
- Undecided parts: [{"part": "bent-four", "search": "solve bentfour", "nodes": 2000269, "area": "narrow"}, {"part": "count", "search": "B to move s=2 o=1 end=2,pv=1", "nodes": 2000002, "area": "narrow"}]
- Analyst (solver_error, says 10kKO): Leaf 5 is the owner's ten-thousand-year ko. The main ko is A19/B19, which Black holds, and neither capture threatens until someone fills a shared liberty at D18 or D19; whoever fills hands the other side a free capture that threatens at once (Black's one approach and both switch tests are decided). The checker's A+2KO-2 picks the wrong ko (A15/A16, which only exists after White captures A16) because the leaf has no ban, then counts White's capture at B19 as an approach; its label comes from the narrow area, its own full count (Black needs two threats more than White) contradicts the label's count of zero, and besogo would rank it above the tree's KO+ at F18, making 5.A16 the top move. Keep S[10kKO]: the one part left open, whether White's capture also needs the fill (undecided at 2,000,000 nodes), only chooses between 10kKO and A-1KO+/-, which besogo ranks equally in a KILL problem.
- Key line from the leaf: D19 B19 (pass D18 takes the corner) / pass B19 pass D19 A19 (pass D18 takes White)
- Variations: 1. Black fills: D19 B19 (White captures at B19 for free; Black's corner is in atari at D18), then pass D18 takes the corner and White lives: Black must answer with A19! after a threat. Same with D18 B19 pass D19.
2. White fills: pass B19 pass D19 A19 (Black retakes for free; White is in atari at D18), then pass D18 captures White.
3. White connecting the ko is a mistake that fills its own eye: pass B19 pass A19, then (narrow, Black with 4 threats) A15 G19 F19! pass G19 pass D18 D19 C18: Black gives up the corner and kills the eye shape; Black wins.
4. With White's two extra liberties removed (A15 saves A16, G19 saves F19): A15 pass G19 pass D18 B19 A19! pass D19: Black needs exactly one threat (fails with 0, wins with 1).
5. Narrow count line, Black with two threats more: A15 G19 F19! pass G19 B19 pass D18 A19 pass D19 (White's fill at D18 hands Black the free retake). This line exists only in the narrow area, where White's G19 capture is an immediate ko.
6. The checker's principal line (narrow): G19 A15 A14 pass A16: its first take at A16 is in the second ko made by White's capture A15, not the leaf's ko.
- Searches: Label rerun: evidence.py 13661 "B18 B17 C18 A18 A16 F18 G18 E18 F19 B19 C19 E19 D16 C17 A19 A17" --label, budget 400000, KO_BUDGET_FACTOR=1 (the checker's own retry at 2M and one-ply expansion): reproduces A+2KO-2, strict A+2KO-4, from the narrow area. Wide: label A?KO?, class undecided (8,000,004 nodes), every wide count undecided (12.8M-14.4M nodes per expanded search). Narrow full count 2. Narrow modes at the leaf: A=N, D=K (3,436 nodes). Middle model (narrow plus G19, H19, A14, so White's G19 stone has its two real liberties): D=K, A undecided at 2M. Counts at the leaf: narrow, Black fails at s-o=1 (5,934 nodes) and wins at s-o=2 (2,054); middle model, s-o=1/2/3 undecided at 2M. Probes on the A19/B19 ko (P_S' = leaf + pass; P_O = leaf + pass B19), all at 400k with one retry at 2M. Black's side: one forced White pass, Black 0 threats vs 4: narrow fails (9,652), middle undecided at 2M. Two forced White passes: Black wins in both areas (4 nodes: D18, D19). Held-ko check (White barred from B19): Black wins (narrow 124, middle 420). White's side: one forced Black pass, Black 4 threats vs 0: undecided at 2M in both areas. Two forced Black passes: White wins (narrow 108, middle 154). Held-ko check (Black barred from A19): White wins (narrow 45, middle 757). Breakdown of White's one-pass test (narrow): after the connection A19 Black wins (725 nodes); after D19, D18, A15, G19 or pass, undecided at 2M. Switch tests: leaf + D19 B19 (also D18 B19), one forced Black pass, Black 4 threats: Black fails (narrow 52, middle 91). Leaf + pass B19 pass D19 A19, one forced White pass, White 4 threats: Black wins (2 nodes, both areas). Base, with both of White's extra liberties removed (leaf + A15 pass G19 pass): middle fails at 0 (2,255) and wins at 1 (21); narrow 0/1 likewise (191/11); the same position with White to move, middle wins at 1 (1,888). Leaf + G19 pass: middle fails at 0 (7,428), wins at 1. Leaf + A15 pass: wins at 1, s-o=0 undecided. After G19 A15 (White captures A16): narrow fails at 1 (4,758), wins at 2 (5,587); middle undecided at 1/2/3. Leaf 1 (context): Black fails at s-o=-1 in both areas, s-o=0 undecided. Files: /tmp/claude-0/proto/research/kores/work/13661/k049_label.txt, out_*.txt, probe.py.
- Wrong part of the label (analyst): First take, and the approach counts and area that follow from it. The leaf has no ban: White's A17 saved A18 after Black's ko capture at A19. So D2 took the first ko capture on the narrow principal line (G19 A15 A14 pass A16), a second ko that only exists after White captures A16, instead of the leaf's own ko A19/B19, which Black holds. In that reading the checker made two errors. It counted White's capture at B19 in the main ko as an approach (n_O=2: B19 and D18/D19). It also turned Black's one approach (D18/D19, which it found too) into a stage, because the held-ko check fails while White can still capture at B19 in the other ko. On the A19/B19 ko both held-ko checks pass, Black needs one approach (decided in narrow), White at most one, and both switch tests are 'yes', so D9 gives 10kKO. The label also comes from the narrow area (wide: A?KO?), where H19 is outside the area, so White's capture at G19 is an immediate ko instead of a stone with two liberties. Its label count 0 disagrees with its full count 2 by two unlocated threats (design risk 2, an approach played with tempo).
- Reviewer 1 (solver, upholds): I ran my own searches in one fresh engine process: narrow area unless noted, 400k nodes per search, one retry at 2M, end rule 2 as in the checker. Files are in /tmp/claude-0/proto/research/kores/work/k049-refute-c8255/ (rp.py, expand.py, o*.txt).

1. Can the checker's A+2KO-2 be right? No.
- Count at the leaf: Black fails at s-o=0 (409,691 nodes) and at s-o=1 (6,157), and wins at s-o=2 (2,088). So Black needs 2 threats more than White, and the label's count of 0 cannot be right.
- Black's side of the A19/B19 ko: with White forced to pass once Black fails (409,653); with two passes Black wins (4, D18 then D19). So n_S=1. The held-ko check (White barred from B19) wins for Black (124 nodes), so this is an approach, not a stage.
- White's side: after White captures at B19 and Black passes twice, White wins (108). So White needs at most one approach. The checker counted White's own capture at B19 as one of its approaches.
- The checker's first take at A16 is in a ko that does not exist at the leaf. It only appears after White captures at A15 and Black plays A14.
- No A+ label is possible: it needs Black's hold on the ko to threaten at once, and the one-pass test above fails.

2. Is the tree's 10kKO wrong? I settled the analyst's open part in the narrow area.
White captures at B19, Black passes (s=4, o=0), and White has six free moves. Every one loses:
- A15: Black G19 wins (2,029 nodes).
- pass: Black G19 wins (6,092).
- A19: Black wins (500).
- D18: Black wins even at s=2 (19,379).
- D19: Black retakes at A19. Then White A15 loses at s=2 (7,699), White G19 loses to Black G17 at s=2 (428,931), and White pass loses (1).
- G19 (a ko capture in this area): Black F19! with a threat. Then White A15 (12,598), A19 (646) and pass (419,387) lose at s=1, and D18 or D19 return to the positions above.
So White's capture also needs the fill: n_O=1, a mutual approach. Both held-ko checks pass (124; 45). Both switch tests are yes: after D19 B19 (or D18 B19) with one forced Black pass, Black fails (52); after pass B19 pass D19 A19 with one forced White pass, Black wins (2). By D9 that is 10kKO in the checker's own area, which matches the tree.

Middle model (narrow plus G19, H19, A14, so White's G19 stone has two liberties):
- These agree with the narrow area: switch tests yes (91 and 2 nodes), two-pass tests (Black wins 4, White wins 154), held-ko checks (432 and 899). Modes are A undecided, D=K.
- Both one-pass tests stay undecided at 2M. All eight White free moves are undecided at s=2 and s=4, also two plies deep and with Black's natural reply fixed. The only one decided is White A19, which loses to Black G19 (409,111).
- n_S=1 still holds there: the test is the leaf count at s-o=-4, and the real board only adds White resources.
- So the only label left besides 10kKO is A-1KO+ or A-1KO-, which besogo ranks equal to 10kKO in a KILL problem. Grading is the same either way: 5.F18 (KO+) stays the top move. With the checker's label, 5.A16 would wrongly become top.

3. 
