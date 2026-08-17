<?php

App::uses('Component', 'Controller');
App::uses('ForbiddenException', 'Routing/Error');
App::uses('UnauthorizedException', 'Routing/Error');

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
class AuthorizationComponent extends Component
{
	public function authorize($resource, $action = null): void
	{
		if (!$this->can($resource, $action))
		{
			if (Auth::identity() === null)
				throw new UnauthorizedException();
			throw new ForbiddenException();
		}
	}

	public function can($resource, $action = null): bool
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
		App::uses($class, 'Policy');
		if (!method_exists($class, $method))
			throw new RuntimeException("Missing policy method {$class}::{$method}");
		$identity = Auth::identity();
		if ($entity !== null)
			return (bool) call_user_func([$class, $method], $identity, $entity);
		return (bool) call_user_func([$class, $method], $identity);
	}

	public function skipAuthorization(): void
	{
		// future fail-closed bookkeeping; no-op for now
	}
}
