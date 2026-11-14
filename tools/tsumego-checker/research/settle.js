export const meta = {
  name: 'settle-research-cases',
  description: 'Settle disputed status cases with two independent judges, or fact-check the definition questions',
  phases: [
    { title: 'Judge', detail: 'two independent judges per disputed case' },
    { title: 'Facts', detail: 'one checker per definition question' },
  ],
}

const BRIEF = '/tmp/claude-0/proto/research/BRIEF.md'
const EVIDENCE = '/tmp/claude-0/proto/research/evidence'
const SETTLE = '/tmp/claude-0/proto/research/settle'

const JUDGE = {
  type: 'object',
  properties: {
    verdict: { type: 'string', enum: ['site_error', 'solver_error', 'definition', 'unclear'], description: 'site_error: the tree S[] is wrong; solver_error: the tree S[] is right; definition: it depends on a labelling or goal decision; unclear: you could not settle it' },
    correct_status: { type: 'string', description: 'the S[] that is right for this leaf in besogo notation, or empty when it depends on a decision or is unknown' },
    confidence: { type: 'string', enum: ['high', 'medium', 'low'] },
    explanation: { type: 'string', description: 'a few plain sentences for the site owner: the decisive variation, its result, and what the other view missed' },
    key_line: { type: 'string', description: 'moves continuing from the leaf position that show the result, in evidence.py notation (A18, pass, A18!); empty if none' },
    question: { type: 'string', description: 'for definition or unclear: the decision or check the owner must make, with the label under each choice; else empty' },
  },
  required: ['verdict', 'correct_status', 'confidence', 'explanation', 'key_line', 'question'],
}

const FACTS = {
  type: 'object',
  properties: {
    facts_hold: { type: 'boolean', description: 'true if the position and the lines the question rests on are as the analyst describes' },
    verdict: { type: 'string', enum: ['definition', 'site_error', 'solver_error', 'unclear'], description: 'definition if it really is a labelling or goal decision; site_error or solver_error if one side is simply wrong under the definitions' },
    correction: { type: 'string', description: 'what in the analyst\'s description is wrong or missing; empty if nothing' },
    explanation: { type: 'string', description: 'a few plain sentences: the decisive lines and their results' },
  },
  required: ['facts_hold', 'verdict', 'correction', 'explanation'],
}

function header(c, lens) {
  return `Problem ${c.id} (https://tsumego.com/${c.id}, "${c.title}"), case ${c.case}: the leaf reached by the moves "${c.line}" from the start. The tree says S[${c.authored}]; the solver says ${c.solver}. Precomputed evidence: ${EVIDENCE}/${c.case}.txt

Read the brief ${BRIEF} first (status definitions, how the solver works and where it fails, the evidence tool: python3 /tmp/claude-0/proto/evidence.py ${c.id} "<moves>" [--tree] [--moves] [--pv] [--ko] [--budget=N]). Run one solver command at a time, no background jobs, at most about 20 minutes of solver time. Your scratch folder is /tmp/claude-0/proto/research/scratch/${c.id}-${c.case}-${lens}/ (create it if you need files). Don't modify anything else.`
}

function judgePrompt(c, lens) {
  return `You are a strong go player settling a dispute about one leaf of a tsumego.com status problem.

${header(c, lens)}

Earlier checkers did not agree. Their views are in ${SETTLE}/${c.case}.${lens === 'judge-b' ? 'b' : 'a'}.txt; read it.

Settle it by your own reading of the position. Find the points where the views part (a move one side thinks is best, a line one side missed, a ko count, a solver artefact) and read those positions yourself, checking the decisive ones with the solver while keeping its weaknesses in mind (search-area edge, end-of-game rule when both pass, node budget, goal limited to the target group). Do not take any view's word for a line: play it out. If the right label depends on something the problem does not fix (which stones are the target, how much room the open edge has, a labelling convention), return definition with the question the owner must decide and the label under each choice. Return your verdict, the right S[], your confidence, and a plain explanation of the decisive variation.`
}

function factsPrompt(c) {
  return `You are a strong go player checking the facts behind a question for the owner of tsumego.com.

${header(c, 'facts')}

An analyst concluded that this disagreement is a question of definitions, not an error of the tree or the solver. The analyst's description, the question for the owner and the lines are in ${SETTLE}/${c.case}.facts.txt; read it.

The owner will decide the question based on these facts, so check them. Read the position yourself and play out the lines the question rests on (for a bent four: is it really the corner bent-four shape, who can start the ko and when, can the defender avoid it or remove it; for a goal question: which stones live and die in each line). Is the description right? Is it really a decision about definitions or the goal, or is one side simply wrong under besogo's definitions? Set facts_hold, the verdict, any correction, and a plain explanation.`
}

const cases = args.cases
if (!Array.isArray(cases))
  throw new Error('args.cases must be a list of cases')
let results
if (args.mode === 'judge') {
  results = await parallel(cases.map(c => async () => {
    const judges = (await parallel(['judge-a', 'judge-b'].map(lens => () =>
      agent(judgePrompt(c, lens), { label: `${lens} ${c.id} ${c.case}`, phase: 'Judge', schema: JUDGE })))).filter(Boolean)
    const verdicts = judges.map(j => j.verdict)
    const final = judges.length === 2 && verdicts[0] === verdicts[1] ? verdicts[0] : 'unclear'
    return { case: c.case, id: c.id, judges, final }
  }))
} else if (args.mode === 'facts') {
  results = await parallel(cases.map(c => async () => {
    const facts = await agent(factsPrompt(c), { label: `facts ${c.id} ${c.case}`, phase: 'Facts', schema: FACTS })
    return { case: c.case, id: c.id, facts }
  }))
} else {
  throw new Error('args.mode must be judge or facts')
}
const done = results.filter(Boolean)
log(`${done.length} of ${cases.length} cases settled`)
return done
