export const meta = {
  name: 'status-disagreements',
  description: 'Check status/ko disagreements between the solver and tsumego.com trees, then have reviewers verify each verdict',
  phases: [
    { title: 'Analyse', detail: 'one analyst per problem checks every disagreeing leaf' },
    { title: 'Verify', detail: 'reviewers try to refute site-error and solver-error verdicts' },
  ],
}

//the research run: one analyst agent per problem checks its disagreeing leaves; a site_error verdict goes to two
//reviewers (one reads the position, one checks the definitions), a solver_error to one; args: {problems: [{id, title,
//cases: [...cases.json entries]}]}. Paths are those of the run (see research/BRIEF.md).
const BRIEF = '/tmp/claude-0/proto/research/BRIEF.md'
const EVIDENCE = '/tmp/claude-0/proto/research/evidence'

const ANALYSIS = {
  type: 'object',
  properties: {
    cases: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          case: { type: 'string' },
          verdict: { type: 'string', enum: ['site_error', 'solver_error', 'definition', 'unclear'] },
          correct_status: { type: 'string', description: 'the S[] that is right for this leaf in besogo notation (e.g. KO-, KO+2, DEAD, SEKI, A-1KO+), or empty when unknown' },
          confidence: { type: 'string', enum: ['high', 'medium', 'low'] },
          summary: { type: 'string', description: 'two or three plain sentences for the site owner: what is right and why' },
          variations: { type: 'string', description: 'the key variations from the leaf as move lists with their outcomes, e.g. "A13 B17 C19: white is captured (DEAD)"; include the main alternatives for the other side' },
          solver_checks: { type: 'string', description: 'positions you ran through the solver (moves from the start) and its verdicts' },
          key_line: { type: 'string', description: 'moves continuing from the leaf position that show the result, in evidence.py notation (A18, pass, A18!), first move by the side to play at the leaf; empty if none' },
          question: { type: 'string', description: 'for definition or unclear: the decision or check the owner must make; else empty' },
        },
        required: ['case', 'verdict', 'correct_status', 'confidence', 'summary', 'variations', 'solver_checks', 'key_line', 'question'],
      },
    },
  },
  required: ['cases'],
}

const CHECK = {
  type: 'object',
  properties: {
    holds: { type: 'boolean' },
    correct_status: { type: 'string', description: 'the S[] that is right for this leaf, or empty if it cannot be told' },
    explanation: { type: 'string', description: 'a few sentences: the decisive variation and its result' },
  },
  required: ['holds', 'correct_status', 'explanation'],
}

function caseText(c) {
  return `- case ${c.case}: leaf "${c.line}" (moves from the start). Tree S[${c.authored}] (class ${c.authored_class}); solver ${c.solver}${c.solver_sign ? ' with sign ' + c.solver_sign : ''}. Kind: ${c.kind === 'ko' ? 'same ko, different sign' : 'different status class'}. Evidence: ${EVIDENCE}/${c.case}.txt`
}

function analysePrompt(p) {
  return `You are a strong go player checking tsumego.com problem ${p.id} (https://tsumego.com/${p.id}, "${p.title}") where an exact local solver disagrees with the S[] statuses of the solution tree.

Read the brief ${BRIEF} completely: it defines the statuses (besogo), explains how the solver works and where it is weak, the evidence tool, and the verdicts. Then read the precomputed evidence file of each case below.

Disagreeing leaves of this problem:
${p.cases.map(caseText).join('\n')}

For each case, settle who is right by reading the position, and use the evidence tool on positions after the leaf (python3 /tmp/claude-0/proto/evidence.py ${p.id} "<moves>" [--moves] [--pv] [--ko]) to check the variations. Look at the whole tree for context (sibling leaves, transpositions, what the author is teaching) and at the description. For ko cases count the ko precisely (who took first, approach moves, local threats each side has) and map it to besogo's label. Be skeptical of both the tree and the solver: the solver's region edge, its goal (only the target group), budget limits and ko modelling can mislead it; authors also make mistakes and use labels loosely.

Your scratch folder is /tmp/claude-0/proto/research/scratch/${p.id}/ (create it if you need files). Run one solver command at a time; no background jobs; roughly 15 minutes of solver time per case at most. Don't modify anything else.

Return one entry per case: the verdict (site_error / solver_error / definition / unclear), the S[] that is right, your confidence, a plain summary for the site owner (no long move lists; name the key points), the key variations with their outcomes, the solver checks you ran, a key line from the leaf, and for definition/unclear the question the owner must decide.`
}

function claimText(p, c, a) {
  return `Problem ${p.id} (https://tsumego.com/${p.id}, "${p.title}"), case ${c.case}: the leaf reached by the moves "${c.line}" from the start. The tree says S[${c.authored}]; the solver says ${c.solver}${c.solver_sign ? ' ' + c.solver_sign : ''}. Evidence: ${EVIDENCE}/${c.case}.txt

An analyst concluded: verdict ${a.verdict}, right status "${a.correct_status}" (confidence ${a.confidence}).
Summary: ${a.summary}
Key variations: ${a.variations}
Solver checks: ${a.solver_checks}
Key line from the leaf: ${a.key_line || '(none)'}`
}

function reviewPrompt(p, c, a, lens) {
  const common = `You are a strong go player reviewing another analyst's conclusion. Read the brief ${BRIEF} first (status definitions, how the solver works and fails, the evidence tool: python3 /tmp/claude-0/proto/evidence.py ${p.id} "<moves>" [--moves] [--pv] [--ko]). Your scratch folder is /tmp/claude-0/proto/research/scratch/${p.id}-${c.case}-${lens}/. Run one solver command at a time, no background jobs, at most about 15 minutes of solver time. Don't modify anything else.

${claimText(p, c, a)}
`
  if (lens === 'reading')
    return common + `
Your job: try hard to REFUTE the claim that the tree's S[${c.authored}] is wrong, by reading the position. Check the analyst's variations move by move and, at every step, the other side's best alternatives (the defence or attack the analyst may have missed, tesuji, throw-ins, ko threats inside the position, approach moves). Use the solver on the positions, remembering its limits. Set holds=true only if you are convinced the tree's S[${c.authored}] is wrong (give the right status in correct_status). If you find a hole, or you are not sure, set holds=false and explain.`
  if (lens === 'definitions')
    return common + `
Your job: check the claim against besogo's exact definitions and the problem's intent. Could the tree's S[${c.authored}] be right after all under the definitions (who took first at the leaf, approach moves versus threats, seki vs alive, bent four, ten-thousand-year ko, double ko), or under what the description asks (e.g. more than the target's life)? Is the proposed label "${a.correct_status}" exactly right in its details (sign, number, approach count)? Read the position to settle the label. Set holds=true only if the tree's label is wrong under the definitions and you can name the right label (in correct_status, which may differ from the analyst's). If the tree's label is defensible, or you are not sure, set holds=false and explain.`
  return common + `
Your job: the analyst says the tree is right and the solver is wrong. Try to show the OPPOSITE: that the tree's S[${c.authored}] is wrong. Read the position: the solver's line, the alternatives, the ko count if any. Set holds=true if you agree the tree's S[${c.authored}] is right (or right enough that no fix is needed). Set holds=false if you find the tree is wrong or doubtful, with the status you believe right in correct_status and the line.`
}

const results = await pipeline(
  args.problems,
  (p) => agent(analysePrompt(p), { label: `analyse ${p.id}`, phase: 'Analyse', schema: ANALYSIS }),
  async (analysis, p) => {
    if (!analysis) return { id: p.id, error: 'analysis failed', cases: p.cases.map(c => ({ ...c, final: 'unclear', note: 'analysis agent failed' })) }
    const byCase = new Map(analysis.cases.map(a => [a.case, a]))
    const out = await parallel(p.cases.map(c => async () => {
      const a = byCase.get(c.case)
      if (!a) return { ...c, final: 'unclear', note: 'no analysis returned for this case' }
      if (a.verdict === 'site_error') {
        const checks = await parallel(['reading', 'definitions'].map(lens => () =>
          agent(reviewPrompt(p, c, a, lens), { label: `${lens} ${p.id} ${c.case}`, phase: 'Verify', schema: CHECK })))
        const valid = checks.filter(Boolean)
        const holding = valid.filter(v => v.holds).length
        const final = valid.length === 2 && holding === 2 ? 'site_error' : holding === 0 && valid.length === 2 ? 'refuted' : 'disputed'
        return { ...c, analysis: a, checks: valid, final }
      }
      if (a.verdict === 'solver_error') {
        const check = await agent(reviewPrompt(p, c, a, 'opposite'), { label: `opposite ${p.id} ${c.case}`, phase: 'Verify', schema: CHECK })
        const final = check && check.holds ? 'solver_error' : 'disputed'
        return { ...c, analysis: a, checks: check ? [check] : [], final }
      }
      return { ...c, analysis: a, checks: [], final: a.verdict }
    }))
    return { id: p.id, cases: out }
  },
)
const flat = results.filter(Boolean).flatMap(r => r.cases)
log(`${flat.length} cases: ` + ['site_error', 'disputed', 'refuted', 'solver_error', 'definition', 'unclear'].map(k => `${k} ${flat.filter(c => c.final === k).length}`).join(', '))
return flat