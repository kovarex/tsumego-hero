//The go ranks of the ratings, as Rating.php and webroot/js/Rating.js count them: rank 1 is 30k, 30 is 1k, 31 is 1d.
//A rank is 100 rating points wide up to 7d (rank 37), and 30 from 8d up, as pro ranks are.

export function getRankFromRating(rating: number): number
{
	if (rating < 2750)
		return Math.floor(Math.max((rating + 1050) / 100, 1));
	return Math.floor((rating - 2750) / 30) + 38;
}

export function getReadableRank(rank: number): string
{
	if (rank <= 30)
		return `${31 - rank}k`;
	return `${rank - 30}d`;
}

export function getReadableRankFromRating(rating: number): string
{
	return getReadableRank(getRankFromRating(rating));
}

//The lowest rating of the rank, its lower edge: 6k (rank 25) is 1450
export function getRankMinimalRating(rank: number): number
{
	if (rank <= 38)
		return 100 * rank - 1050;
	return (rank - 38) * 30 + 2750;
}

//The ticks of a value axis at the lower edges of the ranks, from an edge at or under min to one at or over max, at
//most maxCount of them: every rank, or every 2nd, 5th or 10th one counted from 1d, so 1d stays a tick when it is
//on the axis.
export function rankTicks(min: number, max: number, maxCount: number): number[]
{
	const lowest = getRankFromRating(min);
	const highest = Math.max(lowest + 1, getRankFromRating(max) + (getRankMinimalRating(getRankFromRating(max)) < max ? 1 : 0));
	for (const step of [1, 2, 5, 10, 20])
	{
		const first = 31 + Math.floor((lowest - 31) / step) * step;
		const last = 31 + Math.ceil((highest - 31) / step) * step;
		if ((last - first) / step + 1 <= Math.max(2, maxCount) || step === 20)
		{
			const edges: number[] = [];
			for (let rank = Math.max(1, first); rank <= last; rank += step)
				edges.push(getRankMinimalRating(rank));
			if (edges.length < 2)
				return [edges[0], getRankMinimalRating(Math.max(1, first) + step)];
			// from 8d up the ranks are 30 wide, not 100, so their edges crowd together: an edge too close to the one
			// under it is left out, the top one stays as the end of the axis
			const gap = (edges[edges.length - 1] - edges[0]) / Math.max(2, maxCount) * 0.6;
			const ticks = [edges[0]];
			for (const edge of edges.slice(1, -1))
				if (edge - ticks[ticks.length - 1] >= gap)
					ticks.push(edge);
			const top = edges[edges.length - 1];
			if (top - ticks[ticks.length - 1] < gap && ticks.length > 1)
				ticks.pop();
			ticks.push(top);
			return ticks;
		}
	}
	return [];
}
