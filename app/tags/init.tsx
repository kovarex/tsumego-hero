import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ErrorBoundary } from '../shared/ErrorBoundary';
import { AuthProvider } from '../shared/AuthContext';
import { TagEditor } from './TagEditor';

const queryClient = new QueryClient({
	defaultOptions: {
		queries: { refetchOnWindowFocus: false },
	},
});

export function initializeTagEditor()
{
	const root = document.querySelector<HTMLElement>('[data-tag-editor-root]');
	if (!root) return;

	const props = JSON.parse(root.dataset.tagEditorProps || '{}');

	const reactRoot = createRoot(root);
	reactRoot.render(
		<ErrorBoundary>
			<AuthProvider userId={props.userId ?? null} isAdmin={props.isAdmin ?? false}>
				<QueryClientProvider client={queryClient}>
					<TagEditor
						tsumegoId={props.tsumegoId ?? 0}
						isAdmin={props.isAdmin ?? false}
						problemSolved={props.problemSolved ?? false}
						canContribute={props.canContribute ?? false}
						initialTags={props.initialTags ?? []}
					/>
				</QueryClientProvider>
			</AuthProvider>
		</ErrorBoundary>
	);
}
