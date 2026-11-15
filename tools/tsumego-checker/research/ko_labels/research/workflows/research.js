export const meta = {
  name: 'ko-label-research',
  description: 'Who is right where the full ko label disagrees with the tree: one analyst per problem, reviewers try to refute each verdict',
  phases: [
    { title: 'Analyse', detail: 'one analyst per problem decides each disagreeing leaf' },
    { title: 'Verify', detail: 'two reviewers per site error, one per solver error, each trying to refute it' },
  ],
}

const K = '/tmp/claude-0/proto/research/kores'
const CHECKER = '/tmp/claude-0/proto/research/kovalid/wt/tools/tsumego-checker'
const COMMON = `
Read the brief first: ${K}/BRIEF.md. The design of the labels: ${CHECKER}/research/ko_labels/design.md (sections 1, 3, 11,
13 and the addendum). Use the checker from ${CHECKER} (never modify anything there, never commit) with
PROBLEMS=/tmp/claude-0/dump2/status_problems.jsonl. Keep scratch files in ${K}/work/<your label>/. Run at most one engine
process at a time: the machine is shared by several agents; budget 400000 per search, one retry at 2000000, then call it
undecided. The owner's rules: never touch production or its database, never trigger GitHub workflows, never post
anything; never present an undecided search as a result.`

const CASE = {
  type: 'object',
  properties: {
    case: { type: 'string' },
    verdict: { type: 'string', enum: ['site_error', 'solver_error', 'definition', 'unclear'] },
    correct_status: { type: 'string', description: 'the right S[] for this leaf in besogo notation, or empty when unknown' },
    confidence: { type: 'string', enum: ['high', 'medium', 'low'] },
    summary: { type: 'string', description: 'two or three plain sentences for the owner: what is right and why' },
    variations: { type: 'string', description: 'the key variations from the leaf as move lists with outcomes; include the main alternatives' },
    solver_checks: { type: 'string', description: 'the searches you ran (positions as moves from the start, options, counts) and their results' },
    key_line: { type: 'string', description: 'moves from the leaf position that show the result (A18, pass, A18! for a retake after a threat elsewhere)' },
    checker_part: { type: 'string', description: 'for solver_error: which part of the checker\'s label is wrong (class, first take, approach count, direct-ko value, local threats, area, other) and why; else empty' },
    depends_on: { type: 'string', description: 'for definition: the open question it turns on (1a, 1b, 2, 3, 4, 5, 6, 7, 8, 9, 10 or approach-label) and what each answer gives; else empty' },
    earlier_research: { type: 'string', description: 'for cases with an earlier research verdict: whether that settled status was right, and why; else empty' },
  },
  required: ['case', 'verdict', 'correct_status', 'confidence', 'summary', 'variations', 'solver_checks', 'key_line', 'checker_part', 'depends_on', 'earlier_research'],
}
const ANALYSIS = { type: 'object', properties: { cases: { type: 'array', items: CASE } }, required: ['cases'] }
const CHECK = {
  type: 'object',
  properties: {
    holds: { type: 'boolean', description: 'true when the analyst\'s verdict and status survive your attempt to refute them' },
    correct_status: { type: 'string', description: 'the status you find right, or empty' },
    explanation: { type: 'string', description: 'what you tried, what you found, with the searches behind it' },
  },
  required: ['holds', 'correct_status', 'explanation'],
}

const problems = args.problems
log(`${problems.length} problems, ${problems.reduce((n, p) => n + p.cases.length, 0)} cases`)
const results = await pipeline(
  problems,
  p => agent(`${COMMON}
You analyse problem ${p.id} (${p.title}). Its dossier, with the tree's leaves and every disagreeing leaf (the checker's
label with its parts, text, lines and comparison; the earlier research's verdict where there was one): ${K}/dossiers/${p.id}.md.
Cases: ${p.cases.map(c => c.case + ' (' + c.group + ')').join(', ')}.
For each case decide who is right, reading the position like a strong player and using the checker as a calculator:
look at the checker's lines and parts, try the other side's best resistance, check the counts you rely on, and be
especially careful with the known weak points in the brief (sente approaches, second kos, the area's wall, undecided
classes). Return one entry per case.`, { label: 'analyse:' + p.id, phase: 'Analyse', schema: ANALYSIS }),
  (analysis, p) => {
    if (!analysis) return { problem: p.id, analysis: null, reviews: [] }
    const jobs = []
    for (const c of analysis.cases) {
      const meta = p.cases.find(x => x.case === c.case) || {}
      const ctx = `${COMMON}
Problem ${p.id}, case ${c.case} (leaf "${meta.line || '?'}", tree S[${meta.authored || '?'}], checker ${meta.label || '?'}).
Dossier: ${K}/dossiers/${p.id}.md. The analyst's verdict: ${JSON.stringify(c)}`
      if (c.verdict === 'site_error') {
        jobs.push(() => agent(`${ctx}
You review this verdict by READING THE POSITION. Try hard to refute it: find a better defence or attack, a local threat
or approach the analyst missed, or a line that makes the tree's S[${meta.authored || '?'}] right. Run your own searches in
a fresh engine process. holds = true only if the verdict and its status survive.`, { label: 'review-position:' + c.case, phase: 'Verify', schema: CHECK }).then(r => ({ case: c.case, lens: 'position', ...(r || {}) })))
        jobs.push(() => agent(`${ctx}
You review this verdict AGAINST THE DEFINITIONS: besogo's status semantics (status.js, the count T of each label, the
ranking) and the owner's definitions and open questions in the brief. Does the proposed status follow from the facts?
Is it really a site error, or does it turn on an open question (then it is a definition case)? Check the facts you rely
on with your own searches. holds = true only if the verdict and its status survive.`, { label: 'review-definitions:' + c.case, phase: 'Verify', schema: CHECK }).then(r => ({ case: c.case, lens: 'definitions', ...(r || {}) })))
      } else if (c.verdict === 'solver_error') {
        jobs.push(() => agent(`${ctx}
The analyst says the tree is right and the checker wrong. Try to show the opposite: that the checker's label is right and
the tree's S[] is wrong, or that both are wrong. Run your own searches in a fresh engine process and read the position.
holds = true only if "the tree is right, the checker wrong" survives.`, { label: 'review-solver:' + c.case, phase: 'Verify', schema: CHECK }).then(r => ({ case: c.case, lens: 'solver', ...(r || {}) })))
      }
    }
    return parallel(jobs).then(reviews => ({ problem: p.id, analysis, reviews: reviews.filter(Boolean) }))
  },
)
return { results }
