<?php

namespace App\Model\Table;

use App\Model\Entity\Message;
use App\Utility\Util;
use Cake\Log\Log as CakeLog;
use Cake\ORM\TableRegistry;
use Exception;
use InvalidArgumentException;
use Cake\Routing\Router;

//A message one player sent to another. The messages between two players are their
//conversation, so a conversation has no row of its own: it is addressed by the
//other player, the partner.
//Table: message
//  - sender_user_id, recipient_user_id (FK -> user)
//  - text: plain text, the page turns the addresses in it into links
//  - created
//  - read_at: when the recipient opened the conversation, NULL while unread
//The recipient can get an email about a new message (user.email_on_new_message).
//It is sent for the first unread message of a sender only, so a lively conversation
//doesn't fill the inbox: the next one comes after the recipient read the previous.
class MessageTable extends AppTable
{
	//Longest message accepted, in characters.
	public const MAX_LENGTH = 5000;

	//Messages of a conversation sent to the page at once, older ones are loaded on request.
	public const PAGE_SIZE = 50;

	//Players found by the search of a recipient.
	public const RECIPIENT_SEARCH_LIMIT = 10;

	//How many players who never wrote back a player can write to in a day. It keeps an account
	//from sending its messages (and the emails they bring) to everyone, while talking to
	//players who answer is never limited.
	public const DAILY_UNANSWERED_RECIPIENTS = 10;

	//Why a message can't be sent, null when it can. The sender is the one asking, so this
	//only looks at the recipient and the text.
	public static function problemWith(int $senderUserId, int $recipientUserId, string $text): ?string
	{
		if ($senderUserId === $recipientUserId)
			return 'You can not send a message to yourself.';
		$recipient = UserTable::row($recipientUserId);
		if (!$recipient || UserTable::isDeleted($recipient))
			return 'The player does not exist.';
		$text = self::normalizeText($text);
		if ($text === '')
			return 'The message is empty.';
		if (mb_strlen($text) > self::MAX_LENGTH)
			return 'The message is too long, at most ' . self::MAX_LENGTH . ' characters fit.';
		return null;
	}

	//Store a message and email the recipient about it when they asked for it. This is the
	//one way a message is sent, whether a player wrote it or the site writes on behalf of
	//someone (an admin answering a proposal), so every message is announced the same way.
	//$text: Plain text, http(s) addresses in it are shown as links
	//$emailer: CakeEmail from AppController::_getEmailer(), which a test replaces
	//Returns the id of the new message
	public static function send(int $senderUserId, int $recipientUserId, string $text, mixed $emailer): int
	{
		$problem = self::problemWith($senderUserId, $recipientUserId, $text);
		if ($problem !== null)
			throw new InvalidArgumentException($problem);

		$text = self::normalizeText($text);
		$messageTable = TableRegistry::getTableLocator()->get('Message');
		$message = $messageTable->saveOrFail($messageTable->newEntity([
			'sender_user_id' => $senderUserId,
			'recipient_user_id' => $recipientUserId,
			'text' => $text,
		]));

		self::notifyByEmail($senderUserId, $recipientUserId, $text, $emailer);
		return $message->id;
	}

	//Line ends are kept as \n and the blank space around the message is dropped.
	public static function normalizeText(string $text): string
	{
		return trim(str_replace(["\r\n", "\r"], "\n", $text));
	}

	private static function notifyByEmail(int $senderUserId, int $recipientUserId, string $text, mixed $emailer): void
	{
		$recipient = UserTable::row($recipientUserId);
		if (!$recipient['email_on_new_message'] || empty($recipient['email']))
			return;

		// An earlier message the recipient didn't read yet was already announced.
		if (self::unreadCountFrom($recipientUserId, $senderUserId) > 1)
			return;

		$sender = UserTable::row($senderUserId);
		$senderName = UserTable::displayName($sender);
		$emailer->from(['me@tsumego.com' => 'https://tsumego.com']);
		$emailer->to($recipient['email']);
		$emailer->subject('New message from ' . $senderName . ' on Tsumego Hero');
		$body = $senderName . ' sent you a message on Tsumego Hero:

' . $text . '

Reply: ' . Router::url('/messages/' . $senderUserId, true) . '

You get an email for the first message you have not read yet. You can turn these emails off in your settings: ' . Router::url('/users/settings', true);

		// The message is stored already, a mail server that does not answer only loses the email.
		try
		{
			$emailer->send($body);
		}
		catch (Exception $e)
		{
			CakeLog::write('error', 'A Tsumego Hero message notification could not be sent: ' . $e->getMessage());
		}
	}

	//The conversations of a player, the one with the newest message first. Each carries the
	//partner, the newest message and how many messages of the partner are unread.
	/** @return list<array{partner: array<string, mixed>, lastMessage: array<string, mixed>, unreadCount: int}> */
	public static function conversations(int $userId): array
	{
		$rows = Util::query('
			SELECT
				conversation.unread_count,
				message.id, message.sender_user_id, message.text, message.created, message.read_at,
				user.id AS user_id, user.display_name, user.deleted, user.external_id, user.picture, user.email, user.avatar_setting_id, user.uploaded_avatar_hash, user.rating
			FROM (
				SELECT partner_user_id, MAX(id) AS last_message_id, SUM(unread) AS unread_count
				FROM (
					SELECT recipient_user_id AS partner_user_id, id, 0 AS unread FROM message WHERE sender_user_id = ?
					UNION ALL
					SELECT sender_user_id AS partner_user_id, id, read_at IS NULL AS unread FROM message WHERE recipient_user_id = ?
				) AS messages_of_user
				GROUP BY partner_user_id
			) AS conversation
			JOIN message ON message.id = conversation.last_message_id
			JOIN user ON user.id = conversation.partner_user_id
			ORDER BY conversation.last_message_id DESC', [$userId, $userId]);

		$conversations = [];
		foreach ($rows as $row)
			$conversations[] = [
				'partner' => self::playerPayload($row),
				'lastMessage' => self::messagePayload($row),
				'unreadCount' => (int) $row['unread_count'],
			];
		return $conversations;
	}

	//The newest messages between two players, oldest first, so they read top to bottom.
	//$beforeMessageId: Only messages older than this one, to load the earlier part
	/** @return array{messages: list<array<string, mixed>>, hasOlder: bool} */
	public static function conversation(int $userId, int $partnerUserId, ?int $beforeMessageId = null): array
	{
		$parameters = [$userId, $partnerUserId, $partnerUserId, $userId];
		$olderThan = '';
		if ($beforeMessageId !== null)
		{
			$olderThan = ' AND id < ?';
			$parameters[] = $beforeMessageId;
		}
		$rows = Util::query('
			SELECT id, sender_user_id, text, created, read_at
			FROM message
			WHERE ((sender_user_id = ? AND recipient_user_id = ?) OR (sender_user_id = ? AND recipient_user_id = ?))' . $olderThan . '
			ORDER BY id DESC
			LIMIT ' . (self::PAGE_SIZE + 1), $parameters);

		$hasOlder = count($rows) > self::PAGE_SIZE;
		$rows = array_reverse(array_slice($rows, 0, self::PAGE_SIZE));
		return [
			'messages' => array_map(fn(array $row) => self::messagePayload($row), $rows),
			'hasOlder' => $hasOlder,
		];
	}

	//Mark what the partner sent as read, which is what opening the conversation does.
	//Returns how many messages became read
	public static function markRead(int $userId, int $partnerUserId): int
	{
		return Util::execute(
			'UPDATE message SET read_at = ? WHERE recipient_user_id = ? AND sender_user_id = ? AND read_at IS NULL',
			[date('Y-m-d H:i:s'), $userId, $partnerUserId]);
	}

	public static function unreadCount(int $userId): int
	{
		return (int) Util::query('SELECT COUNT(*) AS count FROM message WHERE recipient_user_id = ? AND read_at IS NULL', [$userId])[0]['count'];
	}

	public static function unreadCountFrom(int $userId, int $senderUserId): int
	{
		return (int) Util::query(
			'SELECT COUNT(*) AS count FROM message WHERE recipient_user_id = ? AND sender_user_id = ? AND read_at IS NULL',
			[$userId, $senderUserId])[0]['count'];
	}

	//Whether the player reached the daily number of players who never wrote back, so a
	//message to one more of them must wait. A message to a player who wrote to them
	//before is always fine.
	public static function reachedUnansweredRecipientLimit(int $senderUserId, int $recipientUserId): bool
	{
		if (self::hasWrittenTo($recipientUserId, $senderUserId))
			return false;
		$recipients = Util::query('
			SELECT COUNT(DISTINCT sent.recipient_user_id) AS count
			FROM message AS sent
			WHERE sent.sender_user_id = ? AND sent.created > ? AND sent.recipient_user_id <> ?
				AND NOT EXISTS (SELECT 1 FROM message AS reply WHERE reply.sender_user_id = sent.recipient_user_id AND reply.recipient_user_id = sent.sender_user_id)',
			[$senderUserId, date('Y-m-d H:i:s', strtotime('-1 day')), $recipientUserId]);
		return (int) $recipients[0]['count'] >= self::DAILY_UNANSWERED_RECIPIENTS;
	}

	private static function hasWrittenTo(int $senderUserId, int $recipientUserId): bool
	{
		return (bool) Util::query('SELECT 1 FROM message WHERE sender_user_id = ? AND recipient_user_id = ? LIMIT 1', [$senderUserId, $recipientUserId]);
	}

	//Players whose display name contains the query, the ones starting with it first. The
	//player searching and the deleted accounts are left out, as nobody can write to them.
	/** @return list<array<string, mixed>> */
	public static function searchRecipients(int $userId, string $query): array
	{
		$query = trim($query);
		if ($query === '')
			return [];
		$escaped = addcslashes($query, '\\%_');
		$rows = Util::query('
			SELECT id AS user_id, display_name, deleted, external_id, picture, email, avatar_setting_id, uploaded_avatar_hash, rating
			FROM user
			WHERE deleted IS NULL AND id <> ? AND display_name LIKE ?
			ORDER BY display_name LIKE ? DESC, CHAR_LENGTH(display_name), display_name
			LIMIT ' . self::RECIPIENT_SEARCH_LIMIT, [$userId, '%' . $escaped . '%', $escaped . '%']);
		return array_map(fn(array $row) => self::playerPayload($row), $rows);
	}

	//A player the way the page shows them. A deleted account has no name and no avatar, the page writes it as deleted.
	/**
	 * @param array<string, mixed> $row With user_id, display_name, deleted, external_id, picture, email, avatar_setting_id, uploaded_avatar_hash, rating
	 * @return array{id: int, displayName: string|null, avatarUrl: string|null, rating: float}
	 */
	public static function playerPayload(array $row): array
	{
		$user = $row;
		$user['id'] = (int) $row['user_id'];
		return [
			'id' => $user['id'],
			'displayName' => UserTable::displayNameOrNull($user),
			'avatarUrl' => UserTable::avatarOrNull($user),
			'rating' => (float) $row['rating'],
		];
	}

	/**
	 * @param array<string, mixed> $row With id, sender_user_id, text, created, read_at
	 * @return array{id: int, senderUserId: int, text: string, created: string, readAt: string|null}
	 */
	public static function messagePayload(array $row): array
	{
		return [
			'id' => (int) $row['id'],
			'senderUserId' => (int) $row['sender_user_id'],
			'text' => (string) $row['text'],
			'created' => Util::toIso8601((string) $row['created']),
			'readAt' => $row['read_at'] === null ? null : Util::toIso8601((string) $row['read_at']),
		];
	}
}
