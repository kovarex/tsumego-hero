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
