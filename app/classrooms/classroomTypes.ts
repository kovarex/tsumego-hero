/** What /classes/progress/<assignment id> answers, see ClassroomAssignment::progress(). */

export type CellState = 'solved' | 'late' | 'earlier' | 'tried';

export interface ProgressCell
{
	state: CellState;
	/** When the problem was first solved since it was assigned, for solved and late. */
	solvedAt?: string;
	/** Misplays since it was assigned, for solved, late and tried. */
	misplays?: number;
}

export interface ProgressProblem
{
	setConnectionId: number;
	/** Its number in its collection. */
	number: number;
	setId: number;
	setTitle: string;
}

export interface ProgressPupil
{
	userId: number;
	name: string | null;
	avatarUrl: string | null;
	rating: number;
	solvedCount: number;
	/** One per problem, in the order of the problems, null for a problem not touched since it was assigned. */
	cells: (ProgressCell | null)[];
}

export interface AssignmentProgress
{
	problems: ProgressProblem[];
	pupils: ProgressPupil[];
}

/** The stones of the preview in the tooltip of a problem button, see TsumegoButton::sgfToPreviewData(). */
export interface ProblemPreview
{
	black: string;
	white: string;
	xMax: number;
	yMax: number;
	boardSize: number;
}

/** A problem of a collection as the buttons of the assignment editor show it, see ClassroomAssignment::problemPayload(). */
export interface PickableProblem
{
	setConnectionId: number;
	tsumegoId: number;
	/** Its number in its collection. */
	number: number;
	setId: number;
	setTitle: string;
	preview: ProblemPreview | null;
}

/** A collection to pick problems from, see ClassroomAssignment::collectionCards(). */
export interface CollectionCard
{
	id: number;
	title: string;
	color: string;
	problemCount: number;
	/** The rank of the average problem, null when unknown. */
	difficulty: string | null;
}

/** What /classes/collections answers. */
export interface CollectionCards
{
	own: CollectionCard[];
	site: CollectionCard[];
}

/** What /classes/collection-problems/<collection id> answers. */
export interface CollectionProblems
{
	collection: { id: number; title: string };
	problems: PickableProblem[];
}

/** What /classes/save-assignment/<assignment id> answers. */
export interface SavedAssignment
{
	problemCount: number;
	published: string | null;
	classUrl: string;
}
