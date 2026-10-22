import { UserLink } from '../shared/UserLink';
import type { Comment as CommentType } from './commentTypes';
import { IssueStatus, type IssueStatusId } from '../issues/issueTypes';
import { useAuth } from '../shared/AuthContext';
import { formatDate } from '../shared/dates';
import type { CoordSequence } from '../types/global';
import { positionMoveNumber } from './boardPosition';
import { commentTextToBoardView, useRerenderOnBoardTransformation } from './boardView';
import { parseCoordinateReferences, type CoordColor } from './coordParser';

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
function CoordSpan({ coord, position, color, sequence }: { coord: string; position?: string | null; color?: CoordColor; sequence?: CoordSequence })
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

// Parse comment text and return React nodes with coordinate highlighting.
// Uses the coordinate parser so lowercase coords, sequences (M16-N17-M20),
// alternatives (F2/G1) and color markers are recognized consistently. The text
// is already converted to the board as the viewer sees it.
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
		const groupClass = `go-coord-group go-coord-group--${kind}`;
		const children: React.ReactNode[] = [];

		// For a sequence, each coordinate carries the full list of moves plus its
		// own index so hover can preview the moves before it. A lone coordinate is
		// a one-move sequence, so it carries this context too. Alternatives (e.g.
		// F2/G1) have no sequence context.
		const sequenceCoords = kind === 'sequence' ? tokens.map(t => t.coord) : undefined;
		// Per-move explicit colour (w/b) from the comment, or null to alternate.
		const sequenceColors = tokens.map(t => t.color ?? null);

		tokens.forEach((token, i) =>
		{
			if (i > 0) 
				children.push(separators[i - 1] || ' ');
			const sequence = sequenceCoords ? { coords: sequenceCoords, colors: sequenceColors, index: i } : undefined;
			children.push(
				<CoordSpan key={`coord-${key++}`} coord={token.coord} position={position} color={token.color} sequence={sequence} />
			);
		});

		return (
			<span key={`group-${key++}`} className={groupClass}>
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

	// The text is stored in the coordinates and colours of the SGF; show it as
	// the viewer's board is oriented, again whenever the board is flipped or rotated.
	useRerenderOnBoardTransformation();
	const commentContent = renderCommentText(commentTextToBoardView(comment.text || ''), comment.position);

	const anchoredMoveNumber = comment.position ? positionMoveNumber(comment.position) : null;

	// Determine if draggable
	// Dragging must be explicitly enabled (isDraggingEnabled=true, default on play page)
	// Admin can drag: standalone comments OR comments inside open issues (not closed)
	const canDrag = isDraggingEnabled && isAdmin && issueStatus !== IssueStatus.CLOSED;

	// Show the position this comment is anchored to on the board.
	const navigateToPosition = () =>
	{
		const editor = window.besogo?.editor;
		if (!editor || !comment.position || !editor.showCommentPosition(comment.position))
			return;
		(document.querySelector('.besogo-container') ?? document.getElementById('board'))?.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
										name={comment.user_display_name}
										avatarUrl={comment.user_avatar_url}
										rating={comment.user_rating}
									/>
									:
								</span>
								<br />
								<span className="comment__text">{commentContent}</span>
							</div>
						</td>
						<td align="right" className="sandboxTable2time">
							<time className="tsumego-comment__date" dateTime={comment.created}>
								{formatDate(comment.created)}
							</time>
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
