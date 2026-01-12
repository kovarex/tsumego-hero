/**
 * User link component with optional avatar and rank display
 * Replaces renderUserLink() HTML string builder
 */

interface UserLinkProps {
	userId: number;
	name: string | null;
	externalId: string | null;
	picture: string | null;
	rating: number | null;
}

function getReadableRankFromRating(rating: number | null): string {
	if (rating === null) return '';
	const rank = getRankFromRating(rating);
	return getReadableRank(rank);
}

function getRankFromRating(rating: number): number {
	if (rating < 2750)
		return Math.floor(Math.max((rating + 1050) / 100, 1));
	return Math.floor((rating - 2750) / 30) + 38;
}

function getReadableRank(rank: number): string {
	if (rank <= 30)
		return `${31 - rank}k`;
	return `${rank - 30}d`;
}

export function UserLink({ userId, name, externalId, picture, rating }: UserLinkProps) {
	if (!name) return <span>[deleted user]</span>;

	const rank = getReadableRankFromRating(rating);
	
	// Google users have 'g__' prefix and show profile pictures
	const isGoogleUser = name.startsWith('g__') && externalId && picture;
	const displayName = isGoogleUser ? name.substring(3) : name;

	return (
		<a href={`/users/view/${userId}`}>
			{isGoogleUser && (
				<img className="google-profile-image" src={`/img/google/${picture}`} alt="" />
			)}
			{displayName}
			{rank && ` ${rank}`}
		</a>
	);
}
