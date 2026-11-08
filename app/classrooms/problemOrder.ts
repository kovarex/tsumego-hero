import type { PickableProblem } from './classroomTypes';

export interface Collection
{
	id: number;
	title: string;
	problems: PickableProblem[];
}

//The problems grouped by their collection, the collections in the order they were first picked
//from and the problems of each in the order of their numbers.
export function groupByCollection(problems: PickableProblem[]): Collection[]
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

//The problems in the order they are given or printed: by collection, then by number.
export function orderedProblems(problems: PickableProblem[]): PickableProblem[]
{
	return groupByCollection(problems).flatMap(collection => collection.problems);
}
