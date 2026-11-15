# Tsumego checker (research prototype)

Checks the site's problems with an exact local life-and-death solver: correct moves that fail, wrong moves that work,
refutations that don't refute, missing correct moves, and authored statuses (`G[]` / `S[]`) against the solved ones.

Research code, not used by the site. The plan for real integration (review queue, editor check) is in the report.

## Build and run

```sh
g++ -O2 -march=native -o engine engine.cpp
sh export-problems.sh DBNAME -u USER -p        # one JSON line per problem, read-only
gunzip problems.jsonl.gz
# full check, one worker: <budget> <problems> <worker> <workers> <limit>
python3 check_tree.py site 100000 problems.jsonl 0 1 999999 > check.0.jsonl
# status problems only: every authored S[] leaf against the solver
python3 status_eval.py 200000 problems.jsonl 0 1 > status.0.jsonl
python3 status_summary.py 'status.*.jsonl'
python3 site_summary.py check v
```

`PROBLEMS=problems.jsonl` tells the inspection scripts (`line.py`, `moves.py`, `recheck.py`, `checklist.py`) where
the export is; `RESULTS` and `REPORT_DIR` do the same for `build_report.py`.

## Files

- `engine.cpp`: the solver. df-pn proof search with Zobrist hashing, two ko modes (unlimited threats for either side)
  plus a plain one, Benson's unconditional life, a "no two eyes possible" test, a dynamic fringe around the region,
  the end-of-game and repetition rules for kos, bent four in the corner, and clean solving (no ko capture by the
  solver's side). Reads commands on stdin, one answer line each (the protocol is at the top of the file):
  - `problem`, `solve` (modes A and D, plus `cycles=`, the repetitions the cycle rule decided), `solvecount` (counted
    threats), `solveplain`.
  - `set <name> <value>`, switches that stay until changed: `cycle 0..3` (repetitions: 0 a cycle with a ban break
    kills, else seki; 1 kill; 2 live; 3 the side completing it loses), `kofree B|W|0`, `bentfour 0|1`, `sekikill 0|1`
    (at the end only unconditional life lives), `endplay 0|1|2` (counted searches: two passes do not end the game
    while a defender chain in the target's area is in atari or the side to move can take a ko; 1 the attacker moves,
    2 the side to move keeps the move), `pv 0|1` (`solve` prints `pvA=` and `pvD=`). `kofree`, `bentfour` and `cycle`
    also work alone.
  - `solvex <toMove> <ko> <budget> <black> <white> <options|-> <board>`: the counted search with `pb=k`/`pw=k`
    (that colour passes its next k turns; a forced pass is a tenuki: it lifts the ko ban and breaks a run of passes,
    so it never ends the game), `ps=1` (the last move was a pass), `fb=i;j`/`fw=` (barred points), `kb=`/`kw=`
    (barred while that colour is under a ko ban) and `end=`, `cyc=`, `sk=`, `pv=` (the switches, for this command
    only). The static "no two eyes" test counts the forced passes owed and the barred points. Prints
    `result N=K|N|U nodes= best= cycles= [pv=]`; a line is `B<i>`, `W<i>`, `Bpass` joined by `_`, with `!` after a
    ban break, `fp` for a forced pass and `cycle` where a position repeats. `solvecount` is `solvex` with no options
    and keeps its old output.
  - `solvemoves`, with the arguments of `solvex`: every legal root move and the pass searched in one table generation,
    each with the full budget. Prints `result N= nodes= best= cycles= moves=<i>[!]:<K|N|U>:<nodes>,...` (-1 the pass,
    `!` a retake against the ban).
  - `keep <indices|->`: attacker stones whose capture means the defender lives (hand-built model boards).
  A malformed `solvex`, `solvemoves`, `set` or `keep`, or an unknown command, answers `error <reason>`.
- `tsumego.py`: SGF parsing, go rules, the problem region, and the bridge to the engine (statuses DEAD, KO, BENT4,
  ALIVE).
- `check_tree.py`: the checker for one problem tree, and `site` mode over an export. Every finding is solved again
  over a wider region with a bigger budget and kept only if that agrees.
- `status_eval.py`, `status_summary.py`: accuracy on the status problems, leaf by leaf.
- `first_moves.py`: the first-move check used on public collections (sanderland/tsumego).
- `line.py`, `moves.py`, `recheck.py`: inspection of single problems.
- `checklist.py`, `render_checklist.py`: the review checklist, as a table and as a page of go diagrams.
- `gen_trees.py`, `gen_status.py`, `inject_eval.py`: trees with planted errors, to test the pipeline.
- `build_report.py`, `report_body.html`, `report_style.html`: the research report.
- `export-problems.sh`: the read-only export of the current version of every problem.
- `probe_leaf.py`, `tally_status.py`: one leaf's verdict as `status_eval.py` computes it; the tallies of a status run
  (disagreement pairs, ko signs).
- `status_examples.py`, `status_examples_style.html`: the review page of the status leaves the solver disagrees with.
- `evidence.py`: everything about one position of a status problem, for checking a disagreement by hand: the tree's
  leaves, the boards with the search area, the solver's verdict in both areas, a one-move table, ko tables.

## Disagreement research

Every leaf where the solver and the tree disagree, checked by an analyst agent that reads the position with the
solver as a calculator, then by reviewers who try to refute each verdict (`research/BRIEF.md`, `research/workflow.js`).

```sh
python3 research/make_cases.py 'status.*.jsonl' problems.jsonl cases.json
PROBLEMS=problems.jsonl python3 research/precompute.py cases.json evidence 0 1
python3 research/merge_settled.py results.json judges.json facts.json > merged.json
PROBLEMS=problems.jsonl python3 research/impact_scenarios.py merged.json scenarios.json research/topics.json
node research/grading_impact.cjs scenarios.json impact.json
PROBLEMS=problems.jsonl python3 research/render_research.py merged.json research.html impact.json cases.json research/topics.json
```

`research/results/` keeps the run of 14 November: `cases.json` (the 125 leaves), `results.json` (every verdict with
the analyses, reviews, judges, fact checks and grading checks) and `impact.json` (the grading effect of each fix).

The report shows every line as a numbered diagram. Writers turn a per-problem dossier into a spec of diagrams and
short text (`research/report_workflow.js`), checked by `report.py check`, which replays every diagram, rejects move
lists in the text and checks that every stone the text names ("Black 7") is in the diagrams; `report.py render`
builds the page from the specs in `research/report_specs/`:

```sh
PROBLEMS=problems.jsonl python3 research/report_dossier.py research/results/impact.json dossiers
PROBLEMS=problems.jsonl python3 research/report.py check research/report_specs/*.json
PROBLEMS=problems.jsonl python3 research/report.py render research/report_specs report.html
```

`research/fixed.json` lists the leaves already fixed on the site ({case: {status, date}}); the report marks their
cards and counts them.

`research/topics.json` holds the decisions several leaves share (bent four, a problem's target, an open edge): each
topic's text, its leaves and the labels to choose from, which the page shows first.

Cases the reviewers split on go to two independent judges, and each definition question to a fact checker
(`research/settle.js`, which reads each case's views from `settle/<case>.a.txt`, `.b.txt` or `.facts.txt`);
`merge_settled.py` folds their verdicts into the results.

The page lists the questions to decide first, then the clear errors on the site, each with the moves the site would grade
differently after the fix (`impact_scenarios.py` proposes each fix alone and all of a problem's fixes together); with
the cases file, the leaves not checked yet are listed as still under review.

`research/grading_impact.cjs` runs besogo's own grading (`webroot/besogo/tests.html` in a headless browser) on a tree
before and after new S[] values, and lists the moves whose correct/wrong mark changes:
`node research/grading_impact.cjs scenarios.json impact.json`, with scenarios `[{key, sgf, fixes: [{line, status}]}]`.

`research/explain.py` draws a page that explains disputed ko leaves move by move from a spec (`research/explain/9.json`
for problem 9). It checks every move for legality and every step against the solver, and stops on any mismatch.

## Full ko labels (in progress)

`research/ko_labels/design.md` is the design for computing besogo's full ko labels (approaches, who takes first,
local threats, 10kKO, double ko, superko, BENT4, SEKI) from small engine searches instead of one threat count.
`research/ko_labels/prototypes/` holds the prototype engines (diffs against `engine.cpp`) and scripts it was built
from, `research/ko_labels/judge/` the runs that verified its outputs on the settled leaves. The engine part (the
design's section 5) is in `engine.cpp`; the label's counted searches use the end playout (`END_PLAY`), the old
commands keep the old end until the regression run of section 12.

The core of the label is on `Solver` in `tsumego.py` (sections 3, 4 steps 1-5, 6 and 9): `count` (the counted game,
with forced passes, barred points, the cycle rule and the end playout, escalated at 5x the budget and then by one ply),
`threshold` (galloping, bisection, the shifted check), `first_take`, `profile` (approach count, held-ko check, the
direct-ko value), `ko_label` (the record of section 7.1 in both areas) and the class step `classify` behind `status`
(cycle rules for double ko and superko, the clean result, the fixed bent-four test, SEKI and SEKI+). The counted
searches of the label use `END_PLAY` (default 2, the side to move keeps the move; 1 the attacker moves on).
`besogo_string`, `parse_status`, `label_threshold` and `better` mirror `status.js`.

The rest of the label (D7, D8, steps 6-9 and section 7) is `explain_label`, which `ko_label` runs on the area the label
comes from. `local_threats` bars the candidate moves of the side with the extra threats cumulatively, while it is under a
ban only, and counts each unit change of the count as one local threat; the points barred for it are its forms. Extra
threats no bar explains are read as stages on the direct-ko line. `approach_rounds` builds the conventional approach line
(the other side plays elsewhere, approach, free retake, retake after a threat) and `round_gains` measures, round by round,
what the other side's local move instead of its tenuki would gain: the located gains, the unlocated rest of the full
count, and the strict label (from the parts when the full count is undecided). `counted_line` builds the lines of 7.2
(approach, direct ko, gain and its no-gain twin; the full count's line when it beats the label, for a mutual approach,
or when no other line can be built) from decided positions, with each move's role; each line carries a
`research/report.py` diagram spec (`start`, `moves`, `first`, `caption`).
A holder's free move that fills its own ko is no approach: the label then counts the second ko that follows (215 and
13169: a bent four) and prints the A-form with the filling point barred beside it (design D5, question 5). An
approach round whose free retake loses outright is reported as failing (a sente approach, D8).
`label_text` writes one sentence per part, also for a partly undecided label, `compare_label` the comparison of 7.3
(on the decided parts of a partly undecided label), and `label_summary` the fields
`check_tree.py` and `status_eval.py` add to KO leaves (`KO_LABEL=0` turns them off). `evidence.py --label` prints the
whole report.

## Ko label questions page

`research/questions.py` renders the page of questions for the owner about ko labels (10kKO against a plain KO and
approach kos, and three single leaves): each position is drawn as numbered diagrams, and every claim under a diagram is a
solver check that runs when the page is built; a failed or undecided check, an illegal move or a move list in the text
stops the build. The page spec is `research/questions/page.json` with one file per section; the checks use the
checker's engine (`solvex` with forced passes, barred points and the end playout) and the class of `Solver.status`,
SEKI and SEKI+ included:

```
PROBLEMS=status_problems.jsonl ENGINE=engine python3 research/questions.py research/questions/page.json out.html [--only <section id>]
```

`research/questions/inventory/` holds the facts behind question 1: every 10kKO leaf of the export and the owner's
approach-form leaves where both sides seem to need an approach, with each side's forced-pass count, the investment, the
switch test and the searches behind them (`inventory.md` summarises them).
