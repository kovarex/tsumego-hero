/**
 * The classes of the avatar element.
 *
 * A board keeps its corners, a picture of a face is round, and the size of both
 * lives in components.css. The url is built by the server, which is what decides
 * the drawing, so the shape is read from it instead of being repeated at every
 * call site.
 */
export function avatarClass(url: string | null): string
{
	return url && url.startsWith('/avatar/') ? 'user-avatar user-avatar--board' : 'user-avatar';
}
