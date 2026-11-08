import { useMemo, useState } from 'react';
import { ProblemButton } from './ProblemButton';
import { ProblemPicker } from './ProblemPicker';
import { groupByCollection } from './problemOrder';
import type { PickableProblem } from './classroomTypes';

interface ProblemSelectionProps
{
	//The id of the section, for the links and tests that look for it.
	id: string;
	problems: PickableProblem[];
	//Changes the picked problems; the update gets the current ones.
	onChange: (update: (current: PickableProblem[]) => PickableProblem[]) => void;
	maximumProblems: number;
	//What is said when there are too many problems.
	tooManyMessage: string;
	//What is said while there are none.
	emptyHint: string;
}

//A selection of problems from any collections: the picked ones by collection, each of which can be
//taken out, and the picker that adds more (ProblemPicker). A class assignment is built with it
//(AssignmentEditor), and so is a sheet to print (PrintSelection).
export function ProblemSelection({ id, problems, onChange, maximumProblems, tooManyMessage, emptyHint }: ProblemSelectionProps)
{
	const [pickerCollectionId, setPickerCollectionId] = useState<number | null | undefined>(undefined);

	const collections = useMemo(() => groupByCollection(problems), [problems]);
	const pickedTsumegoIds = useMemo(() => new Set(problems.map(problem => problem.tsumegoId)), [problems]);
	const pickedCountBySet = useMemo(
		() => new Map(collections.map(collection => [collection.id, collection.problems.length])),
		[collections]);

	const pick = (picked: PickableProblem[]) => onChange(current =>
	{
		const present = new Set(current.map(problem => problem.tsumegoId));
		const result = [...current];
		for (const problem of picked)
			if (!present.has(problem.tsumegoId))
			{
				present.add(problem.tsumegoId);
				result.push(problem);
			}
		return result;
	});
	const unpick = (unpicked: PickableProblem[]) => onChange(current =>
	{
		const removed = new Set(unpicked.map(problem => problem.tsumegoId));
		return current.filter(problem => !removed.has(problem.tsumegoId));
	});

	const problemCount = problems.length;
	return (
		<section className="card classes__section" id={id}>
			<div className="classes__section-header">
				<h2 className="classes__heading">
					Problems <span className="classes__count" data-problem-count={problemCount}>{problemCount}</span>
				</h2>
				<button className="btn" type="button" id="add-problems" onClick={() => setPickerCollectionId(null)}>Add problems</button>
			</div>
			{collections.length === 0 && <p className="hint">{emptyHint}</p>}
			{collections.map(collection => (
				<div className="classes__editor-collection" key={collection.id} data-collection-id={collection.id}>
					<div className="classes__editor-collection-header">
						<span>
							<a href={`/sets/${collection.id}`} target="_blank" rel="noopener">{collection.title}</a>
							{' '}<span className="hint">{collection.problems.length}</span>
						</span>
						<span className="classes__actions">
							<button className="btn btn--small btn--neutral" type="button" onClick={() => setPickerCollectionId(collection.id)}>Pick more</button>
							<button className="btn btn--small btn--neutral" type="button" onClick={() => unpick(collection.problems)}>Take all out</button>
						</span>
					</div>
					<ul className="problem-nav__buttons classes__problems">
						{collection.problems.map(problem => (
							<ProblemButton key={problem.setConnectionId} problem={problem} picked onToggle={toggled => unpick([toggled])} />
						))}
					</ul>
				</div>
			))}
			{problemCount > maximumProblems && <p className="classes__error">{tooManyMessage}</p>}

			{pickerCollectionId !== undefined && (
				<ProblemPicker
					collectionId={pickerCollectionId}
					pickedTsumegoIds={pickedTsumegoIds}
					pickedCountBySet={pickedCountBySet}
					onPick={pick}
					onUnpick={unpick}
					onClose={() => setPickerCollectionId(undefined)}
				/>
			)}
		</section>
	);
}
