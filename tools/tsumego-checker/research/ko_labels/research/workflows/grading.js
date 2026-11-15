export const meta = {
  name: 'ko-label-grading',
  description: 'Check the grading changes the ko-label research fixes cause: one strong-player checker per problem tries to show the new correct/wrong marks wrong',
  phases: [
    { title: 'Grading', detail: 'one checker per problem whose fixes change which moves are correct' },
  ],
}

const K = '/tmp/claude-0/proto/research/kores'
const CHECKER = '/tmp/claude-0/proto/research/kovalid/wt/tools/tsumego-checker'

const GRADING = {
  type: 'object',
  properties: {
    holds: { type: 'boolean', description: 'true if, with only the S[] fixes applied, the site grades every changed move right' },
    explanation: { type: 'string', description: 'a few plain sentences for the site owner: for each changed move, whether the new mark is right and why' },
    tree_changes: { type: 'string', description: 'changes to the tree beyond the S[] fixes that the grading needs (a missing best reply to add, a move to remove, another S[] to set), each with its line from the start; empty if none' },
    other_leaves: { type: 'string', description: 'other leaves of this tree whose S[] looks wrong, with the line, the tree S[] and the right S[]; empty if none' },
    variations: { type: 'string', description: 'the key lines you read, as move lists from the start with their results (they become diagrams)' },
    solver_checks: { type: 'string', description: 'the searches you ran (positions as moves, budget) and their results' },
  },
  required: ['holds', 'explanation', 'tree_changes', 'other_leaves', 'variations', 'solver_checks'],
}

function gradingPrompt(p) {
  return `You are a strong go player checking how the fixes the research proposes for tsumego.com problem ${p.id} (https://tsumego.com/${p.id}, "${p.title}") change what the site accepts as correct.

Read the brief ${K}/BRIEF.md first: besogo's statuses and their ranking, the owner's definitions, and the checker with its weak points. The checker runs from ${CHECKER} (never modify anything there, never commit):
PROBLEMS=/tmp/claude-0/dump2/status_problems.jsonl python3 evidence.py ${p.id} "<moves from the start>" [--tree] [--label] [--ko] [--pv] [--moves] [--budget=N]
--tree lists every leaf of the tree with its S[]; --label gives the full ko label with its parts. Then read ${K}/settle/grading_${p.id}.txt: the leaves the research found wrong with their new S[], and the moves whose correct/wrong mark changes when the site's own grading code (besogo) runs on the tree with all those fixes applied.

How the site grades a status problem: each leaf has an S[]; a node's status is the best child status for the side to move there (in besogo's order: for the solver, ALIVE/DEAD as the goal says, then the kos by approaches first and threats second, 10kKO ranked like the attacker's approach form); a move is correct when its line is still correct and no sibling move has a better status. So a changed mark can be wrong because a fix is wrong, because another leaf's S[] is wrong, or because the tree lacks the opponent's best reply somewhere (then a refuted line looks like a success).

Your job: try to show that the new grading is wrong. For each move whose mark changes, read the position where the move is chosen and compare the moves there, using the tree's leaves and the checker (keeping its weaknesses in mind: the search area's wall, second kos, sente approaches, undecided searches). Is a newly accepted move really as good as the best one? Is a newly rejected move really worse? Does the opponent have a better reply that the tree leaves out? Run one engine process at a time, no background jobs, budget 400000 per search with one retry at 2000000, at most about 25 minutes of solver time; never present an undecided search as a result. Your scratch folder is ${K}/work/${p.id}-grading/ (create it). Don't modify anything else. The owner's rules: never touch production or its database, never trigger GitHub workflows, never post anything.

Set holds, explain each changed move plainly, list any tree changes the grading needs beyond the S[] fixes and any other leaves whose S[] looks wrong, and give the key lines as move lists from the start (they become diagrams).`
}

const problems = args.problems
if (!Array.isArray(problems)) throw new Error('args.problems must be a list of {id, title}')
const results = await parallel(problems.map(p => async () => {
  const grading = await agent(gradingPrompt(p), { label: `grading ${p.id}`, phase: 'Grading', schema: GRADING })
  return { id: p.id, grading }
}))
const done = results.filter(Boolean)
log(`${done.length} of ${problems.length} problems checked`)
return { results: done }
