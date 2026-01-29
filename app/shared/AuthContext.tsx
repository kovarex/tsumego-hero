import { createContext, useContext, type ReactNode } from 'react';

interface AuthContextValue
{
	userId: number | null;
	isAdmin: boolean;
}

const AuthContext = createContext<AuthContextValue | null>(null);

interface AuthProviderProps
{
	userId: number | null;
	isAdmin: boolean;
	children: ReactNode;
}

export function AuthProvider({ userId, isAdmin, children }: AuthProviderProps)
{
	return <AuthContext.Provider value={{ userId, isAdmin }}>{children}</AuthContext.Provider>;
}

/**
 * Hook to access current user auth state.
 * Must be used within an AuthProvider.
 */
export function useAuth(): AuthContextValue
{
	const context = useContext(AuthContext);
	if (!context) 
		throw new Error('useAuth must be used within an AuthProvider');

	return context;
}
