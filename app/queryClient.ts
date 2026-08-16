import { QueryClient } from '@tanstack/react-query';
import { ApiError } from './shared/api';

export const queryClient = new QueryClient({
	defaultOptions: {
		queries: {
			refetchOnWindowFocus: false,
			retry: (failureCount, error) =>
			{
				if (error instanceof ApiError && error.status >= 400 && error.status < 500)
					return false;
				return failureCount < 3;
			},
			retryDelay: attemptIndex => Math.min(1000 * 2 ** attemptIndex, 30000)
		}
	}
});
