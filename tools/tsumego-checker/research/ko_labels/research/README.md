# The ko-label research (15 November)

57 leaves where the checker's full ko label disagreed with the tree's S[] (or with the first research), checked by an
analyst per problem, reviewers, judges or fact checkers, and grading checks; published as the Ko Label Research report.

- `cases.json`, `BRIEF.md`: the cases and the brief every agent read. `workflows/`: the workflow scripts (research,
  settle, grading, grading_review, report_specs), as run from /tmp/claude-0/proto/research/kores.
- `collect.py` merges the research journals into `out/results2.json`; `settle_files.py` writes the judges' and fact
  checkers' inputs; `settle_merge.py` merges their results and `out/manual_decisions.json` (the disputed leaves classified
  by hand from the reviews) into `out/judges.json`; `to_results.py` writes `out/results_k.json` and `out/options.json`,
  applying `out/answers.json` (the owner's answers) and the grading checks `out/grading_<problem>.json`;
  `grading_files.py` writes the grading checks' inputs.
- `out/topics_k.json` and `out/decisions_k.json`: the open decisions; `out/impact_final.json`: besogo's grading under every
  fix and option (impact_scenarios.py and grading_impact.cjs); `specs/`: the report's diagram specs.
- Render: LAYOUT=ko RESULTS=out/results_k.json TOPICS=out/topics_k.json FIXED=out/fixed_k.json IMPACT=out/impact_final.json
  STYLE=../report_style.html DECISIONS=out/decisions_k.json PROBLEMS=status_problems.jsonl ../../report.py render specs out.html

## The third round (16 November)

- After the solver fixes of 15 and 16 November were merged, the solver rechecked all 127 disagreements
  (`../validation/recheck_merged.json`, compared by `../validation/recheck_compare.py`): no leaf it had right went
  wrong, 10 of the 14 solver errors are fixed, and the leaves' current labels are in `out/solver_now.json` (the
  report's SOLVER file).
- `round3/`: the brief, dossiers and cases of the third round: the 5 leaves of the fourth validation pass (k101-k105)
  and 13462's leaf 8 (k106), which the corrected leaf convention (D14) found. `round3_collect.py` turns its journals
  into `out/results2_round3.json`, which `to_results.py` appends; `out/grading_11279.json` is its grading check.
- 11332's leaf 3 (k029) became a decision (topic leaf-convention): the owner's trees mark a line ending with the
  solver's ko capture KO+ even when the solver then wins whatever happens; the research reads it ALIVE.
- `fixes3/`: the faults still left after the recheck (`cases.md`) and the briefs, cases and regression lists of the
  fix groups E1 (the leaf convention made KO+ of a clean failure; merged as D14), E2 (approaches that are not
  approaches: a failing outright test read from its children, merged as c6ad6ec7) and F (labels left undecided where
  the research settled them; `group-F.md`, `regress-F.txt`, being designed).
