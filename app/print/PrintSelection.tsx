import { useEffect, useMemo, useState } from 'react';
import { ProblemSelection } from '../classrooms/ProblemSelection';
import { orderedProblems } from '../classrooms/problemOrder';
import type { PickableProblem } from '../classrooms/classroomTypes';

interface PrintSelectionProps
{
	//The problems to start with, see PrintController::index().
	problems: PickableProblem[];
	problemsPerPageChoices: number[];
	defaultProblemsPerPage: number;
	maximumProblems: number;
}

//The page where a player picks problems to print, the same way a teacher picks them for a class,
//chooses how many go on a page, whether an answer key and room for notes come along, and opens the
//sheet in a new tab to print it (PrintController::sheet()). The picked problems stay in the address,
//so a reload keeps them.
export function PrintSelection(props: PrintSelectionProps)
{
	const [problems, setProblems] = useState<PickableProblem[]>(props.problems);
	const [problemsPerPage, setProblemsPerPage] = useState(props.defaultProblemsPerPage);
	const [withAnswers, setWithAnswers] = useState(false);
	const [withNotes, setWithNotes] = useState(false);

	const setConnectionIds = useMemo(() => orderedProblems(problems).map(problem => problem.setConnectionId).join(','), [problems]);

	useEffect(() =>
	{
		window.history.replaceState(null, '', window.location.pathname + (setConnectionIds === '' ? '' : '?problems=' + setConnectionIds));
	}, [setConnectionIds]);

	// the ids are digits and commas, nothing to escape
	const sheetUrl = `/print/sheet?problems=${setConnectionIds}&perPage=${problemsPerPage}` + (withAnswers ? '&answers=1' : '') + (withNotes ? '&notes=1' : '');
	const canPrint = problems.length > 0 && problems.length <= props.maximumProblems;

	return (
		<div className="classes__editor">
			<ProblemSelection
				id="print-problems"
				problems={problems}
				onChange={setProblems}
				maximumProblems={props.maximumProblems}
				tooManyMessage={`At most ${props.maximumProblems} problems can be printed at once.`}
				emptyHint="No problems yet. Add them from your collections or the ones of the site, as many collections as you like."
			/>

			<section className="card classes__section print-options">
				<h2 className="classes__heading">The sheet</h2>
				<div className="print-options__fields">
					<label className="print-options__field">
						Problems on a page
						<select
							className="form-field__control"
							name="per_page"
							value={problemsPerPage}
							onChange={event => setProblemsPerPage(Number(event.target.value))}
						>
							{props.problemsPerPageChoices.map(choice => <option key={choice} value={choice}>{choice}</option>)}
						</select>
					</label>
					<label className="print-options__field">
						<input type="checkbox" name="answers" checked={withAnswers} onChange={event => setWithAnswers(event.target.checked)} />
						Answer key at the end
					</label>
					<label className="print-options__field">
						<input type="checkbox" name="notes" checked={withNotes} onChange={event => setWithNotes(event.target.checked)} />
						Room for notes
					</label>
				</div>
			</section>

			<div className="classes__editor-actions">
				<a
					className={'btn btn--success' + (canPrint ? '' : ' btn--disabled')}
					id="open-print-sheet"
					href={canPrint ? sheetUrl : undefined}
					aria-disabled={!canPrint}
					target="_blank"
					rel="noopener"
				>
					Print
				</a>
			</div>
		</div>
	);
}
