import { UserLink } from '../shared/UserLink';
import type { Comment as CommentType } from './commentTypes';
import { IssueStatus, type IssueStatusId } from '../issues/issueTypes';
import { useAuth } from '../shared/AuthContext';
import dayjs from 'dayjs';
import { parseCoordinateReferences } from './coordParser';

interface CommentProps
{
	comment: CommentType;
	onDelete: (id: number) => void;
	onMakeIssue?: (id: number) => void; // Optional - only available for standalone comments, not issue comments
	showIssueContext: boolean;
	issueStatus?: IssueStatusId; // tsumego_issue_status_id if comment is in an issue
	isDraggingEnabled?: boolean; // If false, hide drag handles entirely (e.g., on read-only pages)
}

// Component for a single Go coordinate span with hover handlers.
// When the comment is anchored to a board position, the hover preview shows
// that anchored position so the coordinate is interpreted "from the comment".
function CoordSpan({ coord, position, color, sequence }: { coord: string; position?: string | null; color?: 'b' | 'w'; sequence?: { coords: string[]; index: number } })
{
	const handleMouseEnter = (e: React.MouseEvent) =>
	{
		window.showCoordPopup(coord, e.nativeEvent, position ?? undefined, sequence);
	};

	const handleMouseLeave = () =>
	{
		window.hideCoordPopup();
	};

	return (
		<span
			className={`go-coord${color ? ` go-coord--${color}` : ''}`}
			data-coord={coord}
			data-color={color ?? ''}
			title="Hover to highlight on board"
			onMouseEnter={handleMouseEnter}
			onMouseLeave={handleMouseLeave}
		>
			{coord}
		</span>
	);
}

// Re-label comment coordinates written in the commenter's board orientation so
// they match the currently-displayed board (e.g. "T3" -> "A17" when the board
// is shown top-left). Falls back to the authored labels when the board is not
// ready or the orientation cannot be determined uniquely.
function transformCoords(coords: string[], position?: string | null): string[]
{
	const editor = typeof window.besogo !== 'undefined' && window.besogo ? window.besogo.editor : null;
	if (!editor || typeof editor.transformCommentCoords !== 'function')
		return coords;
	try
	{
		return editor.transformCommentCoords(coords, position ?? undefined);
	}
	catch
	{
		return coords;
	}
}

// Parse comment text and return React nodes with coordinate highlighting.
// Uses the coordinate parser so lowercase coords, sequences (M16-N17-M20),
// alternatives (F2/G1) and color markers are recognized consistently.
function renderCommentText(text: string | null | undefined, position?: string | null): React.ReactNode[]
{
	if (!text) 
		return [];

	const slices = parseCoordinateReferences(text);
	let key = 0;

	return slices.map(slice =>
	{
		if (slice.type === 'text') 
			return slice.text;

		const { kind, tokens, separators } = slice;
		const groupClass = ` go-coord-group go-coord-group--${kind}`;
		const children: React.ReactNode[] = [];

		// Transform the authored labels to the displayed board orientation so the
		// comment text agrees with the board the reader sees.
		const boardCoords = transformCoords(tokens.map(t => t.coord), position);

		// For a sequence, each coordinate carries the full list of moves plus its
		// own index so hover can preview the moves before it. A lone coordinate is
		// a one-move sequence, so it carries this context too. Alternatives (e.g.
		// F2/G1) have no sequence context.
		const sequenceCoords = kind === 'sequence' ? boardCoords : undefined;
		// Per-move explicit colour (w/b) from the comment, or null to alternate.
		const sequenceColors = kind === 'sequence' ? tokens.map(t => t.color ?? null) : undefined;

		tokens.forEach((token, i) =>
		{
			if (i > 0) 
				children.push(separators[i - 1] || ' ');
			const coord = boardCoords[i] ?? token.coord;
			const sequence = sequenceCoords ? { coords: sequenceCoords, colors: sequenceColors, index: i } : undefined;
			children.push(
				<CoordSpan key={`coord-${key++}`} coord={coord} position={position} color={token.color} sequence={sequence} />
			);
		});

		return (
			<span key={`group-${key++}`} className={groupClass.trim()}>
				{children}
			</span>
		);
	});
}

export function Comment({
	comment,
	onDelete,
	onMakeIssue,
	showIssueContext,
	issueStatus,
	isDraggingEnabled = true
}: CommentProps)
{
	const { userId, isAdmin } = useAuth();
	const canDelete = isAdmin || userId === comment.user_id;
	// Make Issue button shows for admins on standalone comments (showIssueContext=true) that aren't already in an issue
	const canMakeIssue = isAdmin && showIssueContext && onMakeIssue;

	// User styling - admin comments use different class
	const commentColorClass = comment.isAdmin ? 'commentBox2' : 'commentBox1';

	// Legacy comments had a literal "[current position]" marker baked into the
	// message; the anchor is now stored solely in `position`, so strip it here.
	const displayText = (comment.text || '').replace(/\[current position\]/g, '');

	// Parse comment text with coordinate highlighting. Pass the comment's anchor
	// so hovering a coordinate previews the anchored board position, not the
	// position the user is currently viewing.
	const commentContent = renderCommentText(displayText, comment.position);

	// Move number of the anchored position (position format: x/y/.../moveNumber/...)
	const anchoredMoveNumber = comment.position ? parseInt(comment.position.split('/')[6], 10) : null;

	// Determine if draggable
	// Dragging must be explicitly enabled (isDraggingEnabled=true, default on play page)
	// Admin can drag: standalone comments OR comments inside open issues (not closed)
	const canDrag = isDraggingEnabled && isAdmin && issueStatus !== IssueStatus.CLOSED;

	// Navigate the board to the node this comment is anchored to.
	const navigateToPosition = () =>
	{
		if (!window.besogo?.editor || !comment.position) 
			return;

		// Parse position data
		const [mainPart, pathPart] = comment.position.split('|');
		const parts = mainPart.split('/');

		if (parts.length < 9) 
			return;

		// Extract position parameters
		const x = parseInt(parts[0]);
		const y = parseInt(parts[1]);
		const pX = parseInt(parts[2]);
		const pY = parseInt(parts[3]);
		const cX = parseInt(parts[4]);
		const cY = parseInt(parts[5]);
		const mNum = parseInt(parts[6]);
		const cNum = parseInt(parts[7]);
		const orientation = parts[8];

		// Try commentPosition first (handles tree positions)
		window.commentPosition(x, y, pX, pY, cX, cY, mNum, cNum, orientation);

		// Check if we're still at root (commentPosition failed to find position)
		const currentNode = window.besogo.editor.getCurrent();
		const isAtRoot = currentNode.moveNumber === 0;

		// If still at root and we have a path, try playing the moves
		if (isAtRoot && pathPart)
		{
			const coords = pathPart.split('+').map(c => c.split('/').map(Number));

			// Play each move in sequence (click navigates first, then plays if needed)
			for (const [mx, my] of coords)
				window.besogo.editor.click(mx, my, false, false);
		}
	};

	return (
		<div className={`tsumego-comment${canDrag ? ' tsumego-comment--draggable' : ''}`} data-comment-id={comment.id}>
			<div className="sandboxComment">
				<table className="sandboxTable2" width="100%">
					<tr>
						{canDrag && (
							<td className="tsumego-comment__drag-handle-cell">
								<span className="tsumego-comment__drag-handle" title="Drag to move comment">
									☰
								</span>
							</td>
						)}
						<td>
							<div className={commentColorClass}>
								<span className="tsumego-comment__author">
									<UserLink
										userId={comment.user_id}
										name={comment.user_name}
										rating={comment.user_rating}
										externalId={comment.user_external_id}
										picture={comment.user_picture}
									/>
									:
								</span>
								<br />
								<span className="comment__text">{commentContent}</span>
							</div>
						</td>
						<td align="right" className="sandboxTable2time">
							<span className="tsumego-comment__date">{dayjs(comment.created).format('MMM. D, YYYY HH:mm')}</span>
							{comment.position && (
								<button
									type="button"
									className="tsumego-comment__position-label"
									onClick={navigateToPosition}
									title="Go to this position on the board"
								>
									📌{anchoredMoveNumber ? ` Move ${anchoredMoveNumber}` : ' Position'}
								</button>
							)}
							{canDelete && (
								<button className="deleteComment" onClick={() => onDelete(comment.id)}>
									Delete
								</button>
							)}
							{canMakeIssue && (
								<button className="btn btn--small" onClick={() => onMakeIssue(comment.id)}>
									📋 Make Issue
								</button>
							)}
						</td>
					</tr>
				</table>
			</div>
		</div>
	);
}
