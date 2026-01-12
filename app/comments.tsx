import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { CommentSection } from './comments/CommentSection.tsx';
import type { Issue, Comment, CommentCounts } from './comments/commentTypes.ts';

// Wait for DOM to be fully loaded before initializing
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initializeComments);
} else {
	initializeComments();
}

function initializeComments() {
	// Find all comment section mount points
	const roots = document.querySelectorAll<HTMLElement>('[data-comments-root]');

	roots.forEach(root => {
		// Create a client per mount point to avoid shared state issues
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
		const isAdmin = root.dataset.isAdmin === 'true';  // Changed from === '1' to === 'true'
	
	// Parse initial counts from PHP (passed in data attributes)
	let initialCounts: CommentCounts = { 
		total: 0, comments: 0, issues: 0, openIssues: 0 
	};
	
	try {
		initialCounts = root.dataset.initialCounts ? JSON.parse(root.dataset.initialCounts) : initialCounts;
		console.log('Parsed comment counts:', { initialCounts, tsumegoId, userId, isAdmin });
	} catch (e) {
		console.error('Failed to parse initial comment counts:', e);
	}
	
	const reactRoot = createRoot(root);
	reactRoot.render(
		<QueryClientProvider client={queryClient}>
			<CommentSection 
				tsumegoId={tsumegoId}
				userId={userId}
				isAdmin={isAdmin}
				initialCounts={initialCounts}
			/>
		</QueryClientProvider>
	);
	});
}
