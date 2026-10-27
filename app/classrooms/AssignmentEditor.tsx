import { useEffect, useMemo, useRef, useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { post } from '../shared/api';
import { ProblemButton } from './ProblemButton';
import { ProblemPicker } from './ProblemPicker';
import type { PickableProblem, SavedAssignment } from './classroomTypes';

interface AssignmentEditorProps
{
	assignmentId: number;
	classroomUrl: string;
	title: string;
	//The due day, YYYY-MM-DD.
	due: string;
	isPublished: boolean;
	maximumProblems: number;
	maximumTitleLength: number;
	//The picked problems in their order, see ClassroomAssignment::problems().
	problems: PickableProblem[];
}

interface Collection
{
	id: number;
	title: string;
	problems: PickableProblem[];
}

//The problems grouped by their collection, the collections in the order they were first picked
//from and the problems of each in the order of their numbers, which is the order the pupils get.
function groupByCollection(problems: PickableProblem[]): Collection[]
{
	const collections = new Map<number, Collection>();
	for (const problem of problems)
	{
		const collection = collections.get(problem.setId) ?? { id: problem.setId, title: problem.setTitle, problems: [] };
		collection.problems.push(problem);
		collections.set(problem.setId, collection);
	}
	for (const collection of collections.values())
		collection.problems.sort((a, b) => a.number - b.number || a.setConnectionId - b.setConnectionId);
	return [...collections.values()];
}

//The page where the teacher builds an assignment: picks problems from any collections in the
//picker, takes some out again, names it and sets the due day. A draft stays the teacher's until
//it is published; a published one can still be changed, and the pupils see the change.
export function AssignmentEditor(props: AssignmentEditorProps)
{
	const [title, setTitle] = useState(props.title);
	const [due, setDue] = useState(props.due);
	const [problems, setProblems] = useState<PickableProblem[]>(props.problems);
	const [isChanged, setIsChanged] = useState(false);
	const [pickerCollectionId, setPickerCollectionId] = useState<number | null | undefined>(undefined);

	const collections = useMemo(() => groupByCollection(problems), [problems]);
	const pickedTsumegoIds = useMemo(() => new Set(problems.map(problem => problem.tsumegoId)), [problems]);
	const pickedCountBySet = useMemo(
		() => new Map(collections.map(collection => [collection.id, collection.problems.length])),
		[collections]);

	// Leaving with unsaved changes asks first. The handler reads a ref, so the page can leave right
	// after a save, before the state of it is rendered.
	const isChangedRef = useRef(false);
	useEffect(() =>
	{
		const warn = (event: BeforeUnloadEvent) =>
		{
			if (isChangedRef.current)
				event.preventDefault();
		};
		window.addEventListener('beforeunload', warn);
		return () => window.removeEventListener('beforeunload', warn);
	}, []);

	const change = (update: () => void) =>
	{
		update();
		isChangedRef.current = true;
		setIsChanged(true);
	};

	const pick = (picked: PickableProblem[]) => change(() => setProblems(current =>
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
	}));
	const unpick = (unpicked: PickableProblem[]) => change(() => setProblems(current =>
	{
		const removed = new Set(unpicked.map(problem => problem.tsumegoId));
		return current.filter(problem => !removed.has(problem.tsumegoId));
	}));

	const save = useMutation({
		mutationFn: (publish: boolean) => post<SavedAssignment>(`/classes/save-assignment/${props.assignmentId}`, {
			title,
			due,
			setConnectionIds: collections.flatMap(collection => collection.problems.map(problem => problem.setConnectionId)),
			publish
		}),
		onSuccess: (saved, publish) =>
		{
			isChangedRef.current = false;
			setIsChanged(false);
			if (publish || props.isPublished)
				window.location.href = saved.classUrl;
		}
	});

	const problemCount = problems.length;
	const isTooLong = problemCount > props.maximumProblems;
	return (
		<div className="classes__editor">
			<section className="card classes__section">
				<div className="classes__form-row classes__form-row--two">
					<div className="form-field">
						<label className="form-field__label" htmlFor="assignment-title">Name</label>
						<input
							className="form-field__control"
							id="assignment-title"
							type="text"
							maxLength={props.maximumTitleLength}
							placeholder={collections.map(collection => collection.title).join(', ') || 'Named by its collections'}
							value={title}
							onChange={event => change(() => setTitle(event.target.value))}
						/>
					</div>
					<div className="form-field">
						<label className="form-field__label" htmlFor="assignment-due">Due</label>
						<input
							className="form-field__control"
							id="assignment-due"
							type="date"
							required
							value={due}
							onChange={event => change(() => setDue(event.target.value))}
						/>
					</div>
				</div>
			</section>

			<section className="card classes__section" id="assignment-problems">
				<div className="classes__section-header">
					<h2 className="classes__heading">
						Problems <span className="classes__count" data-problem-count={problemCount}>{problemCount}</span>
					</h2>
					<button className="btn" type="button" id="add-problems" onClick={() => setPickerCollectionId(null)}>Add problems</button>
				</div>
				{collections.length === 0 && (
					<p className="hint">No problems yet. Add them from your collections or the ones of the site, as many collections as you like.</p>
				)}
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
				{isTooLong && <p className="classes__error">An assignment has at most {props.maximumProblems} problems.</p>}
			</section>

			<div className="classes__editor-actions">
				{save.isError && <p className="classes__error">{save.error.message}</p>}
				{!save.isError && !isChanged && save.isSuccess && !props.isPublished && <p className="hint">Saved.</p>}
				<a className="btn btn--neutral" href={props.classroomUrl}>Back to the class</a>
				{props.isPublished
					? (
						<button className="btn" type="button" disabled={save.isPending || isTooLong || problemCount === 0} onClick={() => save.mutate(false)}>
							Save changes
						</button>
					)
					: (
						<>
							<button className="btn btn--neutral" type="button" disabled={save.isPending || isTooLong} onClick={() => save.mutate(false)}>
								Save draft
							</button>
							<button
								className="btn btn--success"
								type="button"
								id="publish-assignment"
								disabled={save.isPending || isTooLong || problemCount === 0}
								onClick={() => save.mutate(true)}
							>
								Publish to the class
							</button>
						</>
					)}
			</div>

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
		</div>
	);
}
