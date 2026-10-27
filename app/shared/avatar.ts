//The classes of the avatar element.
//A board keeps its corners, a picture of a face is round, and the size of both
//lives in components.css. The url is built by the server, which is what decides
//the drawing, so the shape is read from it instead of being repeated at every
//call site.
//Where the drawings of the boards live, the urls the server builds (Avatar::URL_PREFIX).
const BOARD_URL_PREFIX = '/board/';

export function avatarClass(url: string | null): string
{
	return url && url.startsWith(BOARD_URL_PREFIX) ? 'user-avatar user-avatar--board' : 'user-avatar';
}
