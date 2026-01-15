import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ErrorBoundary } from './shared/ErrorBoundary';
import { CommentSection } from './comments/CommentSection';
import { IssuesList } from './issues/IssuesList';
import { ApiError } from './shared/api';
import type { CommentCounts } from './comments/commentTypes';

const globalQueryClient = new QueryClient({
	defaultOptions: {
		queries: {
			refetchOnWindowFocus: false,
			retry: (failureCount, error) => {
				// Don't retry on client errors (4xx)
				if (error instanceof ApiError && error.status >= 400 && error.status < 500) {
					return false;
				}
				// Retry server errors up to 3 times
				return failureCount < 3;
			},
			retryDelay: attemptIndex => Math.min(1000 * 2 ** attemptIndex, 30000), // Exponential backoff
		},
	},
});

/**
 * Initialize comment sections (play page)
 */
function initializeComments() {
	const roots = document.querySelectorAll<HTMLElement>('[data-comments-root]');

	roots.forEach(root => {
		const tsumegoId = parseInt(root.dataset.tsumegoId || '0', 10);
		const userId = root.dataset.userId ? parseInt(root.dataset.userId, 10) : null;
		const isAdmin = root.dataset.isAdmin === 'true';
		const initialCounts: CommentCounts = JSON.parse(root.dataset.initialCounts || '{"comments":0,"openIssues":0,"closedIssues":0}');
		const reactRoot = createRoot(root);
		reactRoot.render(
			<ErrorBoundary>
				<QueryClientProvider client={globalQueryClient}>
					<CommentSection
						tsumegoId={tsumegoId}
						userId={userId}
						isAdmin={isAdmin}
						initialCounts={initialCounts}
					/>
				</QueryClientProvider>
			</ErrorBoundary>
		);
	});
}

/**
 * Initialize issues list (admin issues page)
 */
function initializeIssuesList() {
	const root = document.querySelector<HTMLElement>('[data-issues-root]');
	
	if (!root) return;
	
	// Parse URL params for initial state
	const statusFilter = (root.dataset.statusFilter || 'opened') as 'opened' | 'closed' | 'all';
	const currentPage = parseInt(root.dataset.currentPage || '1', 10);
	const userId = root.dataset.userId ? parseInt(root.dataset.userId, 10) : null;
	const isAdmin = root.dataset.isAdmin === 'true';
	
	const reactRoot = createRoot(root);
	reactRoot.render(
		<ErrorBoundary>
			<QueryClientProvider client={globalQueryClient}>
				<IssuesList
					initialFilter={statusFilter}
					initialPage={currentPage}
					userId={userId}
					isAdmin={isAdmin}
				/>
			</QueryClientProvider>
		</ErrorBoundary>
	);
}

/**
 * Main initialization entry point
 */
function initializeApp() {
	initializeComments();
	initializeIssuesList();
}

// Wait for DOM to be fully loaded, then initialize
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initializeApp);
} else {
	initializeApp();
}
