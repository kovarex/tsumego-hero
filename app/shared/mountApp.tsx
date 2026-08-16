import { createRoot } from 'react-dom/client';
import { QueryClientProvider } from '@tanstack/react-query';
import { ErrorBoundary } from './ErrorBoundary';
import { AuthProvider } from './AuthContext';
import { queryClient } from '../queryClient';
import type { ComponentType } from 'react';

/**
 * Mount a React component into all DOM elements matching `selector`.
 *
 * Props are read from a `data-props` JSON attribute on each element.
 * AuthProvider is always included (harmless if unused by the component).
 */
export function mountApp(
	selector: string,
	Component: ComponentType<any>,
): void
{
	document.querySelectorAll<HTMLElement>(selector).forEach(root =>
	{
		const props = JSON.parse(root.dataset.props || '{}');
		createRoot(root).render(
			<ErrorBoundary>
				<QueryClientProvider client={queryClient}>
					<AuthProvider userId={props.userId ?? null} isAdmin={props.isAdmin ?? false}>
						<Component {...props} />
					</AuthProvider>
				</QueryClientProvider>
			</ErrorBoundary>
		);
	});
}
