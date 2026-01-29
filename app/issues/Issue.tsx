import { useState } from 'react';
import { Comment } from '../comments/Comment';
import { CommentForm } from '../comments/CommentForm';
import { UserLink } from '../shared/UserLink';
import type { Comment as CommentType } from '../comments/commentTypes';
import { IssueStatus, type IssueStatusId, type Issue as IssueType } from './issueTypes';

interface IssueProps
{
	issue: IssueType;
	currentUserId: number | null;
	isAdmin: boolean;
	onDelete: (id: number) => void;
	onReply?: (issueId: number, text: string, position?: string) => Promise<void>; // Optional - not needed on list page
	onCloseReopen: (issueId: number, newStatus: IssueStatusId) => Promise<void>;
	// Optional props for list context
	showReplyForm?: boolean; // Default: true (show reply form)
	comments?: CommentType[]; // If provided, use these instead of issue.comments
	author?: { name: string }; // If provided, use this instead of issue.user_name
	isDraggingEnabled?: boolean; // Default: true (enable dragging on play page)
}

export function Issue({
	issue,
	currentUserId,
	isAdmin,
	onDelete,
	onReply,
	onCloseReopen,
	showReplyForm = true, // Default to showing reply form
	comments, // Optional override
	author, // Optional override
	isDraggingEnabled = true // Default to enabling dragging
}: IssueProps)
{
	const [reply, setReply] = useState({ show: false, submitting: false });
	const isOpen = issue.tsumego_issue_status_id === IssueStatus.OPEN;
	const canCloseReopen = isAdmin || currentUserId === issue.user_id;

	// Use provided comments/author or fall back to issue data
	const displayComments = comments ?? issue.comments ?? [];
	const authorName = author?.name ?? issue.user_name ?? '[deleted user]';

	const handleCloseReopen = async () =>
	{
		setReply(r => ({ ...r, submitting: true }));
		try
		{
			await onCloseReopen(issue.id, isOpen ? IssueStatus.CLOSED : IssueStatus.OPEN);
		}
		finally
		{
			setReply(r => ({ ...r, submitting: false }));
		}
	};

	const handleSubmitReply = async (text: string, position?: string) =>
	{
		if (!onReply) 
			return;
		setReply(r => ({ ...r, submitting: true }));
		try
		{
			await onReply(issue.id, text, position);
			setReply({ show: false, submitting: false });
		}
		finally
		{
			setReply(r => ({ ...r, submitting: false }));
		}
	};

	return (
		<div className={`tsumego-issue tsumego-issue--${isOpen ? 'opened' : 'closed'}`} data-issue-id={issue.id}>
			<div className="tsumego-issue__header">
				<span className="tsumego-issue__title">Issue #{issue.id}</span>
				<span className={`tsumego-issue__badge status--${isOpen ? 'opened' : 'closed'}`}>
					{isOpen ? '🔴' : '✅'} {isOpen ? 'Opened' : 'Closed'}
				</span>
				<span className="tsumego-issue__meta">
					by{' '}
					<UserLink
						userId={issue.user_id}
						name={authorName}
						externalId={issue.user_external_id}
						picture={issue.user_picture}
						rating={issue.user_rating}
					/>{' '}
					•<span className="tsumego-issue__date">{new Date(issue.created).toLocaleDateString()}</span>
				</span>
				{canCloseReopen && (
					<span className="tsumego-issue__actions">
						{isOpen ? (
							<button
								type="button"
								className="btn btn--success btn--small"
								onClick={handleCloseReopen}
								disabled={reply.submitting}
							>
								✓ Close Issue
							</button>
						) : (
							<button
								type="button"
								className="btn btn--warning btn--small"
								onClick={handleCloseReopen}
								disabled={reply.submitting}
							>
								↩ Reopen
							</button>
						)}
					</span>
				)}
			</div>

			<div className="tsumego-dnd__issue-dropzone" data-issue-id={issue.id}>
				{displayComments.map(c => (
					<Comment
						key={c.id}
						comment={c}
						currentUserId={currentUserId}
						isAdmin={isAdmin}
						onDelete={onDelete}
						showIssueContext={false}
						issueStatus={issue.tsumego_issue_status_id as IssueStatusId}
						isDraggingEnabled={isDraggingEnabled}
					/>
				))}
			</div>

			{showReplyForm && currentUserId && (
				<div className="tsumego-issue__reply-toggle">
					<button
						type="button"
						className="tsumego-issue__reply-btn"
						onClick={() => setReply(r => ({ ...r, show: !r.show }))}
					>
						💬 Reply to this issue
					</button>
				</div>
			)}

			{showReplyForm && reply.show && currentUserId && (
				<div className="tsumego-issue__reply-form">
					<CommentForm
						onSubmit={handleSubmitReply}
						isSubmitting={reply.submitting}
						showReportAsIssue={false}
						submitButtonText="Post Reply"
						placeholder="Write a reply..."
					/>
				</div>
			)}
		</div>
	);
}
