<?php

/**
 * Shim of CakePHP 5's AuthorizationComponent. The call site is identical:
 * $this->Authorization->authorize('Admin') -> App\Policy\AdminPolicy::canAdminstats.
 *
 * Policies live in App\Policy and are resolved through composer (PSR-4), so a new
 * policy file is picked up without regenerating the classmap.
 *
 * Resources can be:
 * - a string (policy name, e.g. 'Admin') -> policy receives only the identity
 * - an array in the wrapped Cake form, e.g. ['Set' => $row] -> policy is resolved
 *   from the first key and receives the unwrapped row, e.g.
 *   App\Policy\SetPolicy::canView($user, $row)
 * - an object -> policy resolved from the class basename and the object is passed
 *   as the second argument (no current caller, kept for parity with CakePHP 5)
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
			$class = self::policyClass((string) key($resource)); // ['Set' => [...]] -> App\Policy\SetPolicy
			$entity = $resource[key($resource)]; // unwrap: ['Set' => [...]] -> [...]
		}
		elseif (is_object($resource))
		{
			$class = self::policyClass(get_class($resource));
			$entity = $resource;
		}
		else
		{
			$class = self::policyClass($resource);
			$entity = null;
		}
		$method = 'can' . ucfirst($action); // action is already camelCase, no Inflector
		if (!class_exists($class))
			throw new RuntimeException("Policy class {$class} not found");
		if (!method_exists($class, $method))
			throw new RuntimeException("Missing policy method {$class}::{$method}");
		$identity = Auth::getIdentity();
		if ($entity !== null)
			return (bool) call_user_func([$class, $method], $identity, $entity);
		return (bool) call_user_func([$class, $method], $identity);
	}

	/**
	 * Policy class for a policy name: 'Set' -> App\Policy\SetPolicy.
	 * A name that already contains a namespace is reduced to its class name first.
	 */
	private static function policyClass(string $name): string
	{
		if (str_contains($name, '\\'))
			$name = substr($name, (int) strrpos($name, '\\') + 1);
		return 'App\\Policy\\' . (str_ends_with($name, 'Policy') ? $name : $name . 'Policy');
	}

	public function skipAuthorization(): void
	{
		// future fail-closed bookkeeping; no-op for now
	}
}
