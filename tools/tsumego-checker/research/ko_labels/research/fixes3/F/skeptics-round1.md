# The skeptics of the first fix (bcdf57cd), verbatim

## verify:logic (holds: False)

The fix holds on what the implementer checked: the narrow area, which is the only area validate.py --narrow uses. It breaks on the owner's own switching example as soon as the checker runs both areas, as check_tree.py does (add_wide at line 235), and as status_eval, evidence.py and validate.py without --narrow do. Line numbers refer to tools/tsumego-checker/tsumego.py at bcdf57cd.

1. Wrong decided label on 10403, the owner's switching example, with every search decided.
   - Leaf: 10403 "B18 A18 C19 A16 A19", run with validate.py without --narrow at the default KO_BUDGET_FACTOR.
   - Result: the label is A-2KO+, with areas {'wide': 'A-2KO+', 'narrow': 'A-1KO-'}, count 3 and the warning "the narrow area gives A-1KO-".
   - A-2KO+ is exactly the label the owner's 16 November answer rejected (D4 and section 13 'Answered'). The owner's label is A-1KO-.
   - What the wide-area trace shows:
     - d_full = 3. The text itself names White's G18 as an approach-round gain "Left out by the approach rule".
     - n_S = 2 and v = 0. A17, the second-ko capture, is a decided candidate (True), so the switching reading opens.
     - v' after one forced White pass is 1. The A-1 form therefore counts 2, which is not 3, so line 1733 `continue`s.
     - The two approaches then stand, although A-2KO+ also counts 2, not 3.
     - ko_label (line 2243) prefers the wider area's decided label.
   - Root cause: the switching branch tests only the (n-1)-form against d_full and never the n-form. Under D8 every A-form's count leaves out the approach-round gains, so any gain of Y blocks the switch, and the n approaches are printed as a decided label.
   - A gain-aware test would give the owner's A-1KO- here, because v' = 1.
   - 11279 k109 is the same mechanism in the narrow area: both forms count 3 against 4, and round 4 calls the printed A-2KO- wrong. The commit lists k109 as open, but not that the same rule also breaks 10403. The implementer's 10403 check was narrow-only.
   - Evidence: /tmp/claude-0/-home-user-tsumego-hero/c8255bcd-8912-571f-8c57-f77282a7b25e/scratchpad/sk-rule/wide-1.jsonl and /tmp/claude-0/-home-user-tsumego-hero/c8255bcd-8912-571f-8c57-f77282a7b25e/scratchpad/sk-rule/tw-10403.txt (script trace_wide.py in the same folder).

2. An undecided full count still gives a decided label.
   - Code: second_ko_readings returns at once when the full count is undecided (line 1682: `if d_full is None or take['side'] is None: return`) and marks nothing unsettled.
   - D4 says each reading "needs d_full", and that where a reading cannot be decided the approach count stays undecided.
   - For 'answered' this does no harm: the stage needs d_full for its threats, so its label is already KO?.
   - A switching profile, though, keeps its decided n-approach A-form, whose threats come from v alone.
   - Shown on 10403 in both areas with KO_BUDGET_FACTOR=1, the D4 reviewer's own setting:
     - The wide area's count stays undecided (2,921,699 nodes), so the reading never runs there.
     - The wide label A-2KO+ is decided and printed as the leaf's label.
     - The printed "Full count 2" comes from the narrow area. At that same count the narrow area's switching reading proves A-1KO-.
   - Evidence: /tmp/claude-0/-home-user-tsumego-hero/c8255bcd-8912-571f-8c57-f77282a7b25e/scratchpad/sk-rule/tw-10403-f1.txt

3. The switching reading prints its label even when no real approach is found (code path only, no leaf shows it).
   - Code: line 1748, `approach = found[1] if found is not None and found[0] == record['d'] else None`. The A-(n-1) label is printed whatever this gives.
   - If no real approach (not a pass, not a fill, not a second-ko capture) reaches v', or free_move_count's table is undecided, the n-1 "approaches" rest on the very capture D4 says is no approach. The label is still decided.
   - The 'answered' branch treats the same situation as reason 'count', i.e. undecided.
   - The data comes close: after one forced White pass in 10403, the capture A17 ties the approach E19 at balance 1 (/tmp/claude-0/-home-user-tsumego-hero/c8255bcd-8912-571f-8c57-f77282a7b25e/scratchpad/sk-rule/vt-10403.txt). In 11279, A17 ties F19 and A14 at balance 2.

4. Minor: counts beyond the limit are reported as undecided.
   - A v' beyond the limit gets reason 'v', and a count of P_X beyond the limit is listed as "the count itself" undecided. D5 says a count decided beyond the limit is a bound, never undecided.
   - A v' beyond the limit cannot count d_full, so the n approaches should stand decided; the text calls it undecided instead.

What held:
- The opponent's side of the 'answered' reading: on 1731 "B18 A18 A17 pass A19 A18!" C18 is read as quiet and the label is KO-, the research's plain KO-. On 13547 "...A18 A19! A13" all four replies are quiet and the label is KO-.
- The sign and side logic of answer_reading, x, bracket_labels and D12 for the opponent, LIVE goals and White-first problems.
- Readings fired nowhere they should not on 48 further leaves in the narrow area (21 second-ko leaves from labels.json and 27 one-sided approach leaves).
- Probing one to three count-keeping moves around 1731, 7693, k043 and 10403 gave consistent labels.
- In both areas, 1731 is KO-2, 7693 is KO+ and k043 is A-1KO- (the wide area's count stays undecided, so the narrow label stands). 11279 timed out at 600 s.
- The threat tests in k043 did score repetitions (85 to 256 cycles), but they are attacker wins under rule 0, which D3 counts as exact. A quiet verdict for a defender X would not be exact, and the reading does not check this, unlike D3. This is theoretical; no leaf shows it.

Process note: for about 10 minutes I ran two both-area validate processes at once, each with a second engine for the wider area, so four engine processes. That broke the two-process limit. After that I ran at most two. 

## verify:labels (holds: True)

No label went wrong. The fix changed exactly two labels, and both now match the research's settled verdicts. Nothing else moved. The open points are below.

WHAT I RERAN (fresh processes, never more than two of mine at a time, worktree clean at bcdf57cd). Files are in /tmp/claude-0/-home-user-tsumego-hero/c8255bcd-8912-571f-8c57-f77282a7b25e/scratchpad/skeptic-d4/
- The 63 research leaves (r63-0.jsonl, r63-1.jsonl). My compare63.py output (compare63-mine.txt) is byte-identical to F/fixd4/research63-after.txt: 49 equal to the research, 5 decided-wrong. The 5 are k002, k006, k047, k054 and k055, all area or other old issues the readings don't touch.
- Against merge-r63, comparing every field, only these leaves change:
  - 1731 (k003): A-1KO- -> KO-2
  - 7693 (k016): A-?KO? -> KO+
  - k043: text and warnings now name E19/F19; the label is unchanged.
- r63, the five cases and regress-D4 reproduce the implementer's final runs exactly (same nodes, labels and text). The 15 round-4 leaves have the same nodes and labels as the implementer's after-run.
- The five cases: labels unchanged; only k043's text changed.
- The 15 round-4 leaves: no label changed. The one text change is in 13489 "E19 D18 A16 D19 B18 B19 A17 A18": D7 names A14 instead of C18 as the answer. The readings never run on that leaf, so this comes from search history.
- regress-D4: only 1731 changed.
- The 5 remaining D12 leaves (d12-rest.jsonl): identical to the implementer's after-d12.

THE TWO CHANGED LABELS, AGAINST THE RESEARCH
- 1731 "B18 A18 A17": now KO-2; research KO-2, high confidence.
  - The trace shows answer_reading threats [C18], answers [], so the stage stands. The text says White's local threat C18 is answered at C19, which is exactly the dossier's line (B17 A17! C18 C19 B17).
- 7693 "A16 G19 E19 D18 A18 C19 B19": now KO+; research KO+, high confidence.
  - The trace shows White's F19 and D19 are quiet and answer nothing, so the stage stands: both captures direct, no local threat. This matches the dossier: White's F19 or D19 removes Black's D19 threat, and C18 makes a switching plain ko.
- tracecheck.trace.jsonl also confirms the rest:
  - k043: answers F19/E19, quiet A13, A-1KO-.
  - 10403: switches at A17, A-1KO-.
  - 1807, 2089, 9947, 10005, 15341: the stage stands on form_count == d_full.

OUTSIDE THE LISTS. I traced 86 leaves with a wrapper that records each reading's inputs and outputs: regress-F's 8 second-ko leaves, plus 78 labels.json leaves with second-ko text, stages or approach-ko forms. Results are in sample-*.jsonl and batch2-*.jsonl, with their .trace.jsonl files.
- regress-F's 8 leaves (556, 7487, 13169, 13489, 15216, 15342, 15369, 15416) are identical to the merged run.
- 11 leaves timed out at 300 s under machine load 6 to 9. None of them reached the readings.
- No answered reading and no D12 bracket fired anywhere in the sample.
- The switching reading fired on 840 leaf 1, "B18 B19 A17 D19 A19":
  - Tree KO-, checker A-1KO- (count 2), switching at C19 with approach B17. merge-wt (58aaf59a) prints the same label (merged-check.jsonl), so this fix did not change it.
  - It looks plausible. In probe-840.txt, the outright tests give n=2. Two forced passes give v=0 (A-2KO+, count 2); one forced pass gives v=1 (A-1KO-, count 2). The full count is 2.
  - After the approach B17, C19 wins outright against one White pass (33 nodes); without the approach it does not (16,925 nodes). White's retake B19! needs two forced Black passes. This is 10403's shape, so A-1KO- follows the owner's 16 November rule.
  - But the count disagrees with the tree's KO- (1 against 2), and nobody has researched this leaf. 840 is also the problem whose narrow area the research faulted (k002). It belongs in the next research round.
- 11279 leaf 23, "B18 A18 A17 B17 C18 A16 A19 B19 C19 D19" (tree A-2KO+, checker A-2KO+, same in merge-wt):
  - The switching branch was entered (A17 is a decided capture) but changed nothing.
  - probe-23.txt: the switch form counts 2 (v=2), the two-approach form also counts 2 (v=1), and the full count is 3.

OPEN POINTS
1. 11279 k109 stays a decided A-2KO- (count 4); round 4 says A-1KO- (medium confidence, still running).
   - The fix did not change this leaf, and design.md now states that A-2KO- stands.
   - The checker's own probe finds 10403's switch shape, and the two forms tie: A-1KO-2 and A-2KO- both count 3 against a full count of 4.
   - So the label rests on a tie the checker cannot break, and no warning mentions it. Under the owner's 10403 rule the approach count should be 1.
   - Leaf 23 has the same tie, and there the tree agrees with A-2KO+.
   - I count this as an unsettled definition question, not a label the fix made wrong. A safer output would be A-?KO? with a warning naming the tie.
2. Latent, never hit in any run: reason 'count' covers free_move_count returning None because a move is undecided. counted_moves keeps no list, so that search is not listed. The text then reads "no approach ... counts the full count", stating something that was not decided. The label is A-?KO? in that case, so it is never wrong. 

