export const meta = {
  name: 'ko-label-grading-review',
  description: 'Review what the grading checks found beyond the research: two reviewers per problem try to refute the new leaf fixes and line corrections',
  phases: [
    { title: 'Review', detail: 'a position reviewer and a definitions reviewer per grading finding' },
  ],
}

const K = '/tmp/claude-0/proto/research/kores'
const CHECKER = '/tmp/claude-0/proto/research/kovalid/wt/tools/tsumego-checker'
const COMMON = `
Read the brief first: ${K}/BRIEF.md. The design of the labels: ${CHECKER}/research/ko_labels/design.md (sections 1, 3, 11,
13 and the addendum; section 13 lists the open questions). Use the checker from ${CHECKER} (never modify anything there,
never commit) with PROBLEMS=/tmp/claude-0/dump2/status_problems.jsonl:
python3 evidence.py <id> "<moves from the start>" [--tree] [--label] [--ko] [--pv] [--budget=N]; --tree lists every leaf
with its S[]. Keep scratch files in ${K}/work/<your label>/. Run at most one engine process at a time: the machine is
shared by several agents; budget 400000 per search, one retry at 2000000, then call it undecided. The owner's rules:
never touch production or its database, never trigger GitHub workflows, never post anything; never present an
undecided search as a result. Statuses in besogo notation as typed in the SGF (KO+2, A-1KO-, 10kKO). Move lists use
'pass' for a move elsewhere and 'A18!' for a retake after a ko threat elsewhere.`

const REVIEW = {
  type: 'object',
  properties: {
    holds: { type: 'boolean', description: 'true when the finding survives your attempt to refute it: the other leaf is wrong as claimed, its proposed S[] is right (or right under one answer of an open question, which you name), and the line corrections are right' },
    leaf_status: { type: 'string', description: 'the S[] you find right for the other leaf the finding names; for a definition case "answer: S[...] | answer: S[...]"' },
    question: { type: 'string', description: 'the open question the leaf turns on (1a ... 10 or approach-label), or empty when it does not turn on one' },
    lines_correct: { type: 'boolean', description: 'true when the finding\'s corrections to the research\'s key lines are right' },
    explanation: { type: 'string', description: 'what you tried and found, plainly, for the owner' },
    variations: { type: 'string', description: 'the decisive lines as move lists from the start with their results (they become diagrams)' },
    solver_checks: { type: 'string', description: 'the searches you ran (positions as moves, area, budget) and their results' },
  },
  required: ['holds', 'leaf_status', 'question', 'lines_correct', 'explanation', 'variations', 'solver_checks'],
}

function prompt(p, lens) {
  const head = `${COMMON}
Problem ${p.id} (https://tsumego.com/${p.id}, "${p.title}"). The research proposed S[] fixes on some leaves; a grading
checker then ran the site's grading with those fixes and found more. Its input: ${K}/settle/grading_${p.id}.txt. Its
finding, with lines and searches: ${K}/settle/grading_${p.id}.result.md. What it claims, in short: ${p.claims}`
  if (lens === 'position')
    return `${head}

You review this finding by READING THE POSITION. Try hard to refute it: find a better order or defence, a local threat
or approach the checker missed, a line that makes the other leaf's tree S[] right, or a line that shows the research's
original key lines work after all. Play the lines out yourself and check the decisive counts with the checker in fresh
engine processes. holds = true only if the finding survives.`
  return `${head}

You review this finding AGAINST THE DEFINITIONS: besogo's status semantics (webroot/besogo/js/status.js in
/home/user/tsumego-hero: the count of each label and the ranking), the owner's definitions in the brief (approach ko,
10kKO, the approach-round rule from c121) and the open questions. Does the proposed S[] for the other leaf follow from the
facts, or does it turn on an open question (name it and give the S[] under each answer)? What does the site's grading do
under each label (which moves are correct)? Check the facts you rely on with your own searches. holds = true only if
the finding survives.`
}

const items = args.problems
if (!Array.isArray(items)) throw new Error('args.problems must be a list of {id, title, claims}')
const results = await parallel(items.map(p => async () => {
  const reviews = (await parallel(['position', 'definitions'].map(lens => () =>
    agent(prompt(p, lens), { label: `grading-review-${lens}:${p.id}`, phase: 'Review', schema: REVIEW })
      .then(r => r && { lens, ...r })))).filter(Boolean)
  return { id: p.id, reviews }
}))
const done = results.filter(Boolean)
log(`${done.length} of ${items.length} grading findings reviewed`)
return { results: done }
