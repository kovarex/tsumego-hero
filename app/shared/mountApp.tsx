import { createRoot, type Root } from 'react-dom/client';
import { QueryClientProvider } from '@tanstack/react-query';
import { ErrorBoundary } from './ErrorBoundary';
import { AuthProvider } from './AuthContext';
import { queryClient } from '../queryClient';
import type { ComponentType } from 'react';

//The roots mounted so far, so an element isn't mounted twice and the page can unmount what it replaces
const roots = new Map<HTMLElement, Root>();

//Mount a React component into all DOM elements matching `selector` within `container`.
//Props are read from a `data-props` JSON attribute on each element.
//AuthProvider is always included (harmless if unused by the component).
export function mountApp(
	selector: string,
	Component: ComponentType<any>,
	container: ParentNode = document
): void
{
	container.querySelectorAll<HTMLElement>(selector).forEach(element =>
	{
		if (roots.has(element))
			return;
		const props = JSON.parse(element.dataset.props || '{}');
		const root = createRoot(element);
		roots.set(element, root);
		root.render(
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

//Unmounts the components within `container`, before the page replaces it (the play page's next problem)
export function unmountApps(container: ParentNode): void
{
	for (const [element, root] of roots)
		if (container.contains(element))
		{
			root.unmount();
			roots.delete(element);
		}
}
