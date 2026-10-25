import { useEffect, useRef, useState, useMemo, useCallback } from 'react';
import { Issue } from '../issues/Issue';
import { Comment } from './Comment';
import { CommentForm } from './CommentForm';
import type { Comment as CommentType, CommentCounts } from './commentTypes';
import { IssueStatus, type IssueStatusId, type Issue as IssueType } from '../issues/issueTypes';
import type { BesogoNode } from '../types/global';
import { useCommentsQuery, useCommentMutations } from './useComments';
import { useSortableDnD } from './useSortableDnD';
import { CommentsListSkeleton } from './CommentSkeleton';
import { ErrorMessage } from '../shared/ErrorMessage';
import { useAuth } from '../shared/AuthContext';

interface CommentSectionProps
{
	tsumegoId: number;
	initialCounts: CommentCounts;
	/** Slim list of position-anchored comment positions, for eager tree badges. */
	initialAnchoredPositions?: string[];
}

export function CommentSection({ tsumegoId, initialCounts, initialAnchoredPositions }: CommentSectionProps)
{
	const { userId, isAdmin } = useAuth();
	const contentRef = useRef<HTMLDivElement>(null);

	// Local UI state (tabs and visibility)
	const [activeTab, setActiveTab] = useState<'open' | 'closed' | null>(null);
	const [hasEverOpened, setHasEverOpened] = useState(false); // Track if user clicked a tab

	// Anchor-scope filter: 'all' | 'general' (no position) | 'onboard' (current node)
	const [anchorFilter, setAnchorFilter] = useState<'all' | 'general' | 'onboard'>('all');
	// Ids of comments anchored to the currently-selected tree node
	const [activeNodeCommentIds, setActiveNodeCommentIds] = useState<Set<number>>(new Set());

	// React Query for comments data - only fetch when user clicks tab
	const commentsQuery = useCommentsQuery(tsumegoId, hasEverOpened);

	// Consolidated mutations with auto-invalidation (per TkDodo best practices)
	const {
		addMutation,
		deleteMutation,
		replyMutation,
		closeReopenMutation,
		makeIssueMutation,
		moveCommentMutation,
		invalidate
	} = useCommentMutations(tsumegoId);

	// Use query data as source of truth (empty until first fetch)
	const counts = commentsQuery.data?.counts ?? initialCounts;

	// Merge and sort issues + standalone comments chronologically (oldest first)
	const allItems = useMemo(() =>
	{
		// Extract inside memo to avoid stale dependencies
		const issues = commentsQuery.data?.issues ?? [];
		const standalone = commentsQuery.data?.standalone ?? [];
		const items: Array<{
			type: 'issue' | 'comment';
			created: string;
			data: IssueType | CommentType;
		}> = [];

		// Add issues - use EARLIEST comment date for sorting (not issue creation)
		issues.forEach(issue =>
		{
			let sortDate = issue.created; // Fallback to issue creation
			if (issue.comments && issue.comments.length > 0)
			{
				// Comments in issue are ASC, so first is earliest
				const earliestCommentDate = issue.comments[0].created;
				if (new Date(earliestCommentDate) < new Date(sortDate)) 
					sortDate = earliestCommentDate;
			}
			items.push({
				type: 'issue',
				created: sortDate,
				data: issue
			});
		});

		// Add standalone comments
		standalone.forEach(comment =>
		{
			items.push({
				type: 'comment',
				created: comment.created,
				data: comment
			});
		});

		// Sort by date (oldest first)
		items.sort((a, b) => new Date(a.created).getTime() - new Date(b.created).getTime());

		return items;
	}, [commentsQuery.data]);

	// Resolve position-anchored comments to their game-tree nodes.
	// This maps a besogo node -> ids of the comments anchored to it.
	const anchoredByNode = useMemo(() =>
	{
		const map = new Map<BesogoNode, number[]>();
		const data = commentsQuery.data;
		if (!data) 
			return map;

		const addComment = (comment: CommentType) =>
		{
			if (comment.position && window.besogo?.editor)
			{
				const node = window.besogo.editor.findNodeForPosition(comment.position);
				if (node)
				{
					const ids = map.get(node) ?? [];
					ids.push(comment.id);
					map.set(node, ids);
				}
			}
		};

		data.standalone.forEach(addComment);
		(data.issues ?? []).forEach(issue => (issue.comments ?? []).forEach(addComment));

		return map;
	}, [commentsQuery.data]);

	// Node -> comment count for tree badges. Populated eagerly from the slim
	// SSR positions so badges appear without opening the comments tab; once the
	// full comments load, the authoritative ids are used instead.
	const anchoredCountsByNode = useMemo(() =>
	{
		if (commentsQuery.data)
		{
			const map = new Map<BesogoNode, number>();
			anchoredByNode.forEach((ids, node) => map.set(node, ids.length));
			return map;
		}

		const map = new Map<BesogoNode, number>();
		if (window.besogo?.editor)
			(initialAnchoredPositions ?? []).forEach(position =>
			{
				const node = window.besogo.editor.findNodeForPosition(position);
				if (node) 
					map.set(node, (map.get(node) ?? 0) + 1);
			});
		return map;
	}, [commentsQuery.data, anchoredByNode, initialAnchoredPositions]);

	// Tell besogo which nodes have anchored comments so it can draw badges.
	useEffect(() =>
	{
		const editor = window.besogo?.editor;
		if (!editor) 
			return;

		editor.setAnchoredComments(anchoredCountsByNode);
	}, [anchoredCountsByNode]);

	// Keep the "On board" filter in sync with the board/editor position.
	useEffect(() =>
	{
		const editor = window.besogo?.editor;
		if (!editor) 
			return;

		const syncFromCurrent = () =>
		{
			const current = editor.getCurrent();
			// "Here & ahead": comments anchored to the current node AND the
			// descendant variations below it. Past moves (ancestors) are excluded
			// - you see them by navigating back, and the tree badges show where
			// they are. At the root this means everything.
			const ids = new Set<number>();
			const stack: BesogoNode[] = [current];
			while (stack.length)
			{
				const node = stack.pop()!;
				(anchoredByNode.get(node) ?? []).forEach(id => ids.add(id));
				(node.children ?? []).forEach(child => stack.push(child));
			}
			setActiveNodeCommentIds(ids);
		};

		const handleEditorChange = (msg: { navChange?: boolean; treeChange?: boolean }) =>
		{
			if (msg.navChange || msg.treeChange) 
				syncFromCurrent();
		};

		editor.addListener(handleEditorChange);
		syncFromCurrent();

		return () => editor.removeListener(handleEditorChange);
	}, [anchoredByNode, tsumegoId]);

	// Toggle tab helper - fetch on first click
	const toggleTab = (tab: 'open' | 'closed') =>
	{
		setHasEverOpened(true); // Enable query
		setActiveTab(current => (current === tab ? null : tab));
	};

	// Reset UI state when switching problems (intentional reset when prop changes)
	useEffect(() =>
	{
		// eslint-disable-next-line react-hooks/set-state-in-effect
		setActiveTab(null);
		setHasEverOpened(false);
	}, [tsumegoId]);

	// Move comment handler (passed to SortableJS hook)
	const moveCommentRef = useRef(moveCommentMutation.mutate);
	useEffect(() =>
	{
		moveCommentRef.current = moveCommentMutation.mutate;
	});
	const handleMoveComment = useCallback(
		async (commentId: number, targetIssueId: number | 'standalone') =>
			await moveCommentRef.current({ commentId, targetIssueId }),
		 
		[]
	);

	// Initialize SortableJS drag-and-drop (extracted to custom hook)
	useSortableDnD({
		containerRef: contentRef,
		isAdmin,
		tsumegoId,
		issues: commentsQuery.data?.issues ?? [],
		onMoveComment: handleMoveComment
	});

	// Handlers - mutations auto-invalidate via onSuccess
	const handleAdd = async (text: string, position?: string, reportAsIssue?: boolean) =>
	{
		await addMutation.mutateAsync({ data: { text, tsumego_id: tsumegoId, position, report_as_issue: reportAsIssue } });
	};

	const handleDelete = async (id: number) =>
	{
		if (!confirm('Delete this comment?')) 
			return;
		await deleteMutation.mutateAsync({ commentId: id });
	};

	const handleReply = async (issueId: number, text: string, position?: string) =>
	{
		await replyMutation.mutateAsync({ issueId, text, position });
	};

	const handleCloseReopen = async (issueId: number, newStatus: IssueStatusId) =>
	{
		await closeReopenMutation.mutateAsync({ issueId, newStatus });
	};

	const handleMakeIssue = (commentId: number) =>
		makeIssueMutation.mutate({ commentId });

	// Anchor-scope filter for a single comment
	const matchesAnchorFilter = (comment: CommentType | null | undefined) =>
	{
		if (anchorFilter === 'all') 
			return true;
		if (!comment) 
			return false;
		if (anchorFilter === 'general') 
			return !comment.position; // no anchor
		// 'onboard': a comment anchored to the currently-selected tree node
		return comment.position != null && activeNodeCommentIds.has(comment.id);
	};

	// Filtering (tab + anchor scope)
	const shouldShowItem = (item: IssueType | CommentType, type: 'issue' | 'standalone') =>
	{
		if (!activeTab) 
			return false;

		// Open / closed tab filter
		if (activeTab === 'open')
		{
			const tabOk = type === 'standalone' || (item as IssueType).tsumego_issue_status_id === IssueStatus.OPEN;
			if (!tabOk) 
				return false;
		}
		else if (type !== 'issue' || (item as IssueType).tsumego_issue_status_id !== IssueStatus.CLOSED)
			return false;

		// Anchor-scope filter
		if (anchorFilter === 'all') 
			return true;
		if (type === 'standalone')
			return matchesAnchorFilter(item as CommentType);

		// An issue is shown when at least one of its comments matches.
		return (item as IssueType).comments?.some(c => matchesAnchorFilter(c)) ?? false;
	};

	const showContent = activeTab !== null;
	const showForm = activeTab === 'open';
	const hasContent = counts.comments > 0 || counts.openIssues > 0;
	const commentsPart = counts.comments > 0 ? `${counts.comments} COMMENT${counts.comments > 1 ? 'S' : ''}` : '';
	const issuesPart = counts.openIssues > 0 ? `🔴 ${counts.openIssues} OPEN ISSUE${counts.openIssues > 1 ? 'S' : ''}` : '';

	let commentsTabText = 'COMMENTS';
	if (commentsPart && issuesPart) 
		commentsTabText = `${commentsPart} ${issuesPart}`;
	else if (commentsPart) 
		commentsTabText = commentsPart;
	else if (issuesPart) 
		commentsTabText = issuesPart;

	const closedCount = counts.issues - counts.openIssues;
	const closedTabText = closedCount === 0 ? 'CLOSED ISSUES' : `${closedCount} CLOSED ISSUE${closedCount > 1 ? 'S' : ''}`;

	// Number of items that pass the current tab + anchor filters (for empty-state)
	const visibleItemCount = allItems.filter(item =>
		shouldShowItem(item.data, item.type === 'issue' ? 'issue' : 'standalone')
	).length;

	return (
		<>
			<div className="tsumego-comments__tabs">
				<button
					className={`tsumego-comments__tab ${activeTab === 'open' ? 'active' : ''}${!hasContent ? ' tsumego-comments__tab--empty' : ''}`}
					data-filter="open"
					onClick={() => toggleTab('open')}
				>
					{commentsTabText}
				</button>
				<button
					className={`tsumego-comments__tab ${activeTab === 'closed' ? 'active' : ''}${closedCount === 0 ? ' tsumego-comments__tab--empty' : ''}`}
					data-filter="closed"
					onClick={() => toggleTab('closed')}
				>
					{closedTabText}
				</button>
			</div>

			<div className="tsumego-comments__content" id="msg2x" ref={contentRef} style={{ display: showContent ? '' : 'none' }}>
				{/* Skeleton loading on initial load */}
				{commentsQuery.isLoading && hasEverOpened && <CommentsListSkeleton />}

				{/* Error state */}
				{commentsQuery.isError && (
					<ErrorMessage
						message="Failed to load comments. Please try again."
						onRetry={invalidate}
					/>
				)}

				{/* Content (shows even during background refetch) */}
				{!commentsQuery.isLoading && commentsQuery.isSuccess && (
					<>
						<div className="tsumego-comments__anchor-filter" title="Filter comments by board position">
							<span className="tsumego-comments__anchor-label">Show:</span>
							<button
								className={`tsumego-comments__anchor-btn${anchorFilter === 'all' ? ' active' : ''}`}
								onClick={() => setAnchorFilter('all')}
								title="Show all comments"
							>
								All
							</button>
							<button
								className={`tsumego-comments__anchor-btn${anchorFilter === 'general' ? ' active' : ''}`}
								onClick={() => setAnchorFilter('general')}
								title="General discussion, not tied to a board position"
							>
								General
							</button>
							<button
								className={`tsumego-comments__anchor-btn${anchorFilter === 'onboard' ? ' active' : ''}`}
								onClick={() => setAnchorFilter('onboard')}
								title="Comments tied to this board position and the moves after it"
							>
								Here &amp; ahead
							</button>
						</div>

						{anchorFilter !== 'all' && visibleItemCount === 0 && (
							<div className="tsumego-comments__anchor-empty">
								{anchorFilter === 'onboard'
									? 'No comments on this position or the moves after it.'
									: 'No general comments.'}
							</div>
						)}

						{allItems.map(item =>
						{
							if (item.type === 'issue')
							{
								const issue = item.data as IssueType;
								return (
									<div
										key={`issue-${issue.id}`}
										style={{
											display: shouldShowItem(issue, 'issue') ? '' : 'none'
										}}
									>
										<Issue
											issue={issue}
											onDelete={handleDelete}
											onReply={handleReply}
											onCloseReopen={handleCloseReopen}
										/>
									</div>
								);
							}
							else
							{
								const comment = item.data as CommentType;
								return (
									<div
										className="tsumego-comment--standalone"
										key={`comment-${comment.id}`}
										style={{
											display: shouldShowItem(comment, 'standalone') ? '' : 'none'
										}}
									>
										<Comment
											comment={comment}
											onDelete={handleDelete}
											onMakeIssue={handleMakeIssue}
											showIssueContext={true}
										/>
									</div>
								);
							}
						})}

						{userId ? (
							<div style={{ display: showForm ? '' : 'none' }}>
								<h4>Add Comment</h4>
								<CommentForm onSubmit={handleAdd} isSubmitting={addMutation.isPending} />
							</div>
						) : (
							<div className="tsumego-comments__login-prompt" style={{ display: showForm ? '' : 'none' }}>
								<p>
									<a href="/users/login">Log in</a> to leave a comment.
								</p>
							</div>
						)}
					</>
				)}
			</div>
		</>
	);
}
