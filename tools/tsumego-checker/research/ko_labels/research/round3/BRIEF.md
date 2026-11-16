# Brief: the full ko labels against the owner's S[] (16 November 2026, third round)

tsumego.com status problems carry G[KILL] or G[LIVE] on the root and a besogo status S[] on every leaf of the solution
tree (webroot/besogo/js/status.js in /home/user/tsumego-hero). The checker (an exact local df-pn solver) now computes the
**full besogo ko label** of a leaf: who took first, how many approach moves each side needs, the direct ko after them,
local threats, 10kKO, double ko, bent four, seki. The second round checked 57 leaves where it disagreed with the tree
and its fixes are merged. The leaves in this round's dossiers still disagree with the tree's S[] after those fixes, and
nobody has checked them yet. For each such leaf, find out **who is right
and why**, reading the position like a strong go player; the solver is a calculator you can use, not the judge.

## Statuses (besogo), always from the solver's side

The problem's first player is "the solver" (Black in most problems; when White plays first, signs are from White's side).

- ALIVE, DEAD; SEKI (seki in gote), SEKI+ (seki in which the solver keeps sente); BENT4 (bent four in the corner, dead
  under Japanese rules); DDKO / ADKO (dead / alive in double ko); DSKO / ASKO (superko); 10kKO (ten-thousand-year ko).
- Kos, with s and o the outside ko threats of the solver and the opponent:
  - KO+: the solver takes first, wins when s >= o. KO-: the opponent takes first, solver wins when s >= o + 1.
  - KO+n (n >= 2): solver wins when s >= o - (n-1). KO-n: solver wins when s >= o + n. A local move the other side must
    answer works like a ko threat; a step ko (win it twice) counts as extra threats.
  - A+nKO..., A-nKO...: approach kos. A+n: the opponent needs n approach moves before its ko is direct; A-n: the solver
    needs n. The KO part after it says who takes first and the extra threats. besogo ranks approaches before threats.
  - The count a label implies (threats the solver needs beyond the opponent's, each approach costing one tempo):
    T = -(a + e) with a the signed approaches and e the extra threats (KO+ 0, KO- 1, KO+2 -1, KO-2 2, A-1KO+ 1,
    A-1KO- 2, A+1KO+ -1). Labels with equal T can still rank differently in besogo, because approaches come first.
- besogo ranks 10kKO equal to the attacker's approach form (A-1 in a KILL problem, A+1 in a LIVE one), and no other.

## The owner's definitions and decisions (verbatim where quoted)

- Approach ko: "in approach ko it isn't symmetrical, one side needs to do approaches to start a normal ko, while the
  other side can win by winning one ko at any time."
- 10kKO: "one side needs to do investment, which is then 'wasted' to start a ko no matter who started." In his trees:
  both sides need the same move (a liberty both groups share); whoever plays it hands the other side a direct ko.
- A ko where "both white and black need extra approach, and it can switch there and back": "I would rate it as normal
  KO" (the plain KO sign from the count). Confirmed on 16 November on 10403: after Black's one approach the ko switches
  there and back, "since we call symmetrical kos like this just normal ko, it is indeed one move approach ko for
  black" (S[A-1KO-]).
- Question 1a, answered 15 November: the shared-liberty shape (both sides need the same move at a liberty their groups
  share, and whoever plays it hands the other side a direct ko; 10391) is his 10kKO, "a clear example of 10kKO". Free
  fills of outside liberties or a free extra move before the shape do not change it (11280, 11282, 13541, 13549).
- Approach rounds (c121, his S[A-1KO-]): when one side ignores the approach and the other retakes, "that is implied in
  the approach itself": what the opponent gains with the free move of an approach round belongs to the approach, not to
  the label's threats. The checker prints that gain beside the label and the strict label without the rule.
- Questions still open with him (the question page, https://claude.ai/artifact/1EYiF4TGFP2WMAFoPtoXXW, and design.md
  section 13): 1b switching ko next to a second ko (7487 leaf 98); 2 c081 KO+ or 10kKO (the 1a shape with the colours
  reversed, so 10kKO unless he says otherwise); 3 c014 KO- or A-1KO+
  (an approach after a local answer to a threatening capture); 4 10005 label by structure or by count; 5 throw-in at the
  ko point that makes a second ko (215, 13169): count it or describe the clean kill; 6 SEKI+; 7 15678's target; 8 open
  board edges; 9 equal counts at the leaf; 10 superko. **A leaf whose verdict turns on one of these is a definition
  case: say which question and what each answer gives.** The approach form against the counted form with the same count
  (A+1KO+ against KO+2) is the research report's open approach-label decision, also a definition case.

## The checker

- Run it from /tmp/claude-0/proto/research/kores3/wt/tools/tsumego-checker (do not modify anything there):
  `PROBLEMS=/tmp/claude-0/dump2/status_problems.jsonl python3 evidence.py <id> "<moves from the start>" --label`
  prints the class, the label with every part and its reason, the counted table, the lines, both areas, warnings and the
  undecided parts. `--ko` prints the counted-threat table; `--pv` the solver's lines. Python: tsumego.Solver (status,
  status_record, count, threshold, first_take, profile, ko_label); see evidence.py for the setup.
- It reads only inside its search area (the narrow area: the stones, the tree's points and one ring; the wider area adds
  two rings). Outside it the board counts as the attacker's wall: a group that runs out does not escape, an edge stone
  has fewer liberties. Results near the wall can be artifacts; the label records wall warnings.
- Undecided searches show as "?" or "undecided"; an undecided refinement test leaves statuses open ("KO, BENT4 not ruled
  out"). Never treat an undecided search as a result.
- Known weak points of the label (design.md, section 11 and the addendum): sente approaches (the forced-pass search can
  count an approach the approaching side plays with tempo, so the approach count can exceed what the count shows, as in
  some capturing races); a second ko or a throw-in; positions whose class the solver cannot decide; the first take when
  the principal line is ambiguous.

## What to return per case (the schema enforces it)

verdict: site_error (the tree's S[] is wrong; give the right one), solver_error (the tree is right and the checker's
label is wrong; say which part of the label is wrong and why), definition (it turns on an open question: name it and
what each answer gives), unclear (you could not settle it: say what is missing). correct_status in besogo notation.
Where the dossier gives an earlier verdict on the same leaf, decide whether the new label or the settled status is
right, and say plainly if the earlier research was wrong. The second round's verdicts on the problem's other leaves are
at the end of each dossier: use them as context, and say so where your leaf contradicts one. Give the key variations as move lists (they go into diagrams later), the solver checks you ran, and
two or three plain sentences for the owner.
