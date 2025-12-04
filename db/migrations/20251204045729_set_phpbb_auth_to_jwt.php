<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SetPhpbbAuthToJwt extends AbstractMigration
{
	/**
	 * Change phpBB authentication method from mycustomauth to jwtauth.
	 *
	 * JWT auth supports multi-device login (each device gets independent JWT token).
	 * Old mycustomauth used single database token which overwrote on new login.
	 */
	public function change(): void
	{
		// Update phpBB config to use JWT authentication
		$this->execute("
			UPDATE phpbb_config 
			SET config_value = 'jwtauth' 
			WHERE config_name = 'auth_method'
		");
	}
}
