<?php

/**
 * Tests for the display name a new account gets at registration.
 */
class RegistrationDisplayNameTest extends ControllerTestCase
{
	/**
	 * A player who leaves the name empty gets no account.
	 */
	public function testRegistrationWithoutNameCreatesNoAccount(): void
	{
		new ContextPreparator(['user' => null]);

		$this->testAction('users/add', [
			'data' => [
				'User' => [
					'name' => '',
					'email' => 'noname@example.com',
					'password1' => 'password123',
					'password2' => 'password123',
				]
			],
			'method' => 'POST',
			'return' => 'vars'
		]);

		$user = ClassRegistry::init('User')->find('first', [
			'conditions' => ['email' => 'noname@example.com']
		]);
		$this->assertEmpty($user, 'A registration without a name must not create an account');
	}
}
