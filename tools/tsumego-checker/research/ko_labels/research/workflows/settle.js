export const meta = {
  name: 'ko-label-settle',
  description: 'Settle the disputed ko-label research leaves with independent judges, or check the facts behind the leaves that turn on an open question',
  phases: [
    { title: 'Judge', detail: 'one or two independent judges per disputed leaf, a third where two disagree' },
    { title: 'Facts', detail: 'one fact checker per leaf that turns on an open question' },
  ],
}

const K = '/tmp/claude-0/proto/research/kores'
const CHECKER = '/tmp/claude-0/proto/research/kovalid/wt/tools/tsumego-checker'
const COMMON = `
Read the brief first: ${K}/BRIEF.md. The design of the labels: ${CHECKER}/research/ko_labels/design.md (sections 1, 3, 11,
13 and the addendum; section 13 lists the open questions with their examples). Use the checker from ${CHECKER} (never
modify anything there, never commit) with PROBLEMS=/tmp/claude-0/dump2/status_problems.jsonl. Keep scratch files in
${K}/work/<your label>/. Run at most one engine process at a time: the machine is shared by several agents; budget 400000
per search, one retry at 2000000, then call it undecided. The owner's rules: never touch production or its database,
never trigger GitHub workflows, never post anything; never present an undecided search as a result.`

const RULES = `How to classify (the brief's rule: a leaf whose label turns on an open question is a definition case):
- site_error: the tree's S[] is wrong, and every answer to the open questions gives the same right S[].
- solver_error: the tree's S[] is right under every answer, and the checker's label is wrong.
- definition: the right S[] depends on one of the open questions (1a, 1b, 2, 3, 4, 5, 6, 7, 8, 9, 10 or approach-label):
  name it, give the S[] under each answer, and say whether the tree's S[] is right under any answer. Name a question only
  if its answers really give different labels for THIS leaf.
- unclear: you could not settle it; say what is missing.
Statuses in besogo notation as typed in the SGF (KO+2, A-1KO-, 10kKO). Move lists from the leaf use 'pass' for a move
elsewhere and 'A18!' for a retake after a ko threat elsewhere.`

const VERDICT = {
  type: 'object',
  properties: {
    verdict: { type: 'string', enum: ['site_error', 'solver_error', 'definition', 'unclear'] },
    correct_status: { type: 'string', description: 'the right S[] for site_error or solver_error; empty for definition or unclear' },
    question: { type: 'string', description: 'for definition: the open question it turns on (1a ... 10 or approach-label); else empty' },
    options: { type: 'string', description: 'for definition: the S[] under each answer, as "answer: S[...]" items separated by " | "; else empty' },
    tree_right_under_some_answer: { type: 'boolean', description: 'for definition: true if the tree\'s S[] is right under one of the answers; else false' },
    confidence: { type: 'string', enum: ['high', 'medium', 'low'] },
    explanation: { type: 'string', description: 'a few plain sentences for the owner: the decisive variation, its result, and what the other view missed' },
    key_line: { type: 'string', description: 'moves from the leaf that show the result' },
    variations: { type: 'string', description: 'the key variations from the leaf as move lists with their outcomes (they become diagrams); include the line that refutes the other view' },
    checker_part: { type: 'string', description: 'which part of the checker\'s label is wrong (class, first take, approach count, direct-ko value, local threats, area, other) and why; empty if the checker is right' },
    solver_checks: { type: 'string', description: 'the searches you ran (positions as moves, area, budget) and their results' },
  },
  required: ['verdict', 'correct_status', 'question', 'options', 'tree_right_under_some_answer', 'confidence', 'explanation', 'key_line', 'variations', 'checker_part', 'solver_checks'],
}
const FACTS = {
  type: 'object',
  properties: {
    facts_hold: { type: 'boolean', description: 'true if the position and the lines the question rests on are as the analyst describes' },
    correction: { type: 'string', description: 'what in the analyst\'s description is wrong or missing; empty if nothing' },
    ...VERDICT.properties,
  },
  required: ['facts_hold', 'correction', ...VERDICT.required],
}

function head(c) {
  return `${COMMON}
Problem ${c.id} (https://tsumego.com/${c.id}, "${c.title}"), case ${c.case}: the leaf reached by "${c.line}" from the
start. The tree says S[${c.authored}]; the checker's full label is ${c.label}. The research dossier with the checker's
label parts and lines: ${K}/dossiers/${c.id}.md.`
}

function judgePrompt(c, order, earlier) {
  return `You are a strong go player settling a dispute about one leaf of a tsumego.com status problem.
${head(c)}
An analyst and reviewers did not agree. Their views, with the searches behind them, are in ${K}/settle/${c.case}.${order}.md; read it.${earlier ? `
Two judges have already looked at it and disagree; their verdicts follow. Decide between them by reading the positions
where they part yourself.
${earlier}` : ''}
Settle it by your own reading of the position. Find the points where the views part (a move one side thinks best, a line
one side missed, a count, an artefact of the checker's search area, an open question that does or does not change this
leaf's label) and read those positions yourself, checking the decisive ones with the checker while keeping its weak
points in mind (the search area's wall, second kos and step kos, sente approaches, undecided searches). Do not take any
view's word for a line: play it out.

${RULES}`
}

function factsPrompt(c) {
  return `You are a strong go player checking the facts behind a question for the owner of tsumego.com.
${head(c)}
An analyst concluded that this leaf's label turns on an open question for the owner. The analyst's description, the
lines and the searches are in ${K}/settle/${c.case}.facts.md; read it.

The owner will answer the question from these facts, so check them: read the position yourself, play out the lines the
question rests on, and check the counts with the checker. Is the description right? Does the leaf really turn on that
question (do its answers give different S[] here), or is one side simply wrong under the owner's definitions and
besogo's semantics? Set facts_hold and the correction, then classify the leaf.

${RULES}`
}

const cases = args.cases
if (!Array.isArray(cases)) throw new Error('args.cases must be a list')
let results
if (args.mode === 'judge') {
  results = await parallel(cases.map(c => async () => {
    const orders = c.judges === 2 ? ['a', 'b'] : ['a']
    const judges = (await parallel(orders.map(order => () =>
      agent(judgePrompt(c, order, ''), { label: `judge-${order}:${c.case}`, phase: 'Judge', schema: VERDICT })))).filter(Boolean)
    let third = null
    if (judges.length === 2 && (judges[0].verdict !== judges[1].verdict ||
        (judges[0].verdict !== 'definition' && judges[0].correct_status.trim() !== judges[1].correct_status.trim()) ||
        (judges[0].verdict === 'definition' && judges[0].question.trim() !== judges[1].question.trim()))) {
      const earlier = judges.map((j, i) => `Judge ${i + 1}: ${JSON.stringify(j)}`).join('\n')
      third = await agent(judgePrompt(c, 'a', earlier), { label: `judge-c:${c.case}`, phase: 'Judge', schema: VERDICT })
    }
    return { case: c.case, id: c.id, judges, third }
  }))
} else if (args.mode === 'facts') {
  results = await parallel(cases.map(c => async () => {
    const facts = await agent(factsPrompt(c), { label: `facts:${c.case}`, phase: 'Facts', schema: FACTS })
    return { case: c.case, id: c.id, facts }
  }))
} else {
  throw new Error('args.mode must be judge or facts')
}
const done = results.filter(Boolean)
log(`${done.length} of ${cases.length} cases done`)
return { results: done }
