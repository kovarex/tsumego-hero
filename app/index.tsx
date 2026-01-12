import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ErrorBoundary } from './shared/ErrorBoundary';
import { CommentSection } from './comments/CommentSection';
import { IssuesList } from './issues/IssuesList';
import type { CommentCounts } from './comments/commentTypes';

// Wait for DOM to be fully loaded
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initializeApp);
} else {
	initializeApp();
}

function initializeApp() {
	initializeComments();
	initializeIssuesList();
}

/**
 * Initialize comment sections (play page)
 */
function initializeComments() {
	const roots = document.querySelectorAll<HTMLElement>('[data-comments-root]');

	roots.forEach(root => {
		const queryClient = new QueryClient({
			defaultOptions: {
				queries: {
					refetchOnWindowFocus: false,
					retry: 1,
				},
			},
		});
		
		const tsumegoId = parseInt(root.dataset.tsumegoId || '0', 10);
		const userId = root.dataset.userId ? parseInt(root.dataset.userId, 10) : null;
		const isAdmin = root.dataset.isAdmin === 'true';
		
		// Parse only counts from SSR (no comment data)
		const initialCounts: CommentCounts = JSON.parse(root.dataset.initialCounts || '{"comments":0,"openIssues":0,"closedIssues":0}');
		
		// Render CommentSection
		const reactRoot = createRoot(root);
		reactRoot.render(
			<ErrorBoundary>
				<QueryClientProvider client={queryClient}>
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
	
	const queryClient = new QueryClient({
		defaultOptions: {
			queries: {
				refetchOnWindowFocus: false,
				retry: 1,
				staleTime: 0,
			},
		},
	});
	
	// Parse URL params for initial state
	const statusFilter = (root.dataset.statusFilter || 'opened') as 'opened' | 'closed' | 'all';
	const currentPage = parseInt(root.dataset.currentPage || '1', 10);
	const userId = root.dataset.userId ? parseInt(root.dataset.userId, 10) : null;
	const isAdmin = root.dataset.isAdmin === 'true';
	
	// Render IssuesList
	const reactRoot = createRoot(root);
	reactRoot.render(
		<ErrorBoundary>
			<QueryClientProvider client={queryClient}>
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
