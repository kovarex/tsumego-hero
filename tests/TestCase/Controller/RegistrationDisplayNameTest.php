<?php

/**
 * Tests for the display name a new account gets at registration.
 */
class RegistrationDisplayNameTest extends ControllerTestCase
{
	/**
	 * A name the account cannot be made with gets no account: empty, or with a character above
	 * U+FFFF that the utf8mb3 columns of the forum cannot hold (an emoji, a rare kanji).
	 */
	public function testRegistrationWithANameItCannotUseCreatesNoAccount(): void
	{
		new ContextPreparator(['user' => null]);

		foreach (['empty' => '', 'emoji' => 'Player😀', 'rare kanji' => '𠮷野家'] as $case => $name)
		{
			$this->testAction('users/add', [
				'data' => [
					'User' => [
						'name' => $name,
						'email' => $case . '@example.com',
						'password1' => 'Kd8#vQzP2mWx!7t',
						'password2' => 'Kd8#vQzP2mWx!7t',
					]
				],
				'method' => 'POST',
				'return' => 'vars'
			]);

			$user = ClassRegistry::init('User')->find('first', [
				'conditions' => ['email' => $case . '@example.com']
			]);
			$this->assertEmpty($user, 'A registration with an ' . $case . ' name must not create an account');
		}
	}
}
