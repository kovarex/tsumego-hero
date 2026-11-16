# Brief: fix the checker faults the ko-label research confirmed

## The checker

tsumego.com status problems carry G[KILL] or G[LIVE] and a besogo status S[] on every leaf. The checker in
/home/user/tsumego-hero/tools/tsumego-checker (git branch tsumego-checker) is an exact local df-pn solver
(engine.cpp, driven by tsumego.py) that computes the **full besogo ko label** of a leaf: Solver.ko_label and the
functions it calls (first_take D2, profile D3-D5, approach_alternatives D3, ko_fill D5, label_parts D6, local_threats
D7, approach_rounds and round_gains D8, the class and double-ko tests D10). Read its design first:
research/ko_labels/design.md (section 0, the definitions D1-D13 in section 3, the algorithm in section 4, the risks in
section 11, the open questions in section 13 and the addendum). The besogo statuses, the owner's definitions (approach
ko, 10kKO, the switching plain KO, the approach-round rule) and his open questions are in
/tmp/claude-0/proto/research/kores/BRIEF.md. A fix must never silently decide one of the owner's open questions: where
a leaf's label turns on one, the checker keeps the question page's proposal and says so.

## The faults

The ko-label research compared the checker's label with the tree's S[] on 57 leaves; an analyst and reviewers decided
who is right. Fixes for four groups of faults are merged (tsumego-checker e019960d: a capture in a second ko is no
approach, D4; the double-ko test's rule 2, D10; a holder's investment is no preparation and the attacker's free moves,
D2 and D9); the search-area group is still being designed in other worktrees: leave the area code alone. The
recheck of all 127 disagreements with the merged checker left the faults in /tmp/claude-0/proto/research/kofix3/cases.md;
two of them are yours. The leaves where the tree is right and the checker wrong are grouped by cause in
/tmp/claude-0/proto/research/kofix3/group-<E1|E2>.md, each with the analyst's and reviewers' reading, the key lines,
the searches and the part of the label they found wrong.

## Working

- You work in your own git worktree of the repository. Build the engine there before anything else:
  cd tools/tsumego-checker && g++ -O2 -march=native -o engine engine.cpp
  and run everything from that directory, so your build and your code are the ones used.
- One leaf: PROBLEMS=/tmp/claude-0/dump2/status_problems.jsonl python3 evidence.py <id> "<moves from the start>" --label
  (also --ko, --pv, --tree, --budget=N). Python access: see evidence.py for the setup of a Solver.
- Many leaves: research/ko_labels/validate.py 200000 /tmp/claude-0/dump2/status_problems.jsonl 0 1 <out.jsonl> --narrow
  --limit=300 --keys=<file with one "<id><TAB><moves>" per line>. One JSON line per leaf; it skips leaves already in
  <out.jsonl>. A run of about 100 leaves takes 20 to 40 minutes: start it in the background (nohup ... &) and poll the
  output file; never leave it running when you finish.
- The baseline is the merged checker's labels on your regression list, already computed with the same settings:
  /tmp/claude-0/proto/research/kofix3/base-recheck.jsonl and base-run.jsonl (validate.py records; base-run.jsonl may still
  be growing when you start; a leaf missing from both you run yourself in an unchanged worktree). The unchanged checker
  is also at /tmp/claude-0/proto/research/kores3/wt/tools/tsumego-checker (read-only for you). The regression list of
  your group: /tmp/claude-0/proto/research/kofix3/regress-<group>.txt. research/ko_labels/validation/labels.json is
  older (before the merged fixes): do not use it as the baseline.
- The machine has 4 cores shared with other agents: run at most one engine process at a time (validate.py runs one).
- Code style of the repository: compact '#text' comments in Python and '// text' in C++, full-word names, no docblocks
  that repeat a signature. Keep the design document in step: describe a changed rule where the rule is defined.
- Never touch production or its database, never trigger GitHub workflows, never push, never post anything. Commit only
  in your own worktree. Never present an undecided search as a result.
