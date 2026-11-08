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
  solver's side). Reads commands on stdin (`problem`, `solve`, `solveplain`, `kofree`, `bentfour`).
- `tsumego.py`: SGF parsing, go rules, the problem region, and the bridge to the engine (statuses DEAD, KO, BENT4,
  ALIVE).
- `check_tree.py`: the checker for one problem tree, and `site` mode over an export. Every finding is solved again
  over a wider region with a bigger budget and kept only if that agrees.
- `status_eval.py`, `status_summary.py`: accuracy on the status problems, leaf by leaf.
- `first_moves.py`: the first-move check used on public collections (sanderland/tsumego).
- `line.py`, `moves.py`, `recheck.py`, `checklist.py`: inspection of single problems and the review checklist.
- `gen_trees.py`, `gen_status.py`, `inject_eval.py`: trees with planted errors, to test the pipeline.
- `build_report.py`, `report_body.html`, `report_style.html`: the research report.
- `export-problems.sh`: the read-only export of the current version of every problem.
