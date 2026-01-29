/**
 * Error message component with retry button
 * Used across the app for consistent error handling
 */
export function ErrorMessage({
	message = 'Something went wrong. Please try again.',
	onRetry
}: {
	message?: string;
	onRetry: () => void;
})
{
	return (
		<div className="error-message">
			<p>⚠️ {message}</p>
			<button onClick={onRetry}>Retry</button>
		</div>
	);
}
