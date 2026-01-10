/**
 * Rating and rank utilities - TypeScript port of Rating.php
 */

export function getReadableRankFromRating(rating: number | null): string {
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

/**
 * Render user link with optional avatar and rank.
 * Equivalent to User::renderLink() in PHP.
 */
export function renderUserLink(
	userId: number,
	name: string | null,
	externalId: string | null,
	picture: string | null,
	rating: number | null
): string {
	if (!name) return '[deleted user]';

	const rank = getReadableRankFromRating(rating);
	let image = '';

	// Google users have 'g__' prefix and show profile pictures
	if (name.startsWith('g__') && externalId && picture) {
		image = `<img class="google-profile-image" src="/img/google/${picture}">`;
		name = name.substring(3); // Remove 'g__' prefix
	}

	const rankText = rank ? ` ${rank}` : '';
	return `<a href="/users/view/${userId}">${image}${escapeHtml(name)}${rankText}</a>`;
}

function escapeHtml(text: string): string {
	const div = document.createElement('div');
	div.textContent = text;
	return div.innerHTML;
}
