<!-- The brief the analyst and reviewer agents got in the research run of November 2026. The paths are that run's:
/tmp/claude-0/proto was this folder (engine built there), /tmp/claude-0/dump2/status_problems.jsonl the export of
the status problems, research/evidence/ the output of research/precompute.py. -->

# Brief: checking the status disagreements on tsumego.com

tsumego.com status problems have G[KILL] or G[LIVE] on the root and an S[] status on every leaf of the solution tree
(besogo, `webroot/besogo/js/status.js` in /home/user/tsumego-hero). An exact local solver (df-pn, our research prototype)
checked every leaf; on some leaves it disagrees with the tree. For each such leaf, find out **who is right and why**:
is the tree's S[] wrong (an error on the site, the owner will fix it), is the solver wrong, or is it a question of
definitions. Check each case by reading the position like a strong go player; the solver is a calculator you can
use, not the judge.

## The statuses (besogo), always from the solver's side

The first player of the problem is "the solver" (Black in most problems; in some, White plays first, and then White is
"the solver": authors write the signs from the solver's side, not by colour).

- ALIVE, DEAD: plain life or death of the target group with best play from the leaf position.
- SEKI (seki in gote), SEKI+ (seki in sente). The checker counts a seki as ALIVE for the target.
- BENT4: bent four in the corner (dead under Japanese rules, the attacker removes ko threats first). Ranked just above DEAD.
- Kos, with the counts of ko threats s (solver) and o (opponent) for which the solver reaches its goal:
  - KO+: the solver takes first (it just captured, or it can capture first): the solver wins when s >= o.
  - KO-: the opponent takes first: the solver wins when s >= o + 1.
  - KO+n (n >= 2): the opponent needs n-1 threats to start the ko: the solver wins when s >= o - (n-1).
  - KO-n (n >= 2): the solver needs n-1 threats to start the ko: the solver wins when s >= o + n.
  - The site's own tutorial (/home/user/tsumego-hero/templates/Sites/gotutorial.php) calls KO+n and KO-n step kos: a ko
    whose win does not settle the status at once. KO+2 becomes a plain KO+ once the opponent has won it once; KO-2
    becomes a plain KO- once the solver has won it once. In threat counts this is the same as the two rules above.
  - A+nKO..., A-nKO...: approach kos. A+n: the opponent needs n approach moves before it is a direct ko; A-n: the
    solver needs n. Approaches rank above threats in besogo's ordering.
  - 10kKO (ten thousand year ko): like an approach ko where the attacker needs one approach move.
  - DDKO / ADKO: dead / alive in double ko. DSKO / ASKO: dead / alive in super ko.
- Order for the attacker (worst first for the target): DEAD < BENT4 < DSKO < DDKO < 10kKO < KO < SEKI < ADKO < ASKO < ALIVE.
  Among kos: more approaches for the opponent, then more threats needed by the opponent, is better for the solver.
- The leaf's status is the result with best play from the leaf position, with the side to play at the leaf moving next.
  When the last move of the line captured a ko, the side that captured "took first". A local threat (a move inside
  the position that the other side must answer) works like a ko threat and can change the sign.
- The checker compares statuses by class: ALIVE (also SEKI, SEKI+), DEAD, KO (any ko: KO±n, A±nKO, 10kKO, ADKO...),
  BENT4. For plain KO±n leaves it also compares the sign.

## How the solver works (and where it is weak)

- It reads only inside a search area (the "region") around the target group: the points marked `.` in the evidence
  boards. Everything outside is treated as an uncapturable wall of the attacker that gives the defender no
  liberties. **A group that runs out of the area does not count as escaping** (it is treated as shut in), and a
  defender stone at the area's edge has fewer liberties than it really has. The "wider area" (`+` points) adds two
  rings of empty points and decides kos and ko signs.
- The goal is only the life or death of the target group (the largest defender group at the start). Saving cutting
  stones, capturing stones, points or sente are not part of it, unless they decide the target's life.
- Modes: A = the defender may ignore the ko ban (unlimited threats); D = the attacker may. If the attacker kills in A,
  DEAD; if it cannot kill even in D, ALIVE; if it kills only in D, KO. Seki and life count the same (ALIVE).
- When both players pass, the game ends: a target chain in atari, or a defender chain in atari in the target's area
  (like a ko stone that makes a false eye, or a stone attacking enclosed cutting stones), is taken, then the target is
  checked. A sending-two-returning-one cycle is seki; a cycle through a ko retake is a ko the attacker wins at the end.
- Ko signs come from counted-threat searches: the solver wins or not for given counts s and o (a retake against the
  ko ban uses one threat). Local threats are real moves inside the area; outside threats are just the counts.
- Searches have a node budget (default 200000; ko tables use 5x). `?` means undecided within the budget, not proven.
  Results can differ a little between runs (move ordering), especially near the budget.
- A leaf where the solver's last move captured a ko counts as a ko (KO+), even if the solver could later steer to
  clean life or death; that matches how the trees are written.

## Your tools (read-only use of /tmp/claude-0/proto; write scratch files only under your own folder)

`python3 /tmp/claude-0/proto/evidence.py <problem id> "<moves from the start>" [--tree] [--moves] [--pv] [--ko] [--budget=N]`

- Moves are points like `A18`, `pass`, or `A18!` for a retake against the ko ban (it stands for a ko threat and its
  answer). They alternate from the first player; you can leave the tree (any legal moves).
- It prints the problem, the tree's leaves with their S[] (`--tree`), the start position and the position after your
  moves (with the search area marked), the solver's verdict in the search area and the wider area, a one-move table
  (`--moves`: the verdict after each legal move of the side to play), the solver's line (`--pv`), and ko tables
  (`--ko`: win or not for s-o from -3 to +3 in both areas, the sign, and counted lines).
- A position costs from a second to several minutes (`--moves` and `--ko` are the slow parts; undecided searches use
  the whole budget). Run one solver command at a time (the machine has 4 cores shared by several analysts); don't
  start background jobs; keep your total solver time per case to roughly 15 minutes. Use `--budget=2000000` only for
  a position where the answer depends on it.
- Precomputed evidence for each case is in `/tmp/claude-0/proto/research/evidence/<case>.txt` (made with `--tree
  --moves --pv`, plus `--ko` for ko cases). Read it first.
- The besogo source is in `/home/user/tsumego-hero/webroot/besogo/js/` if you need the exact definitions.
- Never edit or delete anything outside `/tmp/claude-0/proto/research/scratch/`. Never touch the network, the site, the
  database or the git repository.

## How to decide

1. Understand the problem: goal, target, what the tree is teaching around this leaf (its sibling leaves and their
   statuses), and the description.
2. Read the leaf position yourself: liberties, eyes, ko shapes, cutting points, who is to play.
3. Find the best play for both sides from the leaf. Use the solver on sub-positions to check the variations, but think about whether its model fits (area edge, escapes, stones outside the target, budget).
4. For a ko: where is the ko, who captured last, who can take first, are approach moves needed, which local threats
   does each side have? Then which besogo label is right. Compare with the ko tables of the wider area.
5. Verdict:
   - `site_error`: the tree's S[] is wrong under besogo's definitions, and you know the right S[] from your own
     reading of the position (checked with the solver where it can help). Not a matter of convention.
   - `solver_error`: the tree's S[] is right; say what misleads the solver.
   - `definition`: the disagreement comes from how a status is defined or labelled (DEAD vs BENT4, approach-ko
     notation vs counted threats, seki labels, a goal that is more than the target's life such as saving cutting
     stones or "fighting for points", a convention about who took first). Say the question the owner must decide.
   - `unclear`: you could not settle it; say what is left to check.
   Be honest about confidence. A site_error claim will be checked by skeptics who try to refute it.
