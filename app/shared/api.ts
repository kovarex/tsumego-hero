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

/**
 * POST with FormData (for CakePHP bracket notation like data[Model][field]).
 * Use this when the backend expects form-encoded data instead of JSON.
 */
export async function postFormData<T>(url: string, data: Record<string, string>): Promise<T>
{
	const formData = new FormData();
	Object.entries(data).forEach(([key, value]) => formData.append(key, value));

	const response = await fetch(url, {
		method: 'POST',
		headers: {
			'X-Requested-With': 'XMLHttpRequest'
		},
		body: formData
	});
	return handleResponse<T>(response);
}

export { ApiError };
