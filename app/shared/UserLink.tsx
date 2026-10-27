//User link component with optional avatar and rank display
//Replaces renderLink() HTML string builder

import { Avatar } from './Avatar';

interface UserLinkProps
{
	userId: number;
	name: string | null;
	avatarUrl: string | null;
	rating: number | null;
}

function getReadableRankFromRating(rating: number | null): string
{
	if (rating == null)
		return '';
	const rank = getRankFromRating(rating);
	return getReadableRank(rank);
}

function getRankFromRating(rating: number): number
{
	if (rating < 2750)
		return Math.floor(Math.max((rating + 1050) / 100, 1));
	return Math.floor((rating - 2750) / 30) + 38;
}

function getReadableRank(rank: number): string
{
	if (rank <= 30)
		return `${31 - rank}k`;
	return `${rank - 30}d`;
}

export function UserLink({ userId, name, avatarUrl, rating }: UserLinkProps)
{
	if (!name)
		return <span>[account deleted]</span>;

	const rank = getReadableRankFromRating(rating);

	return (
		<a href={`/users/view/${userId}`}>
			<Avatar url={avatarUrl} fallbackUrl={`/avatar/${userId}`} />
			{name}
			{rank && ` ${rank}`}
		</a>
	);
}
