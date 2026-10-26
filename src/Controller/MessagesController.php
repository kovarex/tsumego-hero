<?php

use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Lib\Error\UnprocessableEntityException;
use App\Utility\Auth;
use App\Utility\Util;

/**
 * Messages between players. The page is drawn by React (app/messages), which talks to
 * the JSON actions here:
 *
 * - GET  /messages/conversations                   the player's conversations
 * - GET  /messages/conversations/<partner>?before=  messages with one player
 * - POST /messages/conversations/<partner>          send {"text": ...}
 * - POST /messages/conversations/<partner>/read     mark the partner's messages read
 * - GET  /messages/recipients?query=                players to write to
 */
class MessagesController extends AppController
{
	/**
	 * The messages page, with the conversation with a player open when the address names one.
	 */
	#[HttpGet]
	public function index(?string $partnerUserId = null): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		$partner = null;
		if ($partnerUserId !== null)
		{
			$partner = $this->findPartner((int) $partnerUserId);
			// Writing to yourself is not a conversation, the list is what is left.
			if ($partner['id'] === Auth::getUserID())
				return $this->redirect('/messages');
		}

		$this->set('_page', 'user');
		$this->set('_title', $partner && $partner['displayName'] ? 'Messages with ' . $partner['displayName'] : 'Messages');
		$this->set('partner', $partner);
		return null;
	}

	#[HttpGet]
	public function conversations(): CakeResponse
	{
		$this->Authorization->authorize('Message', 'read');
		return $this->json(['conversations' => Message::conversations(Auth::getUserID())]);
	}

	#[HttpGet]
	public function conversation(string $partnerUserId): CakeResponse
	{
		$this->Authorization->authorize('Message', 'read');
		$partner = $this->findPartner((int) $partnerUserId);
		$before = $this->request->query('before');
		$conversation = Message::conversation(Auth::getUserID(), $partner['id'], $before === null ? null : (int) $before);
		return $this->json(['partner' => $partner] + $conversation);
	}

	#[HttpPost]
	public function send(string $partnerUserId): CakeResponse
	{
		$this->Authorization->authorize('Message', 'send');
		$partner = $this->findPartner((int) $partnerUserId);
		$input = json_decode((string) $this->request->input(), true);
		$text = (string) ($input['text'] ?? '');

		$problem = Message::problemWith(Auth::getUserID(), $partner['id'], $text);
		if ($problem === null && !Auth::isAdmin() && Message::reachedUnansweredRecipientLimit(Auth::getUserID(), $partner['id']))
			$problem = 'You wrote to ' . Message::DAILY_UNANSWERED_RECIPIENTS . ' players who did not answer yet today. Please wait for an answer or try again tomorrow.';
		if ($problem !== null)
			throw new UnprocessableEntityException($problem);

		$messageId = Message::send(Auth::getUserID(), $partner['id'], $text, $this->_getEmailer());
		$row = Util::query('SELECT id, sender_user_id, text, created, read_at FROM message WHERE id = ?', [$messageId])[0];
		return $this->json(['message' => Message::messagePayload($row)]);
	}

	#[HttpPost]
	public function read(string $partnerUserId): CakeResponse
	{
		$this->Authorization->authorize('Message', 'read');
		$partner = $this->findPartner((int) $partnerUserId);
		Message::markRead(Auth::getUserID(), $partner['id']);
		return $this->json(['unreadCount' => Message::unreadCount(Auth::getUserID())]);
	}

	#[HttpGet]
	public function recipients(): CakeResponse
	{
		$this->Authorization->authorize('Message', 'send');
		$query = (string) $this->request->query('query');
		return $this->json(['players' => Message::searchRecipients(Auth::getUserID(), $query)]);
	}

	/**
	 * The other player of a conversation. A deleted account stays, so the conversation with it
	 * can still be read, it only can't get new messages.
	 *
	 * @return array{id: int, displayName: string|null, avatarUrl: string|null, rating: float}
	 */
	private function findPartner(int $userId): array
	{
		$rows = Util::query(
			'SELECT id AS user_id, display_name, deleted, external_id, picture, email, avatar_setting_id, uploaded_avatar_hash, rating FROM user WHERE id = ?',
			[$userId]);
		if (!$rows)
			throw new NotFoundException('Player not found');
		return Message::playerPayload($rows[0]);
	}

	private function json(array $data): CakeResponse
	{
		$this->autoRender = false;
		$this->response->type('json');
		$this->response->body(json_encode($data));
		return $this->response;
	}
}
