/**
 * A comment's board position anchor, as stored in tsumego_comment.position:
 * "x/y/parentX/parentY/childX/childY/moveNumber/childCount/orientation|path",
 * where path lists the moves from the root to the node as "x/y+x/y+...".
 */
export function positionMoveNumber(position: string): number
{
	return parseInt(position.split('/')[6], 10);
}
