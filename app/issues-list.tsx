import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { IssuesList } from './issues/IssuesList';
import type { IssueWithContext } from './issues/issueTypes';

// Wait for DOM to be fully loaded before initializing
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initializeIssuesList);
} else {
	initializeIssuesList();
}

function initializeIssuesList() {
	const root = document.querySelector<HTMLElement>('[data-issues-root]');
	
	if (!root) {
		console.error('[IssuesList] Mount point not found');
		return;
	}
	
	// Create query client
	const queryClient = new QueryClient({
		defaultOptions: {
			queries: {
				refetchOnWindowFocus: false,
				retry: 1,
			},
		},
	});
	
	// Parse initial data from data attributes
	let initialIssues: IssueWithContext[] = [];
	let initialCounts = { open: 0, closed: 0 };
	let initialFilter: 'opened' | 'closed' | 'all' = 'opened';
	let initialPage = 1;
	let totalPages = 1;
	
	try {
		initialIssues = root.dataset.initialIssues ? JSON.parse(root.dataset.initialIssues) : [];
		initialCounts = root.dataset.initialCounts ? JSON.parse(root.dataset.initialCounts) : { open: 0, closed: 0 };
		initialFilter = (root.dataset.statusFilter as 'opened' | 'closed' | 'all') || 'opened';
		initialPage = parseInt(root.dataset.currentPage || '1', 10);
		totalPages = parseInt(root.dataset.totalPages || '1', 10);
		
		console.log('[IssuesList] Parsed initial data:', {
			issueCount: initialIssues.length,
			counts: initialCounts,
			filter: initialFilter,
			page: initialPage,
			totalPages
		});
	} catch (e) {
		console.error('[IssuesList] Failed to parse initial data:', e);
	}
	
	// Mount React app
	const reactRoot = createRoot(root);
	reactRoot.render(
		<QueryClientProvider client={queryClient}>
			<IssuesList
				initialIssues={initialIssues}
				initialCounts={initialCounts}
				initialFilter={initialFilter}
				initialPage={initialPage}
				totalPages={totalPages}
			/>
		</QueryClientProvider>
	);
}
