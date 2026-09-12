<?php

/**
 * Shim of CakePHP 5's AuthorizationComponent. The call site is identical:
 * $this->Authorization->authorize('Admin') -> AdminPolicy::canAdminstats.
 *
 * Resources can be:
 * - a string (policy name, e.g. 'Admin') -> policy receives only the identity
 * - an object (e.g. a $set) -> policy resolved from the class name and the
 *   resource is passed as the second argument, e.g. SetPolicy::canView($user, $set)
 *
 * - Not logged in        -> UnauthorizedException (401)
 * - Logged in, no grant  -> ForbiddenException (403)
 */
use App\Utility\Auth;

class AuthorizationComponent extends Component
{
	public function authorize(string|array|object $resource, ?string $action = null): void
	{
		if (!$this->can($resource, $action))
		{
			if (Auth::getIdentity() === null)
				throw new UnauthorizedException();
			throw new ForbiddenException();
		}
	}

	public function can(string|array|object $resource, ?string $action = null): bool
	{
		$action = $action ?: $this->_Collection->getController()->request->params['action'];
		if (is_array($resource))
		{
			$class = key($resource) . 'Policy'; // e.g. ['Set' => [...]] -> SetPolicy
			$entity = $resource[key($resource)]; // unwrap: ['Set' => [...]] -> [...]
		}
		elseif (is_object($resource))
		{
			$class = get_class($resource) . 'Policy';
			$entity = $resource;
		}
		else
		{
			$class = str_ends_with($resource, 'Policy') ? $resource : $resource . 'Policy';
			$entity = null;
		}
		$method = 'can' . ucfirst($action); // action is already camelCase, no Inflector
		if (!method_exists($class, $method))
			throw new RuntimeException("Missing policy method {$class}::{$method}");
		$identity = Auth::getIdentity();
		if ($entity !== null)
			return (bool) call_user_func([$class, $method], $identity, $entity);
		return (bool) call_user_func([$class, $method], $identity);
	}

	public function skipAuthorization(): void
	{
		// future fail-closed bookkeeping; no-op for now
	}
}
