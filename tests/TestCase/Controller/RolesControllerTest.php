<?php

use App\Utility\CookieFlash;
use App\Utility\Util;
use Facebook\WebDriver\WebDriverSelect;

/**
 * Owners decide who is an admin or an owner (RolesController, User::changeRole).
 */
class RolesControllerTest extends TestCaseWithAuth
{
	private static function roleOf(int $userID): int
	{
		return (int) Util::query('SELECT user_role_id FROM user WHERE id = ?', [$userID])[0]['user_role_id'];
	}

	private function changeRole(int $userID, int $roleID): void
	{
		$this->testAction('/roles/change/' . $userID, ['method' => 'POST', 'data' => ['user_role_id' => $roleID]]);
	}

	public function testOwnerMakesPlayerAnAdmin(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'owner', 'owner' => true],
			'other-users' => [['name' => 'player']]]);
		$playerID = $context->otherUsers[0]['id'];

		$this->changeRole($playerID, User::ROLE_ADMIN);

		$this->assertSame(User::ROLE_ADMIN, self::roleOf($playerID));
		$this->assertSame('The role of DN_player is admin now', CookieFlash::get());
		$activity = ClassRegistry::init('AdminActivity')->find('first')['AdminActivity'];
		$this->assertSame(AdminActivityType::ROLE_CHANGE, (int) $activity['type']);
		$this->assertSame($context->user['id'], (int) $activity['user_id']);
		$this->assertSame(['user_id' => $playerID, 'display_name' => 'DN_player', 'role' => 'player'], json_decode($activity['old_value'], true));
		$this->assertSame('admin', $activity['new_value']);
	}

	public function testOwnerMakesAdminAnOwnerAndTakesItBack(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'owner', 'owner' => true],
			'other-users' => [['name' => 'admin', 'admin' => true]]]);
		$adminID = $context->otherUsers[0]['id'];

		$this->changeRole($adminID, User::ROLE_OWNER);
		$this->assertSame(User::ROLE_OWNER, self::roleOf($adminID));

		$this->changeRole($adminID, User::ROLE_PLAYER);
		$this->assertSame(User::ROLE_PLAYER, self::roleOf($adminID));
	}

	public function testAdminCantChangeRoles(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'other-users' => [['name' => 'player']]]);

		try
		{
			$this->changeRole($context->otherUsers[0]['id'], User::ROLE_ADMIN);
			$this->fail('An admin changed a role');
		}
		catch (ForbiddenException)
		{
		}
		$this->assertSame(User::ROLE_PLAYER, self::roleOf($context->otherUsers[0]['id']));
	}

	public function testAdminCantMakeItselfAnOwner(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);
		$this->expectException(ForbiddenException::class);
		$this->changeRole($context->user['id'], User::ROLE_OWNER);
	}

	public function testLastOwnerStaysOwner(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'owner', 'owner' => true]]);

		$this->changeRole($context->user['id'], User::ROLE_ADMIN);

		$this->assertSame(User::ROLE_OWNER, self::roleOf($context->user['id']));
		$this->assertSame('DN_owner is the last owner, make someone else an owner first.', CookieFlash::get());
		$this->assertSame(0, ClassRegistry::init('AdminActivity')->find('count'));
	}

	public function testOwnerGivesUpTheRoleWhenThereIsAnotherOwner(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'owner', 'owner' => true],
			'other-users' => [['name' => 'second owner', 'owner' => true]]]);

		$this->changeRole($context->user['id'], User::ROLE_ADMIN);

		$this->assertSame(User::ROLE_ADMIN, self::roleOf($context->user['id']));
		// the list of the roles is for owners only, so the former owner goes to the profile
		$this->assertStringEndsWith('/users/view/' . $context->user['id'], $this->headers['Location']);
	}

	public function testDeletedOwnerDoesntCountAsAnotherOwner(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'owner', 'owner' => true],
			'other-users' => [['name' => 'gone', 'owner' => true, 'deleted' => true]]]);

		$this->changeRole($context->user['id'], User::ROLE_PLAYER);

		$this->assertSame(User::ROLE_OWNER, self::roleOf($context->user['id']));
	}

	public function testUnknownRoleIsRefused(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'owner', 'owner' => true],
			'other-users' => [['name' => 'player']]]);

		$this->changeRole($context->otherUsers[0]['id'], 9);

		$this->assertSame(User::ROLE_PLAYER, self::roleOf($context->otherUsers[0]['id']));
		$this->assertSame('There is no such role.', CookieFlash::get());
	}

	public function testRolesPageListsAdminsAndOwners(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'owner', 'owner' => true],
			'other-users' => [
				['name' => 'admin', 'admin' => true],
				['name' => 'player']]]);

		$view = $this->testAction('/roles', ['method' => 'GET', 'return' => 'view']);

		$this->assertStringContainsString('DN_owner', $view);
		$this->assertStringContainsString('DN_admin', $view);
		$this->assertStringNotContainsString('DN_player', $view);
		$this->assertStringContainsString('role-submit-' . $context->otherUsers[0]['id'], $view);
	}

	public function testOnlyOwnerSeesRolesPage(): void
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);
		$this->expectException(ForbiddenException::class);
		$this->testAction('/roles', ['method' => 'GET']);
	}

	public function testOnlyOwnerSeesRoleFormOnProfile(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'other-users' => [['name' => 'player']]]);
		$view = $this->testAction('/users/view/' . $context->otherUsers[0]['id'], ['method' => 'GET', 'return' => 'view']);
		$this->assertStringNotContainsString('role-submit-', $view);
		$this->assertStringNotContainsString('href="/roles"', $view);
	}

	public function testOwnerMakesPlayerAnAdminOnTheProfile(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'owner', 'owner' => true],
			'other-users' => [['name' => 'player']]]);
		$playerID = $context->otherUsers[0]['id'];
		$browser = Browser::instance();

		$browser->get('users/view/' . $playerID);
		(new WebDriverSelect($browser->findById('role-select-' . $playerID)))->selectByValue((string) User::ROLE_ADMIN);
		$browser->clickId('role-submit-' . $playerID);
		$browser->waitUntilCssSelectorExistsWithText('[role="alert"]', 'The role of DN_player is admin now');

		$this->assertSame(User::ROLE_ADMIN, self::roleOf($playerID));
		$browser->get('roles');
		$this->assertStringContainsString('DN_player', $browser->find('#roles-users')->getText());
	}
}
