//User link component with optional avatar and rank display
//Replaces renderLink() HTML string builder

import { Avatar } from './Avatar';
import { getReadableRankFromRating } from './rank';

interface UserLinkProps
{
	userId: number;
	name: string | null;
	avatarUrl: string | null;
	rating: number | null;
}

function readableRank(rating: number | null): string
{
	return rating == null ? '' : getReadableRankFromRating(rating);
}

export function UserLink({ userId, name, avatarUrl, rating }: UserLinkProps)
{
	if (!name)
		return <span>[account deleted]</span>;

	const rank = readableRank(rating);

	return (
		<a href={`/users/view/${userId}`}>
			<Avatar url={avatarUrl} fallbackUrl={`/avatar/${userId}`} />
			{name}
			{rank && ` ${rank}`}
		</a>
	);
}
