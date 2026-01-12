import { Component, ReactNode, ErrorInfo } from 'react';

interface Props {
	children: ReactNode;
	fallback?: ReactNode;
}

interface State {
	hasError: boolean;
	error: Error | null;
}

/**
 * Error Boundary component to catch React errors and display fallback UI
 * instead of crashing the entire app with a white screen.
 *
 * Usage:
 *   <ErrorBoundary>
 *     <YourComponent />
 *   </ErrorBoundary>
 *
 * Or with custom fallback:
 *   <ErrorBoundary fallback={<div>Custom error message</div>}>
 *     <YourComponent />
 *   </ErrorBoundary>
 */
export class ErrorBoundary extends Component<Props, State> {
	constructor(props: Props) {
		super(props);
		this.state = { hasError: false, error: null };
	}

	static getDerivedStateFromError(error: Error): State {
		return { hasError: true, error };
	}

	componentDidCatch(error: Error, errorInfo: ErrorInfo) {
		console.error('React Error Boundary caught an error:', error, errorInfo);
	}

	render() {
		if (this.state.hasError) {
			// Use custom fallback if provided, otherwise default error UI
			if (this.props.fallback) {
				return this.props.fallback;
			}

			return (
				<div className="error-boundary">
					<div className="error-boundary__content">
						<h3>⚠️ Something went wrong</h3>
						<p>We encountered an error loading this section.</p>
						<button 
							onClick={() => window.location.reload()}
							className="error-boundary__reload"
						>
							Reload Page
						</button>
						{this.state.error && (
							<details className="error-boundary__details">
								<summary>Error details</summary>
								<pre>{this.state.error.toString()}</pre>
							</details>
						)}
					</div>
				</div>
			);
		}

		return this.props.children;
	}
}
