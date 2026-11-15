export const meta = {
  name: 'ko-label-report-specs',
  description: 'Write the ko-label research report as diagrams: one writer per problem or decision topic turns its dossier into a validated spec, one reviewer checks it against the research',
  phases: [
    { title: 'Write', detail: 'one writer per problem or decision topic' },
    { title: 'Review', detail: 'one reviewer per problem or decision topic' },
  ],
}

const R = '/home/user/tsumego-hero/tools/tsumego-checker/research'
const K = '/tmp/claude-0/proto/research/kores'
const D = `${K}/report`
const CHECK = (file) => `PROBLEMS=/tmp/claude-0/dump2/status_problems.jsonl RESULTS=${K}/out/results_k.json TOPICS=${K}/out/topics_k.json python3 ${R}/report.py check ${file}`

const CONTEXT = `This report is the ko-label research: the site's new checker computes the full besogo ko label of a leaf
(who takes first, approach moves, extra threats, 10kKO, double ko), and an analyst, reviewers and judges decided, on 57
leaves where its label disagreed with the tree's S[], who is right. The statuses and the owner's definitions are
explained in ${K}/BRIEF.md (read its sections "Statuses" and "The owner's definitions"). The owner's open questions are
on the question page; their parts are summarised in ${R}/ko_labels/design.md, section 13.`

const RULES = `Rules for everything you write:
- Never write a sequence of moves in the text, captions or headline (no "B18 C19", no "G19 E19 A18"). Every line of play goes into a diagram. Refer to diagrams as "Diagram 2" and to stones by their colour and number as drawn, "Black 7", "White 12"; the checker verifies that a stone with that number and colour exists in the same card or block. A single point may be named when it helps ("the ko at A19", "Black's eye at D18").
- Moves continue the move count from the start of the problem by default; keep that. Use 'pass' for a move elsewhere and 'A18!' for a retake after a ko threat elsewhere.
- Say only what the research supports. Add no new claims or lines. Where it records doubt (moderate confidence, undecided solver results, a caveat), keep that in a short clause. Where a later check corrected an earlier line (a reviewer, judge, fact checker or grading check), use the corrected line.
- Write for a strong go player who owns the site: plain, short sentences, no filler. S[] labels as typed in the SGF: S[KO-], S[KO+2], S[A+1KO+]; in prose "KO−" is fine.
- Headline under 30 words; paragraphs under 100 words; 1 to 4 diagrams per card or block.
- Change nothing except your own spec file.`

function writerPrompt(id) {
  return `You are writing one problem's part of a report for the owner of tsumego.com. The report shows every position and line as a numbered go diagram instead of lists of moves.
${CONTEXT}

Read first:
1. ${D}/dossiers/${id}.md: everything the research found on problem ${id} (the tree's leaves, each leaf's verdict and checks, the grading effect, and "What the spec needs").
2. The spec format: the header comment of ${R}/report.py (its first 12 lines).
3. A worked example from the first research report: /tmp/claude-0/proto/research/report/example-9957.json.

Write ${D}/specs/${id}.json. Then run:
${CHECK(`${D}/specs/${id}.json`)}
It replays every diagram (an illegal move is an error), prints each board as text (numbered stones as 7b or 12w, X and O for other stones, letters for labels) and lists errors. Look at the boards: they must show what your captions say. Fix and rerun until it ends with OK.

What to write:
- One card per leaf in the dossier, each with a headline, 1 to 4 diagrams, and 1 to 3 short paragraphs.
  - Diagram 1 is the tree's line to the leaf (start "", moves = the leaf's moves), so the reader sees which leaf it is.
  - The other diagrams show the decisive play from the leaf (start = the leaf's moves, moves = the continuation), from the analysis's key line and variations, or from the reviewers', judges' and fact checkers' lines where those settled or corrected it. Show the line that proves the right status and, where it helps, the line that shows why the tree's status fails.
  - A solver error (the tree is right) gets one or two diagrams and one short paragraph: why the tree is right and what misled the solver.
  - A decision leaf (its dossier names the decision and its options) shows the position the decision is about and the line under each option where they differ, and says which S[] each answer gives. Don't argue the decision itself; a separate introduction does that.
- The blocks listed under "What the spec needs":
  - grading: which moves the site marks differently after the fix (labels a, b on the points in a diagram of the position where the move is chosen, or numbered moves), and whether that is right, per the grading check.
  - tree_change: every change to make in the tree beyond the new S[]: moves to add (a diagram from the branching position whose numbered moves are the moves to add; the caption says which S[] goes at the end), moves to delete (label them), S[] to set on other leaves.
  - also_check: the other leaves the grading checker found, each with a diagram of the leaf and the S[] it suggests, with the checker's confidence.

${RULES}

Return one line: what you wrote and whether the checker says OK.`
}

function reviewerPrompt(id) {
  return `You are reviewing one problem's part of a report for the owner of tsumego.com before he reads it: problem ${id}. The spec ${D}/specs/${id}.json was written from the research dossier ${D}/dossiers/${id}.md.
${CONTEXT}
The spec format is in the header comment of ${R}/report.py (first 12 lines). Run the checker to see every diagram as a text board:
${CHECK(`${D}/specs/${id}.json`)}

Check every card and block against the dossier and the boards:
- Does each diagram show what its caption and the text say: the right stones, colours and numbers, the captures, who plays next?
- Does the text match the research: the right S[], the decisive reason, the caveats? Nothing invented; nothing important lost (a grading check's warning, a tree change to make, a doubt the research recorded, a corrected line).
- Is every leaf of the dossier covered, and every block the dossier asks for present?
- Is anything still given as a list of moves rather than a diagram?
- Is it plain, short and clear for a strong player?

Fix every problem directly in the spec file and rerun the checker until it says OK.

${RULES}

Return a short list of what you changed, or "no changes".`
}

function topicWriterPrompt(t) {
  return `You are writing the introduction of one decision in a report for the owner of tsumego.com: "${t.title}" (topic ${t.key}). The report shows every position and line as a numbered go diagram instead of lists of moves.
${CONTEXT}

Read first:
1. The topic in ${K}/out/topics_k.json (key ${t.key}): its text, its leaves (${t.cases.join(', ')}), each leaf's options and the suggestion. Its content is checked; your job is to turn it into diagrams and short paragraphs.
2. The research dossiers of the problems involved: ${t.problems.map(p => `${D}/dossiers/${p}.md`).join(', ')}. They hold each leaf's options, lines and grading effects ("Grading with S[...] instead").
3. The spec format: the header comment of ${R}/report.py (first 12 lines); a topic spec is {topic, text, diagrams, suggestion}, and every diagram in it names its "problem".

Write ${D}/specs/topic-${t.key}.json with topic "${t.key}": 2 to 4 paragraphs that explain the question, what each answer means on the board and which leaves it changes, and what it changes in the site's grading (from the dossiers; say so where no move's grading changes); 2 to 4 diagrams of the key positions and lines, taken from the leaves of this topic (each diagram names its problem); and the suggestion from the topic in one or two sentences. The leaves themselves follow in the report as cards, so don't repeat each one; show what the reader needs to decide.
Then run:
${CHECK(`${D}/specs/topic-${t.key}.json`)}
and fix until it says OK. Look at the printed boards: they must show what your captions say.

${RULES}

Return one line: what you wrote and whether the checker says OK.`
}

function topicReviewerPrompt(t) {
  return `You are reviewing the introduction of one decision in a report for the owner of tsumego.com before he reads it: "${t.title}" (topic ${t.key}). The spec ${D}/specs/topic-${t.key}.json was written from the topic in ${K}/out/topics_k.json and the dossiers ${t.problems.map(p => `${D}/dossiers/${p}.md`).join(', ')}.
${CONTEXT}
Run the checker to see every diagram as a text board:
${CHECK(`${D}/specs/topic-${t.key}.json`)}

Check that the diagrams show what the captions and text say, that the text matches the research and the topic (options, which leaves each answer changes, what each changes on the site, the suggestion), that nothing is given as a list of moves, and that it is plain and clear for a strong player. Fix problems directly in the spec file and rerun the checker until OK.

${RULES}

Return a short list of what you changed, or "no changes".`
}

let results
if (args.mode === 'problems') {
  results = await pipeline(
    args.ids,
    (id) => agent(writerPrompt(id), { label: `write ${id}`, phase: 'Write' }),
    (written, id) => agent(reviewerPrompt(id), { label: `review ${id}`, phase: 'Review' }).then(review => ({ id, written, review })),
  )
} else if (args.mode === 'topics') {
  results = await pipeline(
    args.topics,
    (t) => agent(topicWriterPrompt(t), { label: `write topic ${t.key}`, phase: 'Write' }),
    (written, t) => agent(topicReviewerPrompt(t), { label: `review topic ${t.key}`, phase: 'Review' }).then(review => ({ key: t.key, written, review })),
  )
} else {
  throw new Error('args.mode must be problems or topics')
}
const done = results.filter(Boolean)
log(`${done.length} of ${(args.ids || args.topics).length} written and reviewed`)
return done
