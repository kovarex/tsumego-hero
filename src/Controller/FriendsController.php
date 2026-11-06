<?php

namespace App\Controller;

use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Model\Table\FriendshipTable;
use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\CookieFlash;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Response;

//Friends: a player asks another one from their profile, and they are friends once the other accepts.
//Friends see on the homepage what the other did lately, so nobody's activity shows without their yes.
//Each answer goes back to the page it was given on: the profile of the other player or /friends.
class FriendsController extends AppController
{
	//The friends of the player and the requests waiting on either side
	#[HttpGet]
	public function index(): ?Response
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');
		$this->set('_page', 'friends');
		$this->set('_title', 'Tsumego Hero - Friends');
		$this->set('user', Auth::getUser());
		$this->set('lists', FriendshipTable::listsOf(Auth::getUserID()));
		return null;
	}

	//Asks the player to be a friend; when they asked already, this accepts it
	#[HttpPost]
	public function add(string $id): Response
	{
		$other = $this->otherPlayer($id);
		FriendshipTable::ask(Auth::getUserID(), (int) $other['id']);
		CookieFlash::set(FriendshipTable::stateBetween(Auth::getUserID(), (int) $other['id']) === FriendshipTable::FRIENDS
			? 'You and ' . $other['display_name'] . ' are friends now.'
			: 'You asked ' . $other['display_name'] . ' to be your friend.', 'success');
		return $this->back($other);
	}

	#[HttpPost]
	public function accept(string $id): Response
	{
		$other = $this->otherPlayer($id);
		if (FriendshipTable::accept(Auth::getUserID(), (int) $other['id']))
			CookieFlash::set('You and ' . $other['display_name'] . ' are friends now.', 'success');
		else
			CookieFlash::set($other['display_name'] . ' didn\'t ask you to be friends, or took it back.', 'error');
		return $this->back($other);
	}

	//Declines a request, takes one back, or ends a friendship: whatever is between the two goes
	#[HttpPost]
	public function remove(string $id): Response
	{
		$other = $this->otherPlayer($id);
		FriendshipTable::remove(Auth::getUserID(), (int) $other['id']);
		return $this->back($other);
	}

	//The other player of an answer; a player can't be their own friend, and a deleted account is gone
	/** @return array<string, mixed> */
	private function otherPlayer(string $id): array
	{
		if (!Auth::isLoggedIn())
			throw new UnauthorizedException('Sign in to have friends.');
		$other = UserTable::row((int) $id);
		if ($other === null || UserTable::isDeleted($other) || (int) $other['id'] === Auth::getUserID())
			throw new NotFoundException('No such player.');
		return $other;
	}

	//Back to the page the answer was given on, which the form names; the profile otherwise
	/** @param array<string, mixed> $other */
	private function back(array $other): Response
	{
		return $this->redirect($this->input('from') === 'friends' ? '/friends' : '/users/view/' . $other['id']);
	}
}
