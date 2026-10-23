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
	number: number;
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
