import { useEffect, useRef, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { get } from '../shared/api';
import { ErrorMessage } from '../shared/ErrorMessage';
import { ProblemButton } from './ProblemButton';
import type { CollectionCard, CollectionCards, CollectionProblems, PickableProblem } from './classroomTypes';

interface ProblemPickerProps
{
	//The collection to show the problems of right away, null to start at the list of collections.
	collectionId: number | null;
	//The problems of the assignment, which show as picked.
	pickedTsumegoIds: ReadonlySet<number>;
	//How many problems of each collection are picked, by collection id.
	pickedCountBySet: ReadonlyMap<number, number>;
	onPick: (problems: PickableProblem[]) => void;
	onUnpick: (problems: PickableProblem[]) => void;
	onClose: () => void;
}

//The popup where the teacher picks problems for an assignment: first a collection, from their own
//ones and the ones of the site, then the problems of it, clicked one by one or as a range of
//numbers. Every pick goes to the assignment at once, so the teacher can go back for another
//collection and pick from that one too.
export function ProblemPicker({ collectionId, pickedTsumegoIds, pickedCountBySet, onPick, onUnpick, onClose }: ProblemPickerProps)
{
	const dialogRef = useRef<HTMLDialogElement>(null);
	const [openCollectionId, setOpenCollectionId] = useState<number | null>(collectionId);

	useEffect(() =>
	{
		const dialog = dialogRef.current;
		if (dialog && !dialog.open)
			dialog.showModal();
	}, []);

	return (
		<dialog
			ref={dialogRef}
			className="classes__picker"
			aria-label="Pick problems"
			onClose={onClose}
			onClick={event =>
			{
				// a click on the backdrop lands on the dialog itself
				if (event.target === dialogRef.current)
					dialogRef.current.close();
			}}
		>
			<div className="classes__picker-body">
				{openCollectionId === null
					? <CollectionList pickedCountBySet={pickedCountBySet} onOpen={setOpenCollectionId} />
					: (
						<CollectionProblemList
							collectionId={openCollectionId}
							pickedTsumegoIds={pickedTsumegoIds}
							onPick={onPick}
							onUnpick={onUnpick}
							onBack={() => setOpenCollectionId(null)}
						/>
					)}
			</div>
			<div className="classes__picker-footer">
				<button className="btn" type="button" onClick={() => dialogRef.current?.close()}>Done</button>
			</div>
		</dialog>
	);
}

interface CollectionListProps
{
	pickedCountBySet: ReadonlyMap<number, number>;
	onOpen: (collectionId: number) => void;
}

function matches(card: CollectionCard, search: string): boolean
{
	return card.title.toLowerCase().includes(search.trim().toLowerCase());
}

function CollectionList({ pickedCountBySet, onOpen }: CollectionListProps)
{
	const [search, setSearch] = useState('');
	const collectionsQuery = useQuery({
		queryKey: ['classroom-collections'],
		queryFn: () => get<CollectionCards>('/classes/collections'),
		staleTime: Infinity
	});

	if (collectionsQuery.isError)
		return <ErrorMessage onRetry={() => collectionsQuery.refetch()} />;
	if (!collectionsQuery.data)
		return <p className="hint">Loading the collections…</p>;

	const own = collectionsQuery.data.own.filter(card => matches(card, search));
	const site = collectionsQuery.data.site.filter(card => matches(card, search));
	return (
		<>
			<div className="classes__picker-header">
				<h2 className="classes__heading">Pick a collection</h2>
				<input
					className="form-field__control classes__picker-search"
					type="search"
					placeholder="Search"
					aria-label="Search the collections"
					value={search}
					onChange={event => setSearch(event.target.value)}
				/>
			</div>
			{own.length > 0 && (
				<>
					<h3 className="classes__picker-group">Your collections</h3>
					<CollectionCardGrid cards={own} pickedCountBySet={pickedCountBySet} onOpen={onOpen} />
				</>
			)}
			{site.length > 0 && (
				<>
					<h3 className="classes__picker-group">Collections of the site</h3>
					<CollectionCardGrid cards={site} pickedCountBySet={pickedCountBySet} onOpen={onOpen} />
				</>
			)}
			{own.length === 0 && site.length === 0 && <p className="hint">No collection is called like that.</p>}
		</>
	);
}

interface CollectionCardGridProps
{
	cards: CollectionCard[];
	pickedCountBySet: ReadonlyMap<number, number>;
	onOpen: (collectionId: number) => void;
}

//The collections as the cards of the collections page (src/View/Elements/set_card.ctp).
function CollectionCardGrid({ cards, pickedCountBySet, onOpen }: CollectionCardGridProps)
{
	return (
		<div className="classes__picker-cards">
			{cards.map(card =>
			{
				const pickedCount = pickedCountBySet.get(card.id) ?? 0;
				return (
					<button
						key={card.id}
						type="button"
						className="set-card classes__picker-card"
						style={{ backgroundColor: card.color }}
						data-collection-id={card.id}
						onClick={() => onOpen(card.id)}
					>
						<div className="set-card__top">{card.title}</div>
						<div className="set-card__middle-left">{card.problemCount} {card.problemCount === 1 ? 'problem' : 'problems'}</div>
						{card.difficulty && <div className="set-card__middle-right">~{card.difficulty}</div>}
						<div className="set-card__bottom">{pickedCount > 0 ? `${pickedCount} picked` : ''}</div>
					</button>
				);
			})}
		</div>
	);
}

interface CollectionProblemListProps
{
	collectionId: number;
	pickedTsumegoIds: ReadonlySet<number>;
	onPick: (problems: PickableProblem[]) => void;
	onUnpick: (problems: PickableProblem[]) => void;
	onBack: () => void;
}

//An empty field of the range is its open end.
function parseNumber(value: string): number | null
{
	const number = parseInt(value, 10);
	return Number.isNaN(number) ? null : number;
}

function CollectionProblemList({ collectionId, pickedTsumegoIds, onPick, onUnpick, onBack }: CollectionProblemListProps)
{
	const [firstNumber, setFirstNumber] = useState('');
	const [lastNumber, setLastNumber] = useState('');
	const problemsQuery = useQuery({
		queryKey: ['classroom-collection-problems', collectionId],
		queryFn: () => get<CollectionProblems>(`/classes/collection-problems/${collectionId}`),
		staleTime: Infinity
	});

	const header = (title: string) => (
		<div className="classes__picker-header">
			<button className="btn btn--small btn--neutral" type="button" onClick={onBack}>‹ Collections</button>
			<h2 className="classes__heading">{title}</h2>
		</div>
	);

	if (problemsQuery.isError)
		return <>{header('')}<ErrorMessage onRetry={() => problemsQuery.refetch()} /></>;
	if (!problemsQuery.data)
		return <>{header('')}<p className="hint">Loading the problems…</p></>;

	const { collection, problems } = problemsQuery.data;
	const pickedCount = problems.filter(problem => pickedTsumegoIds.has(problem.tsumegoId)).length;
	const first = parseNumber(firstNumber);
	const last = parseNumber(lastNumber);
	const inRange = problems.filter(problem => (first === null || problem.number >= first) && (last === null || problem.number <= last));

	return (
		<>
			{header(collection.title)}
			<div className="classes__picker-tools">
				<span className="hint" data-picked-count={pickedCount}>{pickedCount} of {problems.length} picked</span>
				<form
					className="classes__picker-range"
					onSubmit={event =>
					{
						event.preventDefault();
						onPick(inRange);
					}}
				>
					<label>
						From
						<input className="form-field__control" type="number" min="1" name="first_number" placeholder="first" value={firstNumber} onChange={event => setFirstNumber(event.target.value)} />
					</label>
					<label>
						to
						<input className="form-field__control" type="number" min="1" name="last_number" placeholder="last" value={lastNumber} onChange={event => setLastNumber(event.target.value)} />
					</label>
					<button className="btn btn--small" type="submit" disabled={inRange.length === 0}>
						Pick {first === null && last === null ? 'all' : `${inRange.length}`}
					</button>
					<button className="btn btn--small btn--neutral" type="button" disabled={pickedCount === 0} onClick={() => onUnpick(problems)}>
						Take all out
					</button>
				</form>
			</div>
			<p className="hint">Click the problems to pick. Hover one to see the board, Ctrl+click opens it in a new tab.</p>
			<ul className="problem-nav__buttons classes__problems">
				{problems.map(problem => (
					<ProblemButton
						key={problem.setConnectionId}
						problem={problem}
						picked={pickedTsumegoIds.has(problem.tsumegoId)}
						onToggle={toggled => pickedTsumegoIds.has(toggled.tsumegoId) ? onUnpick([toggled]) : onPick([toggled])}
					/>
				))}
			</ul>
		</>
	);
}
