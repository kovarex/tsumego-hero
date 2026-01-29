/**
 * API client helpers for JSON requests
 */

class ApiError extends Error
{
	constructor(
		message: string,
		public status: number,
		public data?: unknown
	)
	{
		super(message);
		this.name = 'ApiError';
	}
}

async function handleResponse<T>(response: Response): Promise<T>
{
	if (!response.ok)
	{
		let errorData: unknown;
		try
		{
			errorData = await response.json();
		}
		catch
		{
			throw new ApiError(`HTTP ${response.status}: ${response.statusText}`, response.status);
		}

		const errorMessage =
			errorData && typeof errorData === 'object' && 'error' in errorData
				? String(errorData.error)
				: `HTTP ${response.status}: ${response.statusText}`;

		throw new ApiError(errorMessage, response.status, errorData);
	}

	return response.json();
}

export async function get<T>(url: string): Promise<T>
{
	const response = await fetch(url, {
		method: 'GET',
		headers: {
			Accept: 'application/json',
			'X-Requested-With': 'XMLHttpRequest'
		}
	});
	return handleResponse<T>(response);
}

export async function post<T>(url: string, data: unknown): Promise<T>
{
	const response = await fetch(url, {
		method: 'POST',
		headers: {
			Accept: 'application/json',
			'Content-Type': 'application/json',
			'X-Requested-With': 'XMLHttpRequest'
		},
		body: JSON.stringify(data)
	});
	return handleResponse<T>(response);
}

export async function del<T>(url: string): Promise<T>
{
	const response = await fetch(url, {
		method: 'POST', // CakePHP routes use POST for delete actions
		headers: {
			Accept: 'application/json',
			'Content-Type': 'application/json',
			'X-Requested-With': 'XMLHttpRequest'
		},
		body: JSON.stringify({}) // Empty body for POST
	});
	return handleResponse<T>(response);
}

export async function moveComment(commentId: number, targetIssueId: number | 'standalone'): Promise<{ success: boolean }>
{
	// Use FormData instead of JSON because CakePHP expects bracket notation
	const formData = new FormData();
	formData.append('data[Comment][tsumego_issue_id]', String(targetIssueId));

	const response = await fetch(`/tsumego-issues/move-comment/${commentId}`, {
		method: 'POST',
		headers: {
			'X-Requested-With': 'XMLHttpRequest' // Mark as AJAX to prevent rating processing
		},
		body: formData
	});

	if (!response.ok) 
		throw new ApiError(`HTTP ${response.status}`, response.status);

	return response.json();
}

export { ApiError };
