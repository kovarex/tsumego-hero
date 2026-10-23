import { useQuery } from '@tanstack/react-query';
import { get } from '../shared/api';
import { formatDate } from '../shared/dates';
import { ErrorMessage } from '../shared/ErrorMessage';
import { UserLink } from '../shared/UserLink';
import type { AssignmentProgress, ProgressCell } from './classroomTypes';

/** How often the table asks again, so the teacher sees the solves while the pupils play. */
const REFRESH_MILLISECONDS = 10000;

interface ClassroomProgressProps
{
	assignmentId: number;
	/** The teacher sees every pupil, a pupil only their own row. */
	isTeacher: boolean;
}

function misplaysText(misplays: number | undefined): string
{
	if (!misplays)
		return 'no misplay';
	return misplays === 1 ? '1 misplay' : `${misplays} misplays`;
}

function cellTitle(cell: ProgressCell | null): string
{
	if (cell === null)
		return 'Not tried yet';
	switch (cell.state)
	{
		case 'solved':
			return `Solved ${formatDate(cell.solvedAt ?? '')}, ${misplaysText(cell.misplays)}`;
		case 'late':
			return `Solved after the due day, ${formatDate(cell.solvedAt ?? '')}, ${misplaysText(cell.misplays)}`;
		case 'earlier':
			return 'Solved before it was assigned, not played since';
		case 'tried':
			return `Not solved yet, ${misplaysText(cell.misplays)}`;
	}
}

function cellText(cell: ProgressCell | null): string
{
	if (cell === null)
		return '';
	return cell.state === 'tried' ? String(cell.misplays ?? '') : '✓';
}

/**
 * The table of who solved which problem of an assignment: a row per pupil, a column per problem.
 * It asks the server again every few seconds, so the teacher watches the class work in real time.
 */
export function ClassroomProgress({ assignmentId, isTeacher }: ClassroomProgressProps)
{
	const progressQuery = useQuery({
		queryKey: ['classroom-progress', assignmentId],
		queryFn: () => get<AssignmentProgress>(`/classes/progress/${assignmentId}`),
		refetchInterval: REFRESH_MILLISECONDS,
		refetchOnWindowFocus: true
	});

	if (progressQuery.isError)
		return <ErrorMessage onRetry={() => progressQuery.refetch()} />;
	if (!progressQuery.data)
		return <p className="hint">Loading…</p>;

	const { problems, pupils } = progressQuery.data;
	if (pupils.length === 0)
		return <p className="hint">{isTeacher ? 'No pupils in the class yet.' : ''}</p>;

	return (
		<div className="classes__progress">
			<table className="classes__table">
				<thead>
					<tr>
						{isTeacher && <th className="classes__pupil">Pupil</th>}
						{problems.map(problem => (
							<th key={problem.setConnectionId}>
								<a href={`/${problem.setConnectionId}`}>{problem.number}</a>
							</th>
						))}
						<th>Solved</th>
					</tr>
				</thead>
				<tbody>
					{pupils.map(pupil => (
						<tr key={pupil.userId} data-user-id={pupil.userId}>
							{isTeacher && (
								<td className="classes__pupil">
									<UserLink userId={pupil.userId} name={pupil.name} avatarUrl={pupil.avatarUrl} rating={pupil.rating} />
								</td>
							)}
							{pupil.cells.map((cell, index) => (
								<td
									key={problems[index].setConnectionId}
									className={'classes__cell' + (cell ? ` classes__cell--${cell.state}` : '')}
									title={cellTitle(cell)}
								>
									{isTeacher
										? cellText(cell)
										: <a href={`/${problems[index].setConnectionId}`}>{cellText(cell) || '·'}</a>}
								</td>
							))}
							<td className="classes__solved-count">{pupil.solvedCount}/{problems.length}</td>
						</tr>
					))}
				</tbody>
			</table>
		</div>
	);
}
