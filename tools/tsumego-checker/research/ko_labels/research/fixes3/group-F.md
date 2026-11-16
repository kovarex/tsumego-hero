# Group F: labels the checker leaves undecided where the research settled them

The research settled these five leaves, but the merged checker (tsumego-checker at 4537ef85) still leaves a part of
each label undecided at its budget (200,000 per search, escalation x4 and x10 where the code escalates, the 300 s
validate limit). The searches that stall: the full count (D2's d), the approach alternatives, the bent-four refinement
test, the switch test and D2's investment test. 13661 k049 is the 10kKO leaf that group C could not reach ("their
deciding searches stay undecided at the checker's budget"); 13547 k041 is its twin in another problem. The aim is the
research's label on these leaves within the leaf's time limit, by better search, not by a bigger budget everywhere:
what the stalled searches spend their nodes on (repetitions, the ko threats' bookkeeping, the area's fringe, move
ordering), and what would let them finish (a narrower position, a decided sub-result reused, a proof found from the
research's line, iterative widening, a cheaper equivalent test). A label may stay '?' where nothing decides it, but never
turn into a wrong decided label.

The research's own runs on these leaves are in its dossiers (/tmp/claude-0/proto/research/kores/dossiers/<problem>.md)
and in kofix3/cases.md below.


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
