import { useState, useEffect } from 'react';
import { Issue } from './Issue';
import { useIssuesQuery, useInvalidateIssuesList } from './useIssues';
import { useCloseReopenIssue } from '../comments/useComments';
import { useDeleteComment } from '../comments/useComments';
import type { IssuesListResponse, IssueStatusFilter, IssueWithContext } from './issueTypes';
import { IssuesListSkeleton } from './IssueSkeleton';
import { ErrorMessage } from '../shared/ErrorMessage';

interface IssuesListProps {
	initialFilter: IssueStatusFilter;
	initialPage: number;
	userId: number | null;
	isAdmin: boolean;
}

export function IssuesList({
	initialFilter,
	initialPage,
	userId,
	isAdmin,
}: IssuesListProps) {
	// URL-synced state
	const [statusFilter, setStatusFilter] = useState<IssueStatusFilter>(initialFilter);
	const [page, setPage] = useState(initialPage);
	
	// Fetch issues with React Query - always fetch fresh, no SSR placeholder
	const issuesQuery = useIssuesQuery(statusFilter, page);
	
	// Mutations
	const closeReopenMutation = useCloseReopenIssue();
	const deleteMutation = useDeleteComment();
	const invalidateList = useInvalidateIssuesList();
	
	// Extract data from API (all from fetch, no SSR fallbacks)
	const issues = issuesQuery.data?.issues ?? [];
	const counts = issuesQuery.data?.counts ?? { open: 0, closed: 0 };
	const totalPages = issuesQuery.data?.totalPages ?? 1;
	const currentPage = issuesQuery.data?.currentPage ?? page;
	
	// Sync URL with state
	useEffect(() => {
		const url = new URL(window.location.href);
		url.searchParams.set('status', statusFilter);
		url.searchParams.set('page', page.toString());
		window.history.pushState({}, '', url);
	}, [statusFilter, page]);
	
	// Handlers
	const handleFilterChange = (newFilter: IssueStatusFilter) => {
		setStatusFilter(newFilter);
		setPage(1);  // Reset to page 1 when changing filter
	};
	
	const handlePageChange = (newPage: number) => {
		setPage(newPage);
		window.scrollTo(0, 0);  // Scroll to top on page change
	};
	
	const handleCloseReopen = async (issueId: number, newStatus: 'open' | 'closed') => {
		try {
			await closeReopenMutation.mutateAsync({ issueId, newStatus });
			await invalidateList();
		} catch (error) {
			console.error('[IssuesList] Close/reopen failed:', error);
			alert('Failed to update issue status. Please try again.');
		}
	};
	
	const handleDeleteComment = async (commentId: number) => {
		if (!confirm('Delete this comment?')) return;
		
		try {
			await deleteMutation.mutateAsync({ commentId });
			await invalidateList();
		} catch (error) {
			console.error('[IssuesList] Delete comment failed:', error);
			alert('Failed to delete comment. Please try again.');
		}
	};
	
	// Helper to build problem title
	const getProblemTitle = (item: IssueWithContext): string => {
		if (item.Set && item.TsumegoNum) {
			return `${item.Set.title} #${item.TsumegoNum}`;
		}
		return `Problem #${item.tsumegoId}`;
	};
	
	// Helper to build problem link
	const getProblemLink = (item: IssueWithContext): string => {
		return `/tsumegos/play/${item.tsumegoId}#issue-${item.issue.id}`;
	};

	return (
		<div>
			{/* Filter tabs */}
			<div className="issues-page__filters">
				<a 
					href={`/tsumego-issues?status=opened`}
					onClick={(e) => { e.preventDefault(); handleFilterChange('opened'); }}
					className={`issues-filter ${statusFilter === 'opened' ? 'issues-filter--active' : ''}`}
				>
					🔴 Open ({counts.open})
				</a>
				<a 
					href={`/tsumego-issues?status=closed`}
					onClick={(e) => { e.preventDefault(); handleFilterChange('closed'); }}
					className={`issues-filter ${statusFilter === 'closed' ? 'issues-filter--active' : ''}`}
				>
					✅ Closed ({counts.closed})
				</a>
				<a 
					href={`/tsumego-issues?status=all`}
					onClick={(e) => { e.preventDefault(); handleFilterChange('all'); }}
					className={`issues-filter ${statusFilter === 'all' ? 'issues-filter--active' : ''}`}
				>
					All ({counts.open + counts.closed})
				</a>
			</div>
			
			{/* Issues list */}
			<div className="issues-list">
				{issuesQuery.isLoading ? (
					<IssuesListSkeleton />
				) : issuesQuery.isError ? (
					<ErrorMessage 
						message="Failed to load issues. Please try again."
						onRetry={() => issuesQuery.refetch()}
					/>
				) : issues.length === 0 ? (
					<p className="issues-list__empty">
						{statusFilter === 'opened' && 'No open issues! 🎉'}
						{statusFilter === 'closed' && 'No closed issues yet.'}
						{statusFilter === 'all' && 'No issues found.'}
					</p>
				) : (
					issues.map((item) => (
						<div key={item.issue.id}>
							{/* Problem reference header */}
							<div className="issues-list__problem-ref">
								<a href={getProblemLink(item)}>{getProblemTitle(item)}</a>
							</div>
							
							{/* Issue component (reused from play page) */}
							<Issue
								issue={item.issue}
								issueNumber={item.issue.id}  // Use global ID in list context
								currentUserId={userId}
								isAdmin={isAdmin}
								onDelete={handleDeleteComment}
								onReply={async () => {}}  // No reply on list page
								onCloseReopen={handleCloseReopen}
								showReplyForm={false}  // Hide reply form on list page
								comments={item.comments}
								author={item.author}
							/>
						</div>
					))
				)}
			</div>
			
			{/* Pagination */}
			{totalPages > 1 && (
				<div className="issues-page__pagination">
					{currentPage > 1 && (
						<a 
							href={`/tsumego-issues?status=${statusFilter}&page=${currentPage - 1}`}
							onClick={(e) => { e.preventDefault(); handlePageChange(currentPage - 1); }}
						>
							« Previous
						</a>
					)}
					
					{Array.from({ length: totalPages }, (_, i) => i + 1).map((pageNum) => (
						<a
							key={pageNum}
							href={`/tsumego-issues?status=${statusFilter}&page=${pageNum}`}
							onClick={(e) => { e.preventDefault(); handlePageChange(pageNum); }}
							className={pageNum === currentPage ? 'active' : ''}
						>
							{pageNum}
						</a>
					))}
					
					{currentPage < totalPages && (
						<a 
							href={`/tsumego-issues?status=${statusFilter}&page=${currentPage + 1}`}
							onClick={(e) => { e.preventDefault(); handlePageChange(currentPage + 1); }}
						>
							Next »
						</a>
					)}
				</div>
			)}
		</div>
	);
}
