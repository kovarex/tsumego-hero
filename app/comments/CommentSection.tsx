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
import { updateMoreSummary } from './moreSummary';

type SectionItem =
	| { type: 'issue'; created: string; data: IssueType }
	| { type: 'comment'; created: string; data: CommentType };

//The label of the tab of the comments and the open issues, like "3 COMMENTS 🔴 1 OPEN ISSUE".
function openTabText(counts: CommentCounts): string
{
	const parts = [];
	if (counts.comments > 0)
		parts.push(`${counts.comments} comment${counts.comments > 1 ? 's' : ''}`);
	if (counts.openIssues > 0)
		parts.push(`🔴 ${counts.openIssues} open issue${counts.openIssues > 1 ? 's' : ''}`);
	return parts.length > 0 ? parts.join(' ') : 'Comments';
}

function closedTabText(closedCount: number): string
{
	return closedCount === 0 ? 'Closed issues' : `${closedCount} closed issue${closedCount > 1 ? 's' : ''}`;
}

interface CommentSectionProps
{
	tsumegoId: number;
	initialCounts: CommentCounts;
	//Slim list of position-anchored comment positions, for eager tree badges.
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

	// Every change of the comments (an action here, or a refetch) brings new counts for the summary on More
	const loadedCounts = commentsQuery.data?.counts;
	useEffect(() =>
	{
		if (loadedCounts)
			updateMoreSummary(loadedCounts);
	}, [loadedCounts]);

	// The issues and the standalone comments in one list, the oldest first; an issue is as old as its first comment
	const allItems = useMemo(() =>
	{
		const issues: SectionItem[] = (commentsQuery.data?.issues ?? []).map(issue =>
		{
			const firstComment = issue.comments?.[0]?.created;
			const created = firstComment && new Date(firstComment) < new Date(issue.created) ? firstComment : issue.created;
			return { type: 'issue', created, data: issue };
		});
		const comments: SectionItem[] = (commentsQuery.data?.standalone ?? []).map(comment => ({ type: 'comment', created: comment.created, data: comment }));
		return [...issues, ...comments].sort((a, b) => new Date(a.created).getTime() - new Date(b.created).getTime());
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

	// The play page's More tells what the comments hold; opening it opens them right away, so a
	// player sees the comments (and the open issues, on the same tab) without a second click
	useEffect(() =>
	{
		const open = () =>
		{
			setHasEverOpened(true);
			setActiveTab('open');
		};
		window.addEventListener('comments-open', open);
		return () => window.removeEventListener('comments-open', open);
	}, []);

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

	// The anchor filter: every comment, the ones on no position, or the ones on the current node and the moves after it
	const matchesAnchorFilter = (comment: CommentType) =>
	{
		if (anchorFilter === 'all')
			return true;
		if (anchorFilter === 'general')
			return !comment.position;
		return comment.position != null && activeNodeCommentIds.has(comment.id);
	};

	// The open tab shows the standalone comments and the open issues, the closed tab the closed issues;
	// an issue passes the anchor filter when one of its comments does
	const shouldShowItem = (item: SectionItem) =>
	{
		if (!activeTab)
			return false;
		if (item.type === 'comment')
			return activeTab === 'open' && matchesAnchorFilter(item.data);
		const status = activeTab === 'open' ? IssueStatus.OPEN : IssueStatus.CLOSED;
		return item.data.tsumego_issue_status_id === status
			&& (anchorFilter === 'all' || (item.data.comments?.some(matchesAnchorFilter) ?? false));
	};

	const showContent = activeTab !== null;
	const showForm = activeTab === 'open';
	const hasContent = counts.comments > 0 || counts.openIssues > 0;
	const closedCount = counts.issues - counts.openIssues;

	// Number of items that pass the current tab + anchor filters (for empty-state)
	const visibleItemCount = allItems.filter(shouldShowItem).length;

	return (
		<>
			<div className="tsumego-comments__tabs">
				<button
					className={`tsumego-comments__tab ${activeTab === 'open' ? 'active' : ''}${!hasContent ? ' tsumego-comments__tab--empty' : ''}`}
					data-filter="open"
					onClick={() => toggleTab('open')}
				>
					{openTabText(counts)}
				</button>
				<button
					className={`tsumego-comments__tab ${activeTab === 'closed' ? 'active' : ''}${closedCount === 0 ? ' tsumego-comments__tab--empty' : ''}`}
					data-filter="closed"
					onClick={() => toggleTab('closed')}
				>
					{closedTabText(closedCount)}
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
								const issue = item.data;
								return (
									<div
										key={`issue-${issue.id}`}
										style={{
											display: shouldShowItem(item) ? '' : 'none'
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
								const comment = item.data;
								return (
									<div
										className="tsumego-comment--standalone"
										key={`comment-${comment.id}`}
										style={{
											display: shouldShowItem(item) ? '' : 'none'
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
