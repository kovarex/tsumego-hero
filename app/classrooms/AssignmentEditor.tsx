import { useEffect, useMemo, useRef, useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { post } from '../shared/api';
import { ProblemSelection } from './ProblemSelection';
import { groupByCollection, orderedProblems } from './problemOrder';
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

//The page where the teacher builds an assignment: picks problems from any collections in the
//picker, takes some out again, names it and sets the due day. A draft stays the teacher's until
//it is published; a published one can still be changed, and the pupils see the change.
export function AssignmentEditor(props: AssignmentEditorProps)
{
	const [title, setTitle] = useState(props.title);
	const [due, setDue] = useState(props.due);
	const [problems, setProblems] = useState<PickableProblem[]>(props.problems);
	const [isChanged, setIsChanged] = useState(false);

	const collections = useMemo(() => groupByCollection(problems), [problems]);
	//The order the pupils get them in
	const setConnectionIds = useMemo(() => orderedProblems(problems).map(problem => problem.setConnectionId), [problems]);

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

	const save = useMutation({
		mutationFn: (publish: boolean) => post<SavedAssignment>(`/classes/save-assignment/${props.assignmentId}`, {
			title,
			due,
			setConnectionIds,
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

			<ProblemSelection
				id="assignment-problems"
				problems={problems}
				onChange={update => change(() => setProblems(update))}
				maximumProblems={props.maximumProblems}
				tooManyMessage={`An assignment has at most ${props.maximumProblems} problems.`}
				emptyHint="No problems yet. Add them from your collections or the ones of the site, as many collections as you like."
			/>

			<div className="classes__editor-actions">
				{save.isError && <p className="classes__error">{save.error.message}</p>}
				{!save.isError && !isChanged && save.isSuccess && !props.isPublished && <p className="hint">Saved.</p>}
				<a className="btn btn--neutral" href={props.classroomUrl}>Back to the class</a>
				{problemCount > 0 && (
					<a className="btn btn--neutral" id="print-assignment" href={`/print?problems=${setConnectionIds.join(',')}`} target="_blank" rel="noopener">Print</a>
				)}
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

		</div>
	);
}
