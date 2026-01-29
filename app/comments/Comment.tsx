import { UserLink } from '../shared/UserLink';
import type { Comment as CommentType } from './commentTypes';
import { IssueStatus, type IssueStatusId } from '../issues/issueTypes';
import dayjs from 'dayjs';

interface CommentProps
{
	comment: CommentType;
	currentUserId: number | null;
	isAdmin: boolean;
	onDelete: (id: number) => void;
	onMakeIssue?: (id: number) => void; // Optional - only available for standalone comments, not issue comments
	showIssueContext: boolean;
	issueStatus?: IssueStatusId; // tsumego_issue_status_id if comment is in an issue
	isDraggingEnabled?: boolean; // If false, hide drag handles entirely (e.g., on read-only pages)
}

// Component for a single Go coordinate span with hover handlers
function CoordSpan({ coord }: { coord: string })
{
	const handleMouseEnter = (e: React.MouseEvent) =>
	{
		if (typeof window.showCoordPopup === 'function') 
			window.showCoordPopup(coord, e.nativeEvent);
	};

	const handleMouseLeave = () =>
	{
		if (typeof window.hideCoordPopup === 'function') 
			window.hideCoordPopup();
	};

	return (
		<span
			className="go-coord"
			data-coord={coord}
			title="Hover to highlight on board"
			style={{ cursor: 'pointer', textDecoration: 'underline' }}
			onMouseEnter={handleMouseEnter}
			onMouseLeave={handleMouseLeave}
		>
			{coord}
		</span>
	);
}

// Parse comment text and return React nodes with coordinate highlighting
function renderCommentText(text: string | null | undefined): React.ReactNode[]
{
	if (!text) 
		return [];

	const coordPattern = /\b([A-HJ-T])(\d{1,2})\b/g;
	const parts: React.ReactNode[] = [];
	let lastIndex = 0;
	let match;

	while ((match = coordPattern.exec(text)) !== null)
	{
		const num = parseInt(match[2]);
		// Only valid Go coordinates (1-19)
		if (num >= 1 && num <= 19)
		{
			// Add text before coordinate
			if (match.index > lastIndex) 
				parts.push(text.substring(lastIndex, match.index));

			// Add coordinate component
			parts.push(<CoordSpan key={`coord-${match.index}`} coord={match[0]} />);
			lastIndex = match.index + match[0].length;
		}
	}

	// Add remaining text
	if (lastIndex < text.length) 
		parts.push(text.substring(lastIndex));

	return parts.length > 0 ? parts : [text];
}

export function Comment({
	comment,
	currentUserId,
	isAdmin,
	onDelete,
	onMakeIssue,
	showIssueContext,
	issueStatus,
	isDraggingEnabled = true
}: CommentProps)
{
	const canDelete = isAdmin || currentUserId === comment.user_id;
	// Make Issue button shows for admins on standalone comments (showIssueContext=true) that aren't already in an issue
	const canMakeIssue = isAdmin && showIssueContext && onMakeIssue;

	// User styling - admin comments use different class
	const commentColorClass = comment.isAdmin ? 'commentBox2' : 'commentBox1';

	// Parse comment text with coordinate highlighting
	const commentContent = renderCommentText(comment.text);

	// Determine if draggable
	// Dragging must be explicitly enabled (isDraggingEnabled=true, default on play page)
	// Admin can drag: standalone comments OR comments inside open issues (not closed)
	const canDrag = isDraggingEnabled && isAdmin && issueStatus !== IssueStatus.CLOSED;

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
								<img
									src="/img/positionIcon1.png"
									className="positionIcon1"
									onClick={() =>
									{
										if (!window.besogo?.editor) 
											return;

										// Parse position data
										const [mainPart, pathPart] = comment.position!.split('|');
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
											console.log('[Comment] Position not in tree, playing moves:', pathPart);
											const coords = pathPart.split('+').map((c: string) => c.split('/').map(Number));

											// Play each move in sequence
											for (const [x, y] of coords)
												// click will try to navigate first, then play if needed
												window.besogo.editor.click(x, y, false, false);
										}
									}}
									title="Show position"
									style={{ cursor: 'pointer' }}
								/>
							)}
							{canDelete && (
								<button className="deleteComment" onClick={() => onDelete(comment.id)}>
									Delete
								</button>
							)}
							{canMakeIssue && (
								<button className="tsumego-comment__make-issue-btn" onClick={() => onMakeIssue(comment.id)}>
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
