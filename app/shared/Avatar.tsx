//The avatar of a player as an image element. Every place that shows an avatar
//goes through this, so the classes, the lazy loading and the referrer policy
//stay the same everywhere. A board keeps its corners, a picture stays round.

import type { SyntheticEvent } from 'react';
import { avatarClass } from './avatar';

interface AvatarProps
{
	url: string | null;
	//The board of the account, shown when a picture of another host is gone.
	fallbackUrl?: string;
}

//A picture of another host can stop working (a player who removes the photo of
//their Google account), which the browser draws as a broken image. The board of
//the account is always there, so it takes over, with the square corners the
//board class gives it.
function fallBackToBoard(event: SyntheticEvent<HTMLImageElement>, fallbackUrl: string): void
{
	event.currentTarget.onerror = null;
	event.currentTarget.classList.add('user-avatar--board');
	event.currentTarget.src = fallbackUrl;
}

export function Avatar({ url, fallbackUrl }: AvatarProps)
{
	if (!url)
		return null;

	return <img src={url} alt="" className={avatarClass(url)} loading="lazy" decoding="async" referrerPolicy="no-referrer" onError={fallbackUrl ? (event) => fallBackToBoard(event, fallbackUrl) : undefined} />;
}
